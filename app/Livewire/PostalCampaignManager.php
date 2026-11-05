<?php
namespace App\Livewire;
use App\Jobs\SyncCdsDeclarationsForCampaign;
use App\Jobs\SendPostalCampaignMessages;
use App\Models\PostalPackage;
use App\Models\PostalCampaign;
use App\Models\PostalCampaignTemplate;
use App\Services\WhatsAppTestNumberRegistry;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostalCampaignManager extends Component
{
    use WithPagination;
    private ?Collection $basePackages = null;
    private ?Collection $filteredPackages = null;
    private ?array $eventSummaryCache = null;
    public string $phoneFilter = 'all';
    public bool $filtersApplied = false;
    public ?string $cdsSyncNotice = null;
    public bool $modalMode = false;
    public array $selectedEvents=[]; public string $eventFilter=''; public string $scope='nacional'; public string $source='all'; public string $from=''; public string $to=''; public int $limit=50; public string $campaignName='Campana postal'; public int $intervalSeconds=30; public bool $testMode=true; public string $template='Hola {recipient}, tu paquete {tracking_number} esta en estado {current_event}.'; public ?int $editingCampaignId=null; public string $editingCampaignName='';
    public function mount(string $scope='nacional', bool $modalMode = false): void
    {
        $this->scope = in_array($scope, ['nacional', 'internacional'], true) ? $scope : 'nacional';
        $this->source = $this->scope === 'nacional' ? 'siop' : 'sitra';
        $this->modalMode = $modalMode;
        if ($this->modalMode) {
            $this->testMode = false;
        }
    }

    public function updating($field): void
    {
        if (in_array($field, ['source', 'from', 'to', 'limit', 'phoneFilter', 'eventFilter', 'testMode'], true)) {
            $this->resetPackageCache();
            $this->resetPage();
        }
    }

    public function updated($property): void
    {
        if (! $this->modalMode || ! in_array($property, ['from', 'to', 'eventFilter', 'testMode', 'limit'], true)) {
            return;
        }

        if ($property === 'limit') {
            $maxLimit = $this->modalMode ? $this->availablePackageCount : 1000;
            $this->limit = min($maxLimit, max(0, (int) $this->limit));
            $this->filtersApplied = true;
            return;
        }

        if ($property === 'eventFilter') {
            $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        }

        $available = $this->availablePackageCount;
        $this->limit = $this->modalMode ? $available : min(1000, $available);

        $this->applyFilters();
    }

    private function resetPackageCache(): void
    {
        $this->basePackages = null;
        $this->filteredPackages = null;
        $this->eventSummaryCache = null;
    }

    public function applyFilters(): void
    {
        $maxLimit = $this->modalMode ? $this->availablePackageCount : 1000;
        $this->limit = min($maxLimit, max(0, (int) $this->limit));
        $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        $this->filtersApplied = true;
        $this->cdsSyncNotice = null;
        $this->resetPackageCache();
        $this->resetPage();

        if ($this->scope === 'internacional') {
            $trackings = $this->packageQuery()
                ->whereNull('cds_synced_at')
                ->orderBy('tracking_number')
                ->limit(min(1000, $this->limit))
                ->distinct()
                ->pluck('tracking_number')
                ->all();
            if ($trackings !== []) {
                SyncCdsDeclarationsForCampaign::dispatch($trackings)->onConnection('database');
                $this->cdsSyncNotice = 'La lista ya está disponible. Estamos actualizando los datos CDS en segundo plano; vuelve a abrir el modal en unos momentos para ver los cambios.';
            }
        }
    }
    public function updatedLimit($value): void
    {
        $maxLimit = $this->modalMode ? $this->availablePackageCount : 1000;
        $this->limit = min($maxLimit, max($this->modalMode ? 0 : 1, (int) $value));
        $this->resetPage();
    }
    public function updatedIntervalSeconds($value): void { $this->intervalSeconds = max(30, (int) $value); }
    public function clearFilters(): void { $this->selectedEvents=[]; $this->eventFilter=''; $this->phoneFilter='all'; $this->from=''; $this->to=''; $this->limit=50; $this->filtersApplied=false; $this->cdsSyncNotice=null; $this->resetPackageCache(); $this->resetPage(); }
    public function displayRecipient(PostalPackage $package): string
    {
        return $this->firstValue($package, ['cds_recipient', 'recipient', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.nombre', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.campos.RNm', 'raw_payload.destinatario.nombre']);
    }
    public function displaySender(PostalPackage $package): string
    {
        return $this->firstValue($package, ['cds_sender', 'sender', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.remitente_declarado.nombre', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.campos.SNm', 'raw_payload.remitente.nombre']);
    }
    public function displayPhone(PostalPackage $package): string
    {
        $paths = [
            'phone',
            'raw_payload.destinatario.telefono',
            'raw_payload.destinatario.celular',
            'raw_payload.destinatario.mobile',
            'raw_payload.destinatario.phone',
            'raw_payload.destinatario.telefono_celular',
            'raw_payload.destinatario.contacto.telefono',
            'raw_payload.destinatario.contacto.celular',
            'raw_payload.contacto.telefono',
            'raw_payload.contacto.celular',
            'raw_payload.telefono',
            'raw_payload.celular',
            'raw_payload.mobile',
            'raw_payload.phone',
            'raw_payload.telefono_destinatario',
            'raw_payload.celular_destinatario',
        ];
        if ($this->scope === 'internacional') array_unshift($paths, 'cds_phone', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.telefono', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.celular', 'raw_payload.declaracion_cds.objetos.0.declaraciones.0.campos.RTel');
        return $this->firstValue($package, $paths);
    }
    private function firstValue(PostalPackage $package, array $paths): string
    {
        foreach ($paths as $path) {
            $value = data_get($package, $path);
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return '—';
    }
    public function filterByEvent(string $event): void
    {
        $this->eventFilter = $this->selectedEvents === [$event] ? '' : $event;
        $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        if ($this->modalMode) {
            $this->limit = $this->availablePackageCount;
        }
        $this->applyFilters();
    }
    private function packageQuery()
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $phoneCondition = match ($driver) {
            'sqlite' => "coalesce(%s, '') glob '*[0-9]*'",
            'mysql', 'mariadb' => "coalesce(%s, '') regexp '[0-9]'",
            default => "coalesce(%s, '') ~ '[0-9]'",
        };
        $jsonPhoneExpressions = $this->jsonPhoneExpressions();
        if ($this->scope === 'nacional') $jsonPhoneExpressions = array_values(array_filter($jsonPhoneExpressions, fn (string $expression): bool => ! str_contains($expression, 'declaracion_cds')));

        $q = PostalPackage::where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->whereNotNull('tracking_number')
            ->whereRaw("trim(tracking_number) <> ''")
            ->where(function ($phoneQuery) use ($phoneCondition, $jsonPhoneExpressions) {
                $phoneQuery->whereRaw(sprintf($phoneCondition, 'phone'));
                if ($this->scope === 'internacional') {
                    $phoneQuery->orWhereRaw(sprintf($phoneCondition, 'cds_phone'));
                }
                foreach ($jsonPhoneExpressions as $expression) {
                    $phoneQuery->orWhereRaw(sprintf($phoneCondition, $expression));
                }
            });

        $dateExpression = 'COALESCE(postal_packages.updated_at_api, (SELECT MAX(postal_events.event_at) FROM postal_events WHERE postal_events.postal_package_id = postal_packages.id), postal_packages.updated_at)';
        if ($this->from) {
            $q->whereRaw("DATE({$dateExpression}) >= ?", [$this->from]);
        }
        if ($this->to) {
            $q->whereRaw("DATE({$dateExpression}) <= ?", [$this->to]);
        }

        return $q;
    }

    private function jsonPhoneExpressions(): array
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $paths = [
            'destinatario.telefono',
            'destinatario.celular',
            'destinatario.mobile',
            'destinatario.phone',
            'destinatario.telefono_celular',
            'destinatario.contacto.telefono',
            'destinatario.contacto.celular',
            'contacto.telefono',
            'contacto.celular',
            'telefono',
            'celular',
            'mobile',
            'phone',
            'telefono_destinatario',
            'celular_destinatario',
            'declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.telefono',
            'declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.celular',
            'declaracion_cds.objetos.0.declaraciones.0.campos.RTel',
        ];

        return array_map(function (string $path) use ($driver): string {
            if ($driver === 'pgsql') {
                return "raw_payload #>> '{".str_replace('.', ',', $path)."}'";
            }

            $jsonPath = '$.'.str_replace('.0.', '[0].', $path);
            return in_array($driver, ['mysql', 'mariadb'], true)
                ? "JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '{$jsonPath}'))"
                : "json_extract(raw_payload, '{$jsonPath}')";
        }, $paths);
    }
    private function basePackageCollection(): Collection
    {
        if ($this->basePackages !== null) {
            return $this->basePackages;
        }

        return $this->basePackages = $this->packageQuery()
            ->select([
                'id', 'source', 'scope', 'external_id', 'tracking_number', 'recipient',
                'phone', 'cds_recipient', 'cds_phone', 'current_event', 'destination_branch',
                'updated_at_api', 'updated_at',
            ])
            ->with(['latestEvent' => fn ($query) => $query->select([
                'postal_events.id', 'postal_events.postal_package_id', 'postal_events.source_event',
                'postal_events.event_name', 'postal_events.event_at', 'postal_events.raw_payload',
            ])])
            ->orderByDesc('updated_at_api')
            ->orderByDesc('id')
            ->get();
    }

    private function filteredPackageCollection(): Collection
    {
        if ($this->filteredPackages !== null) {
            return $this->filteredPackages;
        }

        $items = $this->basePackageCollection();
        if ($this->selectedEvents) {
            $items = $items->filter(fn ($package) => in_array($this->classifyPackage($package), $this->selectedEvents, true));
        }

        if ($this->testMode) {
            $allowedNumbers = array_fill_keys(app(WhatsAppTestNumberRegistry::class)->all(), true);
            $items = $items->filter(function (PostalPackage $package) use ($allowedNumbers): bool {
                $phone = preg_replace('/\D+/', '', $this->displayPhone($package)) ?? '';

                return $phone !== '' && isset($allowedNumbers[$phone]);
            });
        }

        $items = $items->take(max(1, $this->limit))->values();
        $this->loadRecipientPayloadFallbacks($items);

        return $this->filteredPackages = $items;
    }

    private function loadRecipientPayloadFallbacks(Collection $packages): void
    {
        $packagesNeedingFallback = $packages->filter(
            fn (PostalPackage $package): bool => (
                trim((string) $package->cds_recipient) === ''
                && trim((string) $package->recipient) === ''
            ) || (
                trim((string) $package->cds_phone) === ''
                && trim((string) $package->phone) === ''
            ),
        );
        if ($packagesNeedingFallback->isEmpty()) {
            return;
        }

        $payloads = PostalPackage::query()
            ->whereKey($packagesNeedingFallback->modelKeys())
            ->pluck('raw_payload', 'id');
        foreach ($packagesNeedingFallback as $package) {
            $package->setAttribute('raw_payload', $payloads->get($package->id));
        }
    }
    public function getPackagesProperty(){ $perPage=15; $page=$this->getPage(); $max=max(1,$this->limit); $items=$this->filteredPackageCollection()->take($max)->values(); return new LengthAwarePaginator($items->forPage($page,$perPage)->values(),$items->count(),$perPage,$page,['path'=>request()->url(),'pageName'=>'page']); }
    public function getEventSummaryProperty(): array
    {
        if ($this->eventSummaryCache !== null) {
            return $this->eventSummaryCache;
        }

        $labels = $this->scope === 'internacional'
            ? ['Llegada a Bolivia', 'Salida de Bolivia', 'Aduana', 'Distribución nacional', 'Última milla', 'Cartero', 'Ventanilla para recojo', 'Entregado', 'Importación detenida', 'Sin evento', 'Otros']
            : ['Recibido Origen', 'En Camino', 'Recibido Destino', 'Cartero', 'Ventanilla para recojo', 'Entregado', 'Cancelado', 'Sin evento', 'Otros'];
        $summary = array_fill_keys($labels, 0);

        $this->packageQuery()
            ->select([
                'id', 'source', 'scope', 'external_id', 'tracking_number', 'recipient',
                'phone', 'cds_recipient', 'cds_phone', 'current_event', 'destination_branch',
                'updated_at_api', 'updated_at',
            ])
            ->with(['latestEvent' => fn ($query) => $query->select([
                'postal_events.id', 'postal_events.postal_package_id', 'postal_events.source_event',
                'postal_events.event_name', 'postal_events.event_at', 'postal_events.raw_payload',
            ])])
            ->chunkById(250, function (Collection $packages) use (&$summary): void {
                foreach ($packages as $package) {
                    $label = $this->classifyPackage($package);
                    $summary[array_key_exists($label, $summary) ? $label : 'Otros']++;
                }
            });

        return $this->eventSummaryCache = $summary;
    }
    public function eventFilterLabel(string $event): string
    {
        return [
            'Recibido Origen' => 'Origen',
            'En Camino' => 'Camino',
            'Recibido Destino' => 'Llegó a destino',
            'Ventanilla para recojo' => 'Ventanilla',
        ][$event] ?? $event;
    }
    public function eventFilterColor(string $event): string
    {
        return [
            'Recibido Origen' => 'blue',
            'Llegada a Bolivia' => 'blue',
            'En Camino' => 'purple',
            'Salida de Bolivia' => 'cyan',
            'Recibido Destino' => 'cyan',
            'Distribución nacional' => 'cyan',
            'Ventanilla para recojo' => 'orange',
            'Cartero' => 'purple',
            'Última milla' => 'orange',
            'Aduana' => 'purple',
            'Entregado' => 'green',
            'Cancelado' => 'red',
            'Importación detenida' => 'red',
            'Sin evento' => 'gray',
            'Otros' => 'gray',
        ][$event] ?? 'gray';
    }
    public function getAvailablePackageCountProperty(): int
    {
        $summary = $this->eventSummary;

        return $this->eventFilter === ''
            ? array_sum($summary)
            : ($summary[$this->eventFilter] ?? 0);
    }
    public function classifyPackage(PostalPackage $package): string
    {
        $currentText = mb_strtolower((string) $package->current_event);
        if ($package->relationLoaded('latestEvent')) {
            $latest = $package->getRelation('latestEvent');
        } elseif ($package->relationLoaded('events')) {
            $latest = $package->events
                ->sortByDesc(fn ($event) => $event->event_at?->timestamp ?? 0)
                ->first();
        } else {
            $latest = $package->latestEvent;
        }
        $latestText = mb_strtolower((string) ($latest?->event_name ?? '').' '.json_encode($latest?->raw_payload ?? [], JSON_UNESCAPED_UNICODE));
        $statusText = trim($currentText.' '.$latestText);
        $code = (string) ($latest?->source_event ?? '');

        if ($this->isPickupReady($statusText) || ($this->scope === 'nacional' && $code === '312')) {
            return 'Ventanilla para recojo';
        }

        if ($this->isCourierDelivery($statusText) || ($this->scope === 'nacional' && in_array($code, ['315', '4409'], true))) {
            return 'Cartero';
        }

        if ($this->scope === 'internacional') {
            $directionText = mb_strtolower($currentText.' '.(string) ($latest?->event_name ?? ''));
            if (preg_match('/\bsalida\b|\b(?:despacho|despachad[oa])\s+(?:internacional|al\s+exterior)\b|\bexportaci[oó]n\b|\bremitid[oa]\s+al\s+exterior\b/iu', $directionText)) {
                return 'Salida de Bolivia';
            }
            if (preg_match('/\b(?:entrada|llegada)\b/iu', $directionText) && in_array($code, ['13', '14', '32', '35', '71', '72', '130', '131', '132', '133'], true)) {
                return 'Distribución nacional';
            }
            if (preg_match('/\b(?:llegada|arribo)\b|\b(?:entrada|ingreso|recepci[oó]n|recibid[oa])\s+(?:a|en)\s+(?:bolivia|el\s+pa[ií]s|oficina\s+de\s+cambio)\b|\b(?:entrada|ingreso)\s+al\s+pa[ií]s\b/iu', $directionText)) {
                return 'Llegada a Bolivia';
            }
            if (in_array($code, ['36', '39', '73', '74', '75'], true)) return 'Última milla';
            if ($code === '37') return 'Entregado';
            if (in_array($code, ['60', '76'], true)) return 'Importación detenida';
        }
        return $this->bucket((string) $package->current_event);
    }
    private function isPickupReady(string $event): bool
    {
        return preg_match('/\bventanilla\b|\boficina\s+de\s+entrega\b|\bentrega\s+en\s+oficina\b|\bpunto\s+de\s+entrega\b|\bsolicitud\s+de\s+recojo\b|\bret[eé]n(?:ido)?\s+en\s+punto\s+de\s+entrega\b|\blist[oa]\s+para\s+entregar\b|\bpendiente\s+de\s+(?:recojo|retiro)\b|\bdisponible\s+para\s+(?:entrega\s+en\s+oficina|recojo|retiro)\b|\bdevuelt[oa]\s+a\s+ventanilla\b/iu', $event) === 1;
    }

    private function isCourierDelivery(string $event): bool
    {
        return preg_match('/\bcartero\b|\breparto\b|\brepartidor\b|\bsalida\s+a\s+entrega\b|\bentrega\s+f[ií]sica\b|\bintento\s+fallido\s+de\s+entrega\b/iu', $event) === 1;
    }

    private function bucket(string $event): string
    {
        $value = mb_strtolower($event);

        if ($this->scope === 'internacional') {
            if (str_contains($value, 'aduana')) return 'Aduana';
            if (str_contains($value, 'distribuci') || str_contains($value, 'ubicacion nacional') || str_contains($value, 'ubicación nacional')) return 'Distribución nacional';
            if (str_contains($value, 'milla') || str_contains($value, 'repart')) return 'Última milla';
            if (str_contains($value, 'entreg')) return 'Entregado';
            if (str_contains($value, 'recibido del cliente') || str_contains($value, 'registrado')) return 'Recibido Origen';
            if (str_contains($value, 'saca interna') || str_contains($value, 'oficina de transito') || str_contains($value, 'enviado')) return 'En Camino';
            if (str_contains($value, 'detenid')) return 'Importación detenida';
            if (preg_match('/\bsalida\b|\bexportaci[oó]n\b/iu', $value)) return 'Salida de Bolivia';
            if (preg_match('/\b(llegada|arribo|entrada|ingreso)\b/iu', $value)) return 'Llegada a Bolivia';
        } else {
            if (str_contains($value, 'cartero')) return 'Cartero';
            if (str_contains($value, 'entreg')) return 'Entregado';
            if (str_contains($value, 'despach') || str_contains($value, 'en camino') || str_contains($value, 'transit')) return 'En Camino';
            if (str_contains($value, 'destino')) return 'Recibido Destino';
            if (str_contains($value, 'origen') || str_contains($value, 'recibid')) return 'Recibido Origen';
            if (str_contains($value, 'cancel')) return 'Cancelado';
        }

        return $event !== '' ? $event : 'Sin evento';
    }
    public function getTotalFilteredProperty(): int{return min($this->limit,$this->filteredPackageCollection()->count());}public function getVisibleProperty(): int{return$this->packages->count();}
    public function prepareCampaign(): void
    {
        if ($this->modalMode && ! $this->filtersApplied) {
            $this->applyFilters();
        }

        $this->intervalSeconds = max(30, (int) $this->intervalSeconds);
        $this->validate([
            'campaignName' => 'required|string|max:120',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'required|integer|min:0|max:'.($this->modalMode ? $this->availablePackageCount : 1000),
            'intervalSeconds' => 'required|integer|min:30',
            'template' => 'required|string',
        ]);
        $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        $packages = $this->filteredPackageCollection()->take(max(0, $this->limit));
        if ($packages->isEmpty()) {
            session()->flash(
                'campaignError',
                $this->testMode
                    ? 'No hay paquetes que coincidan con los filtros y cuyos teléfonos estén registrados en Números de prueba.'
                    : 'No hay paquetes con teléfono que coincidan con los filtros para crear la campaña.',
            );
            return;
        }

        $campaign = PostalCampaign::create([
            'user_id' => auth()->id(),
            'name' => $this->campaignName,
            'filters' => [
                'scope' => $this->scope,
                'source' => $this->source,
                'from' => $this->from ?: null,
                'to' => $this->to ?: null,
                'limit' => $this->limit,
            ],
            'events' => $this->selectedEvents,
            'template' => $this->template,
            'interval_seconds' => $this->intervalSeconds,
            'test_mode' => $this->testMode,
            'status' => 'prepared',
            'total' => $packages->count(),
            'pending' => $packages->count(),
        ]);

        $categoryTemplates = PostalCampaignTemplate::query()
            ->where('scope', $this->scope)
            ->whereNotNull('category')
            ->pluck('template', 'category');

        foreach ($packages as $package) {
            $phone = $this->displayPhone($package);
            $messageTemplate = $categoryTemplates->get($this->messageCategoryForPackage($package), $this->template);
            $campaign->messages()->create([
                'postal_package_id' => $package->id,
                'phone' => $phone === '—' ? null : $phone,
                'message' => strtr($messageTemplate, [
                    '{recipient}' => $this->displayRecipient($package) === '—' ? 'cliente' : $this->displayRecipient($package),
                    '{tracking_number}' => $package->tracking_number,
                    '{current_event}' => $package->current_event ?? 'actualizado',
                    '{campaign_name}' => $this->campaignName,
                    '{destination_branch}' => $package->destination_branch ?: 'la sucursal de destino',
                ]),
            ]);
        }

        session()->flash('campaignCreated', "Campaña preparada con {$packages->count()} paquetes.");
        if ($this->modalMode) {
            $this->dispatch('campaign-created', count: $packages->count());
            $this->dispatch('campaign-modal-close');
        }
    }

    private function messageCategoryForPackage(PostalPackage $package): string
    {
        $categoryByEvent = $this->scope === 'internacional'
            ? [
                'Llegada a Bolivia' => 'arrival_bolivia',
                'Salida de Bolivia' => 'departure_bolivia',
                'Aduana' => 'customs',
                'Distribución nacional' => 'national_distribution',
                'Última milla' => 'last_mile',
                'Ventanilla para recojo' => 'pickup',
                'Cartero' => 'courier',
                'Entregado' => 'delivered',
            ]
            : [
                'Recibido Origen' => 'origin',
                'En Camino' => 'transit',
                'Recibido Destino' => 'destination',
                'Ventanilla para recojo' => 'pickup',
                'Cartero' => 'courier',
                'Entregado' => 'delivered',
            ];

        return $categoryByEvent[$this->classifyPackage($package)] ?? 'other';
    }

    public function sendCampaign(int $id): void
    {
        $campaign = PostalCampaign::findOrFail($id);
        if (data_get($campaign->filters, 'scope') !== $this->scope) {
            abort(404);
        }

        if ($campaign->status === 'sending') {
            session()->flash('campaignError', 'Esta campaña ya se está enviando.');
            return;
        }

        if ($campaign->pending < 1) {
            session()->flash('campaignError', 'Esta campaña no tiene mensajes pendientes para enviar.');
            return;
        }
        $sentBeforeDispatch = $campaign->sent;
        $failedBeforeDispatch = $campaign->failed;
        $campaign->update(['status' => 'sending']);
        SendPostalCampaignMessages::dispatch($campaign->id);
        $campaign->refresh();
        session()->flash('campaignCreated', $campaign->status === 'sending'
            ? 'Envío iniciado. Los detalles se actualizarán conforme avance.'
            : 'Envío finalizado: '.($campaign->sent - $sentBeforeDispatch).' enviado(s), '.($campaign->failed - $failedBeforeDispatch).' fallido(s).');
        if ($campaign->failed > 0) {
            session()->flash('campaignError', $campaign->messages()
                ->where('status', 'failed')
                ->whereNotNull('error_message')
                ->latest('updated_at')
                ->value('error_message'));
        }
    }

    public function getCampaignHistoryProperty()
    {
        return PostalCampaign::query()
            ->where('filters->scope', $this->scope)
            ->withCount('messages')
            ->latest()
            ->limit(20)
            ->get();
    }

    public function editCampaign(int $id): void {$campaign=PostalCampaign::findOrFail($id);$this->editingCampaignId=$campaign->id;$this->editingCampaignName=$campaign->name;}
    public function saveCampaignEdit(): void {$this->validate(['editingCampaignName'=>'required|string|max:120']);PostalCampaign::whereKey($this->editingCampaignId)->update(['name'=>$this->editingCampaignName]);$this->editingCampaignId=null;$this->editingCampaignName='';}
    public function render()
    {
        return view($this->modalMode ? 'livewire.postal-campaign-creator-modal' : 'livewire.postal-campaign-manager-root');
    }
}
