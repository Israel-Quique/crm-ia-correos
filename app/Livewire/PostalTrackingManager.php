<?php

namespace App\Livewire;

use App\Models\PostalPackage;
use App\Services\PostalApiSyncService;
use App\Services\PostalEventPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithPagination;

class PostalTrackingManager extends Component
{
    use WithPagination;

    public string $scope = 'nacional';
    public string $trackingFilter = '';
    public string $senderFilter = '';
    public string $recipientFilter = '';
    public string $eventFilter = '';
    public string $filterFrom = '';
    public string $filterTo = '';
    public string $from = '';
    public string $to = '';
    public bool $force = false;
    public bool $syncModalOpen = false;
    public ?string $syncNotice = null;
    public ?string $syncError = null;
    public ?int $selectedPackageId = null;

    public function mount(string $scope = 'nacional'): void
    {
        $this->scope = in_array($scope, ['nacional', 'internacional'], true) ? $scope : 'nacional';
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['trackingFilter', 'senderFilter', 'recipientFilter', 'eventFilter', 'filterFrom', 'filterTo'], true)) {
            $this->resetPage();
        }
    }

    public function updatedScope(string $scope): void
    {
        abort_unless(in_array($scope, ['nacional', 'internacional'], true), 422);
        $this->eventFilter = '';
        $this->resetPage();
        $this->selectedPackageId = null;
        $this->syncModalOpen = false;
        $this->syncNotice = null;
        $this->syncError = null;
    }

    public function getPackagesProperty(): LengthAwarePaginator
    {
        $query = PostalPackage::query()
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->whereNotNull('updated_at_api');

        foreach ([
            'trackingFilter' => 'tracking_number',
            'senderFilter' => 'sender',
            'recipientFilter' => 'recipient',
        ] as $filter => $column) {
            $value = trim($this->{$filter});
            if ($value !== '') {
                $query->whereLike($column, '%'.$value.'%');
            }
        }

        $eventFilter = trim($this->eventFilter);
        $hiddenEventFilters = array_merge(['__sin_evento__'], array_map(
            fn (string $category): string => 'category:'.rawurlencode($category),
            $this->hiddenEventCategories(),
        ));
        if (in_array($eventFilter, $hiddenEventFilters, true)) {
            $eventFilter = '';
        }

        if ($eventFilter === '__sin_evento__') {
            $this->applyCategoryFilter($query, 'Sin movimientos o sin clasificación confiable');
        } elseif (str_starts_with($eventFilter, 'category:')) {
            $this->applyCategoryFilter($query, rawurldecode(substr($eventFilter, strlen('category:'))));
        } elseif ($eventFilter !== '') {
            $query->where(function ($query) use ($eventFilter): void {
                $query->whereLike('current_event', '%'.$eventFilter.'%')
                    ->orWhereHas('events', fn ($events) => $events->whereLike('event_name', '%'.$eventFilter.'%'));
            });
        }

        if ($this->filterFrom !== '') {
            $query->whereDate('updated_at_api', '>=', $this->filterFrom);
        }
        if ($this->filterTo !== '') {
            $query->whereDate('updated_at_api', '<=', $this->filterTo);
        }

        $packages = $query->with('latestEvent')
            ->orderByDesc('updated_at_api')
            ->orderByDesc('id')
            ->paginate(14);
        $presenter = app(PostalEventPresenter::class);
        $packages->getCollection()->each(function (PostalPackage $package) use ($presenter): void {
            $package->setAttribute('display_status', $presenter->currentStatus($package));
            $package->setAttribute('display_category', $presenter->currentCategory($package));
        });

        return $packages;
    }

    public function getEventOptionsProperty(): array
    {
        $eventOptions = [];
        foreach (app(PostalEventPresenter::class)->categories($this->scope) as $category) {
            if (in_array($category, $this->hiddenEventCategories(), true)) {
                continue;
            }

            $eventOptions['category:'.rawurlencode($category)] = $category;
        }

        return $eventOptions;
    }

    private function hiddenEventCategories(): array
    {
        return $this->scope === 'nacional'
            ? ['Cancelado', 'Sin movimientos o sin clasificación confiable']
            : ['Importación detenida', 'Sin movimientos o sin clasificación confiable'];
    }

    private function applyCategoryFilter(Builder $query, string $category): void
    {
        $source = $this->scope === 'nacional' ? 'siop' : 'sitra';
        $presenter = app(PostalEventPresenter::class);
        $knownCodes = $presenter->knownEventCodes($this->scope);
        if ($category === 'Ventanilla') {
            $ventanillaCodes = $presenter->ventanillaEventCodes($this->scope);
            $query->where(function (Builder $matching) use ($ventanillaCodes): void {
                $matching->whereHas('latestEvent', function (Builder $event) use ($ventanillaCodes): void {
                    $event->where(function (Builder $classification) use ($ventanillaCodes): void {
                        foreach (['%ventanilla%', '%recojo%', '%recoger%', '%retiro%', '%pickup%'] as $term) {
                            $classification->orWhereLike('event_name', $term);
                        }

                        if ($ventanillaCodes !== []) {
                            $classification->orWhereIn('source_event', $ventanillaCodes);
                        }
                    });
                });
                $matching->orWhere(function (Builder $withoutEvents): void {
                    $withoutEvents->whereDoesntHave('latestEvent', function (Builder $event): void {
                        $event->whereNotNull('source_event')
                            ->whereRaw("trim(source_event) <> ''")
                            ->whereRaw("lower(trim(source_event)) <> 'unknown'");
                    })
                        ->where(function (Builder $status): void {
                            foreach (['%ventanilla%', '%recojo%', '%recoger%', '%retiro%', '%pickup%'] as $term) {
                                $status->orWhereLike('current_event', $term);
                            }
                        });
                });
            });

            return;
        }

        $eventCodes = $presenter->categoryCodes($this->scope, $category);
        $hasNational297 = $this->scope === 'nacional'
            && in_array($category, ['Recibido Origen', 'En Camino', 'Recibido Destino'], true);
        $currentStatuses = PostalPackage::query()
            ->where('scope', $this->scope)
            ->where('source', $source)
            ->whereDoesntHave('latestEvent', function (Builder $event): void {
                $event->whereNotNull('source_event')
                    ->whereRaw("trim(source_event) <> ''")
                    ->whereRaw("lower(trim(source_event)) <> 'unknown'");
            })
            ->whereNotNull('current_event')
            ->whereRaw("trim(current_event) <> ''")
            ->distinct()
            ->pluck('current_event')
            ->reject(fn ($status) => $presenter->isEmptyStatus((string) $status))
            ->filter(fn ($status) => $presenter->category($this->scope, null, (string) $status) === $category)
            ->values()
            ->all();
        $uncodedEventNames = DB::table('postal_events')
            ->join('postal_packages', 'postal_packages.id', '=', 'postal_events.postal_package_id')
            ->where('postal_packages.scope', $this->scope)
            ->where('postal_packages.source', $source)
            ->whereNotNull('postal_events.event_name')
            ->whereRaw("trim(postal_events.event_name) <> ''")
            ->where(function ($events): void {
                $events->whereNull('postal_events.source_event')
                    ->orWhereRaw("trim(postal_events.source_event) = ''")
                    ->orWhereRaw("lower(trim(postal_events.source_event)) = 'unknown'");
            })
            ->select('postal_events.event_name')
            ->distinct()
            ->pluck('postal_events.event_name')
            ->filter(fn ($eventName) => $presenter->category($this->scope, null, (string) $eventName) === $category)
            ->unique()
            ->values()
            ->all();
        $unclassified = $category === 'Sin movimientos o sin clasificación confiable';

        if ($eventCodes === [] && $currentStatuses === [] && $uncodedEventNames === [] && ! $unclassified && ! $hasNational297) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $matching) use ($category, $eventCodes, $knownCodes, $currentStatuses, $uncodedEventNames, $unclassified, $hasNational297): void {
            if ($currentStatuses !== []) {
                $matching->where(function (Builder $status) use ($currentStatuses): void {
                    $status->whereIn('current_event', $currentStatuses)
                        ->whereDoesntHave('latestEvent', function (Builder $event): void {
                            $event->whereNotNull('source_event')
                                ->whereRaw("trim(source_event) <> ''")
                                ->whereRaw("lower(trim(source_event)) <> 'unknown'");
                        });
                });
            }
            if ($eventCodes !== []) {
                $matching->orWhereHas('latestEvent', fn (Builder $event) => $event->whereIn('source_event', $eventCodes));
            }
            if ($hasNational297) {
                $matching->orWhereHas('latestEvent', function (Builder $events) use ($category): void {
                    $events->where('source_event', '297')
                        ->where(function ($classification) use ($category): void {
                            $transitText = fn ($text) => $text
                                ->whereLike('event_name', '%tránsito%')
                                ->orWhereLike('event_name', '%transito%')
                                ->orWhereLike('event_name', '%en camino%');
                            $destinationText = fn ($text) => $text
                                ->whereLike('event_name', '%destino%')
                                ->orWhereLike('event_name', '%oficina de entrega%')
                                ->orWhereLike('event_name', '%ventanilla%')
                                ->orWhereLike('event_name', '%recojo%')
                                ->orWhereLike('event_name', '%retiro%');
                            $priorTransit = fn ($query) => $query
                                ->selectRaw('1')
                                ->from('postal_events as prior')
                                ->whereColumn('prior.postal_package_id', 'postal_events.postal_package_id')
                                ->whereIn('prior.source_event', ['168', '2239', '2240', '2241', '240', '296', '4400', '4401'])
                                ->whereRaw('(prior.event_at < postal_events.event_at OR (prior.event_at = postal_events.event_at AND prior.id < postal_events.id))');

                            if ($category === 'En Camino') {
                                $classification->where($transitText);
                            } elseif ($category === 'Recibido Destino') {
                                $classification->where($destinationText)
                                    ->orWhereExists($priorTransit);
                            } else {
                                $classification->whereNotExists($priorTransit)
                                    ->where(function ($otherStage) use ($transitText, $destinationText): void {
                                        $otherStage->whereNull('event_name')
                                            ->orWhere(function ($withoutStage) use ($transitText, $destinationText): void {
                                                foreach (['%tránsito%', '%transito%', '%en camino%', '%destino%', '%oficina de entrega%', '%ventanilla%', '%recojo%', '%retiro%'] as $term) {
                                                    $withoutStage->whereRaw('LOWER(event_name) NOT LIKE ?', [$term]);
                                                }
                                            });
                                    });
                            }
                        });
                });
            }
            if ($uncodedEventNames !== []) {
                $matching->orWhereHas('latestEvent', function (Builder $events) use ($uncodedEventNames): void {
                    $events->whereIn('event_name', $uncodedEventNames)
                        ->where(function ($codes): void {
                            $codes->whereNull('source_event')
                                ->orWhereRaw("trim(source_event) = ''")
                                ->orWhereRaw("lower(trim(source_event)) = 'unknown'");
                        })
                        ->where(function (Builder $status): void {
                            $status->whereNull('postal_packages.current_event')
                                ->orWhereRaw("trim(postal_packages.current_event) = ''")
                                ->orWhereRaw("lower(trim(postal_packages.current_event)) in ('sin evento', 'sin eventos', 'no event')");
                        });
                });
            }
            if ($unclassified) {
                $matching->orWhereHas('latestEvent', function (Builder $events) use ($knownCodes): void {
                    $events->whereNotNull('source_event')
                        ->whereRaw("trim(source_event) <> ''")
                        ->whereRaw("lower(trim(source_event)) <> 'unknown'")
                        ->whereNotIn('source_event', $knownCodes);
                })->orWhere(function (Builder $withoutEvents): void {
                    $withoutEvents->whereDoesntHave('latestEvent')
                        ->where(function (Builder $status): void {
                            $status->whereNull('current_event')
                                ->orWhereRaw("trim(current_event) = ''")
                                ->orWhereRaw("lower(trim(current_event)) in ('sin evento', 'sin eventos', 'no event')");
                        });
                })->orWhereHas('latestEvent', function (Builder $events): void {
                    $events->where(function (Builder $codes): void {
                        $codes->whereNull('source_event')
                            ->orWhereRaw("trim(source_event) = ''")
                            ->orWhereRaw("lower(trim(source_event)) = 'unknown'");
                    })->where(function (Builder $status): void {
                        $status->whereNull('postal_packages.current_event')
                            ->orWhereRaw("trim(postal_packages.current_event) = ''")
                            ->orWhereRaw("lower(trim(postal_packages.current_event)) in ('sin evento', 'sin eventos', 'no event')");
                    })->where(function (Builder $name): void {
                        $name->whereNull('event_name')->orWhereRaw("trim(event_name) = ''");
                    });
                });
            }
        });
    }

    public function getSelectedPackageProperty(): ?PostalPackage
    {
        if (! $this->selectedPackageId) {
            return null;
        }

        $package = PostalPackage::query()
            ->whereKey($this->selectedPackageId)
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->with(['events' => fn ($query) => $query->orderByRaw('event_at IS NULL')->orderByDesc('event_at')->orderByDesc('id')])
            ->first();
        if (! $package) {
            return null;
        }

        $presenter = app(PostalEventPresenter::class);
        $package->setAttribute('display_status', $presenter->currentStatus($package));
        $package->setAttribute('display_category', $presenter->currentCategory($package));
        $package->events->each(function ($event) use ($package, $presenter): void {
            $event->setAttribute('display_name', $presenter->describeEvent($package->scope, $event));
            $event->setAttribute('display_category', $presenter->categoryForEvent($package, $event));
        });

        return $package;
    }

    public function openPackage(int $id): void
    {
        $this->selectedPackageId = PostalPackage::query()
            ->whereKey($id)
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->value('id');

        abort_if($this->selectedPackageId === null, 404);
    }

    #[Renderless]
    public function closePackage(): void
    {
        $this->selectedPackageId = null;
    }

    public function openSyncModal(): void
    {
        $this->syncError = null;
        $this->syncNotice = null;
        $this->syncModalOpen = true;
    }

    public function closeSyncModal(): void
    {
        $this->syncModalOpen = false;
    }

    public function clearTableFilters(): void
    {
        $this->trackingFilter = '';
        $this->senderFilter = '';
        $this->recipientFilter = '';
        $this->eventFilter = '';
        $this->filterFrom = '';
        $this->filterTo = '';
        $this->resetPage();
    }

    public function syncNow(PostalApiSyncService $sync): void
    {
        $this->validate([
            'scope' => ['required', 'in:nacional,internacional'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'force' => ['boolean'],
        ]);

        $this->syncError = null;
        $this->syncNotice = null;
        try {
            $result = $sync->sync($this->scope, 1, $this->from, $this->to, $this->force);
            if ($result['skipped'] ?? false) {
                $this->syncNotice = 'Ya hay datos locales para todos los días del rango; no se consultó nuevamente. Activa “Forzar actualización” para volver a consultar.';
            } else {
                $source = $this->scope === 'nacional' ? 'SIOP' : 'SITRA';
                $this->syncNotice = "{$source}: sincronización completada. Paquetes recibidos: ".($result['received'] ?? 0).'.';
            }
            $this->syncError = null;
            $this->resetPage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->syncNotice = null;
            $this->syncError = 'No se pudo sincronizar: '.$exception->getMessage();
        }
    }

    public function getPackageCountProperty(): int
    {
        return PostalPackage::query()
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->whereNotNull('updated_at_api')
            ->count();
    }

    public function getLastSyncAtProperty(): mixed
    {
        return PostalPackage::query()
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->max('last_synced_at');
    }

    public function render()
    {
        return view('livewire.postal-tracking-manager');
    }
}
