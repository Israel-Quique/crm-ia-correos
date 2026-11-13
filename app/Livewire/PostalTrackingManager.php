<?php

namespace App\Livewire;

use App\Models\PostalPackage;
use App\Services\PostalApiSyncService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra');

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
        if ($eventFilter !== '') {
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

        return $query->with('latestEvent')
            ->orderByDesc('updated_at_api')
            ->orderByDesc('id')
            ->paginate(14);
    }

    public function getSelectedPackageProperty(): ?PostalPackage
    {
        if (! $this->selectedPackageId) {
            return null;
        }

        return PostalPackage::query()
            ->whereKey($this->selectedPackageId)
            ->where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->with(['events' => fn ($query) => $query->orderByDesc('event_at')->orderByDesc('id')])
            ->first();
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
