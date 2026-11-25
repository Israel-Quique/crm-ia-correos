<?php
namespace App\Livewire;
use App\Jobs\SyncCdsDeclarationsForCampaign;
use App\Jobs\SendPostalCampaignMessages;
use App\Models\PostalPackage;
use App\Models\PostalCampaign;
use App\Models\PostalCampaignTemplate;
use App\Services\WhatsAppTestNumberRegistry;
use App\Services\PostalApiSyncService;
use App\Services\WhatsAppCloudApiService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostalCampaignManager extends Component
{
    use WithPagination;
    private ?Collection $basePackages = null;
    private ?Collection $filteredPackages = null;
    // Debe ser público para que Livewire lo conserve entre peticiones.
    public array $eventSummaryCache = [];
    public string $phoneFilter = 'all';
    public string $originDepartment = '';
    public string $destinationDepartment = '';
    public bool $filtersApplied = false;
    public ?string $cdsSyncNotice = null;
    public bool $modalMode = false;
    public array $selectedEvents=[]; public string $eventFilter=''; public string $scope='nacional'; public string $source='all'; public string $from=''; public string $to=''; public int $limit=50; public string $campaignName='Campana postal'; public int $intervalSeconds=30; public bool $testMode=true; public string $template='Hola {recipient}, tu paquete {tracking_number} esta en estado {current_event}.'; public ?int $editingCampaignId=null; public string $editingCampaignName='';
    public string $campaignType = 'operativa';
    public array $metaTemplates = [];
    public array $metaTemplateLanguages = [];
    public array $metaTemplateParameterCounts = [];
    public string $selectedMetaTemplate = '';

    public function loadMetaTemplates(WhatsAppCloudApiService $whatsapp): void
    {
        try {
            $approved = collect($whatsapp->listMessageTemplates())
                ->filter(fn (array $template): bool => strtoupper((string) ($template['status'] ?? '')) === 'APPROVED')
                ->filter(fn (array $template): bool => trim((string) ($template['name'] ?? '')) !== '');

            $this->metaTemplates = $approved->mapWithKeys(fn (array $template): array => [
                $template['name'] => $template['name'].' · '.($template['language'] ?? 'es'),
            ])->all();
            $this->metaTemplateLanguages = $approved->mapWithKeys(fn (array $template): array => [
                $template['name'] => $template['language'] ?? 'es',
            ])->all();
            $this->metaTemplateParameterCounts = $approved->mapWithKeys(function (array $template): array {
                $body = data_get(collect($template['components'] ?? [])->firstWhere('type', 'BODY'), 'text', '');
                preg_match_all('/\{\{(\d+)\}\}/', (string) $body, $matches);
                return [$template['name'] => count($matches[1] ?? [])];
            })->all();
        } catch (\Throwable $exception) {
            report($exception);
            $this->metaTemplates = [];
            $this->metaTemplateLanguages = [];
            $this->metaTemplateParameterCounts = [];
        }
    }

    public function updatedCampaignType(string $value): void
    {
        if ($value === 'promocional') {
            $this->loadMetaTemplates(app(WhatsAppCloudApiService::class));
        }
    }

    public function mount(string $scope='nacional', bool $modalMode = false): void
    {
        $this->scope = in_array($scope, ['nacional', 'internacional'], true) ? $scope : 'nacional';
        $this->source = $this->scope === 'nacional' ? 'siop' : 'sitra';
        $this->modalMode = $modalMode;
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        if ($this->modalMode) {
            $this->testMode = false;
        }
    }

    public function updating($field): void
    {
        if (in_array($field, ['source', 'from', 'to', 'limit', 'phoneFilter', 'testMode', 'originDepartment', 'destinationDepartment'], true)) {
            $this->resetPackageCache();
            $this->resetPage();
        }
    }

    public function updated($property): void
    {
        if (! $this->modalMode || ! in_array($property, ['from', 'to', 'eventFilter', 'testMode', 'limit', 'originDepartment', 'destinationDepartment'], true)) {
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
            $this->limit = $this->availablePackageCount;
        }

        $this->applyFilters($property === 'eventFilter');
    }

    private function resetPackageCache(bool $resetSummary = true, bool $resetBase = true): void
    {
        if ($resetBase) {
            $this->basePackages = null;
        }
        $this->filteredPackages = null;
        if ($resetSummary) {
            $this->eventSummaryCache = [];
        }
    }

    public function applyFilters(bool $preserveSummary = false): void
    {
        $maxLimit = $this->modalMode ? $this->availablePackageCount : 1000;
        $this->limit = $this->modalMode
            ? $maxLimit
            : min($maxLimit, max(0, (int) $this->limit));
        $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        $this->filtersApplied = true;
        $this->cdsSyncNotice = null;
        // El cambio de evento no modifica los conteos; conservar el resumen
        // evita recorrer todos los paquetes nuevamente.
        $this->resetPackageCache($preserveSummary, ! $preserveSummary);
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
        if ($this->modalMode) {
            $this->limit = $this->availablePackageCount;
        } else {
            $this->limit = min(1000, max(1, (int) $value));
        }
        $this->resetPage();
    }
    public function updatedIntervalSeconds($value): void { $this->intervalSeconds = max(30, (int) $value); }

    public function campaignStatusLabel(string $status): string
    {
        return match ($status) {
            'prepared' => 'Preparada',
            'sending' => 'Enviando',
            'paused' => 'Pausada',
            'partial' => 'Parcial',
            'sent' => 'Enviada',
            'failed' => 'Fallida',
            'cancelled' => 'Cancelada',
            'completed' => 'Completada',
            'draft' => 'Borrador',
            default => 'Estado no identificado',
        };
    }

    public function messageStatusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'read' => 'Visto',
            'failed' => 'Error al enviar',
            'sending' => 'Enviando',
            'pending' => 'Pendiente',
            'replied' => 'Respondió',
            'cancelled' => 'Cancelado',
            'received' => 'Recibido',
            default => 'Estado no identificado',
        };
    }

    public function clearFilters(): void { $this->selectedEvents=[]; $this->eventFilter=''; $this->phoneFilter='all'; $this->originDepartment=''; $this->destinationDepartment=''; $this->from=now()->startOfMonth()->format('Y-m-d'); $this->to=now()->format('Y-m-d'); $this->limit=50; $this->filtersApplied=false; $this->cdsSyncNotice=null; $this->resetPackageCache(); $this->resetPage(); }
    public function syncNow(PostalApiSyncService $sync): void
    {
        try {
            $result = $sync->sync($this->scope, 1);
            $this->resetPackageCache();
            $this->cdsSyncNotice = 'Sincronización completada: '.($result['packages'] ?? $result['count'] ?? 0).' paquetes procesados.';
        } catch (\Throwable $e) {
            report($e);
            $this->cdsSyncNotice = 'No se pudo completar la sincronización: '.$e->getMessage();
        }
    }

    public function getLatestSyncAtProperty(): ?\Illuminate\Support\Carbon
    {
        $latestSyncAt = PostalPackage::query()
            ->where('scope', $this->scope)
            ->where('source', $this->source)
            ->max('last_synced_at');

        return $latestSyncAt ? \Illuminate\Support\Carbon::parse($latestSyncAt) : null;
    }

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
        $this->limit = $this->availablePackageCount;
        $this->applyFilters(true);
    }
    private function packageQuery()
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $phoneCondition = match ($driver) {
            'mysql', 'mariadb' => "length(trim(coalesce(%s, ''))) >= 7 AND coalesce(%s, '') regexp '[0-9]'",
            default => "length(trim(coalesce(%s, ''))) >= 7 AND coalesce(%s, '') ~ '[0-9]'",
        };
        $jsonPhoneExpressions = $this->jsonPhoneExpressions();
        if ($this->scope === 'nacional') $jsonPhoneExpressions = array_values(array_filter($jsonPhoneExpressions, fn (string $expression): bool => ! str_contains($expression, 'declaracion_cds')));

        $q = PostalPackage::where('scope', $this->scope)
            ->where('source', $this->scope === 'nacional' ? 'siop' : 'sitra')
            ->whereNotNull('tracking_number')
            ->whereRaw("trim(tracking_number) <> ''")
            ->where(function ($phoneQuery) use ($phoneCondition, $jsonPhoneExpressions) {
                $phoneQuery->whereRaw(sprintf($phoneCondition, 'phone', 'phone'));
                if ($this->scope === 'internacional') {
                    $phoneQuery->orWhereRaw(sprintf($phoneCondition, 'cds_phone', 'cds_phone'));
                }
                foreach ($jsonPhoneExpressions as $expression) {
                    $phoneQuery->orWhereRaw(sprintf($phoneCondition, $expression, $expression));
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

    private function departmentPayloadPaths(): array
    {
        return [
            'origin' => [
                'departamento_origen', 'origin_department', 'originDepartment',
                'origen', 'origen.departamento', 'origen.departamento.nombre', 'origen.departamento_nombre', 'origen.nombre_departamento', 'origen.nombre',
                'origin.department', 'origin.department.name',
                'remitente.departamento', 'remitente.departamento.nombre', 'remitente.departamento_nombre',
                'remitente.direccion.departamento', 'remitente.localidad.departamento', 'remitente.ciudad',
                'oficina_origen.departamento', 'oficina_origen.departamento.nombre', 'oficina_origen.nombre',
                'sucursal_origen.departamento', 'sucursal_origen.nombre', 'direccion_origen.departamento',
                'remitente_declarado.departamento', 'declaracion_cds.objetos.0.declaraciones.0.remitente_declarado.departamento',
            ],
            'destination' => [
                'departamento_destino', 'destination_department', 'destinationDepartment',
                'destino', 'destino.departamento', 'destino.departamento.nombre', 'destino.departamento_nombre', 'destino.nombre_departamento',
                'destination.department', 'destination.department.name',
                'destinatario.departamento', 'destinatario.departamento.nombre', 'destinatario.departamento_nombre',
                'destinatario.direccion.departamento', 'destinatario.localidad.departamento', 'destinatario.ciudad',
                'oficina_destino.departamento', 'oficina_destino.departamento.nombre', 'oficina_destino.nombre',
                'sucursal_destino.departamento', 'sucursal_destino.nombre', 'direccion_destino.departamento',
                'destinatario_declarado.departamento', 'declaracion_cds.objetos.0.declaraciones.0.destinatario_declarado.departamento',
            ],
        ];
    }

    private function jsonTextExpression(string $path): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            return "raw_payload #>> '{".str_replace('.', ',', $path)."}'";
        }

        $jsonPath = '$.'.str_replace('.0.', '[0].', $path);
        return match ($driver) {
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '{$jsonPath}'))",
            default => throw new \LogicException("Unsupported database driver [{$driver}] for postal package JSON extraction."),
        };
    }

    private function departmentForPackage(PostalPackage $package, string $kind): string
    {
        $paths = $this->departmentPayloadPaths()[$kind];
        foreach ($paths as $index => $path) {
            $department = $this->matchDepartment((string) $package->getAttribute("{$kind}_department_{$index}"));
            if ($department !== '') {
                return $department;
            }
        }

        if ($kind === 'destination') {
            return $this->matchDepartment((string) $package->destination_branch);
        }

        return '';
    }

    private function matchDepartment(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        foreach ($this->departments() as $department) {
            $normalized = mb_strtolower($department, 'UTF-8');
            $normalized = strtr($normalized, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
            if (preg_match('/(?<![a-z])'.preg_quote($normalized, '/').'(?![a-z])/u', $value)) {
                return $department;
            }
        }

        return '';
    }

    public function departments(): array
    {
        return ['Beni', 'Chuquisaca', 'Cochabamba', 'La Paz', 'Oruro', 'Pando', 'Potosí', 'Santa Cruz', 'Tarija'];
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
            return match ($driver) {
                'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '{$jsonPath}'))",
                default => throw new \LogicException("Unsupported database driver [{$driver}] for postal package JSON extraction."),
            };
        }, $paths);
    }
    private function basePackageCollection(): Collection
    {
        if ($this->basePackages !== null) {
            return $this->basePackages;
        }

        $query = $this->packageQuery()
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
            ->orderByDesc('id');
        $departmentPaths = $this->departmentPayloadPaths();
        foreach ([
            'origin' => $this->originDepartment,
            'destination' => $this->destinationDepartment,
        ] as $kind => $department) {
            if ($department === '') {
                continue;
            }

            $paths = $departmentPaths[$kind];
            foreach ($paths as $index => $path) {
                $query->addSelect(\Illuminate\Support\Facades\DB::raw(
                    $this->jsonTextExpression($path)." as {$kind}_department_{$index}",
                ));
            }
        }

        return $this->basePackages = $query->get();
    }

    private function departmentFilteredPackages(): Collection
    {
        return $this->basePackageCollection()
            ->filter(function (PostalPackage $package): bool {
                $originMatches = $this->originDepartment === ''
                    || $this->departmentForPackage($package, 'origin') === $this->originDepartment;
                $destinationMatches = $this->destinationDepartment === ''
                    || $this->departmentForPackage($package, 'destination') === $this->destinationDepartment;

                return $originMatches && $destinationMatches;
            })
            ->values();
    }

    private function filteredPackageCollection(): Collection
    {
        if ($this->filteredPackages !== null) {
            return $this->filteredPackages;
        }

        $items = $this->departmentFilteredPackages();
        if ($this->selectedEvents) {
            $items = $items->filter(function (PostalPackage $package): bool {
                $classification = $this->classifyPackage($package);
                if ($this->modalMode && $this->scope === 'internacional') {
                    $classification = match ($classification) {
                        'Ventanilla para recojo' => 'Ventanilla',
                        'Cartero' => 'Cartero',
                        default => 'Otros',
                    };
                }

                return in_array($classification, $this->selectedEvents, true);
            });
        }

        if ($this->testMode) {
            $allowedNumbers = array_fill_keys(app(WhatsAppTestNumberRegistry::class)->all(), true);
            $items = $items->filter(function (PostalPackage $package) use ($allowedNumbers): bool {
                $phone = preg_replace('/\D+/', '', $this->displayPhone($package)) ?? '';

                return $phone !== '' && isset($allowedNumbers[$phone]);
            });
        }

        if (! $this->modalMode) {
            $items = $items->take(max(1, $this->limit));
        }
        $items = $items->values();
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
    public function getPackagesProperty(){ $perPage=15; $page=$this->getPage(); $items=$this->filteredPackageCollection(); if (! $this->modalMode) $items=$items->take(max(1,$this->limit)); $items=$items->values(); return new LengthAwarePaginator($items->forPage($page,$perPage)->values(),$items->count(),$perPage,$page,['path'=>request()->url(),'pageName'=>'page']); }
    public function getEventSummaryProperty(): array
    {
        if ($this->eventSummaryCache !== []) {
            return $this->eventSummaryCache;
        }

        if ($this->modalMode && $this->scope === 'internacional') {
            $ventanilla = 0;
            $cartero = 0;
            $other = 0;
            foreach ($this->departmentFilteredPackages() as $package) {
                match ($this->classifyPackage($package)) {
                    'Ventanilla para recojo' => $ventanilla++,
                    'Cartero' => $cartero++,
                    default => $other++,
                };
            }

            return $this->eventSummaryCache = [
                'Ventanilla' => $ventanilla,
                'Cartero' => $cartero,
                'Otros' => $other,
            ];
        }

        $labels = $this->scope === 'internacional'
            ? ['Llegada a Bolivia', 'Salida de Bolivia', 'Aduana', 'Distribución nacional', 'Última milla', 'Cartero', 'Ventanilla para recojo', 'Entregado', 'Importación detenida', 'Sin evento', 'Otros']
            : ['Recibido Origen', 'En Camino', 'Cartero', 'Ventanilla para recojo', 'Entregado', 'Sin evento', 'Otros'];
        $summary = array_fill_keys($labels, 0);

        // Reutilizar la colección que también usa el filtro evita ejecutar una
        // segunda consulta completa al seleccionar un evento.
        foreach ($this->departmentFilteredPackages() as $package) {
            $label = $this->classifyPackage($package);
            $summary[array_key_exists($label, $summary) ? $label : 'Otros']++;
        }

        return $this->eventSummaryCache = $summary;
    }
    public function eventFilterLabel(string $event): string
    {
        return [
            'Recibido Origen' => 'Origen',
            'En Camino' => 'Camino',
            'Recibido Destino' => 'Llegó a destino',
            'Ventanilla para recojo' => 'Ventanilla',
            'Ventanilla' => 'Ventanilla',
            'Internacional' => 'Internacional',
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
            if (str_contains($value, 'destino')) return 'Ventanilla para recojo';
            if (str_contains($value, 'origen') || str_contains($value, 'recibid')) return 'Recibido Origen';
            if (str_contains($value, 'cancel')) return 'Cancelado';
        }

        return $event !== '' ? $event : 'Sin evento';
    }
    public function getTotalFilteredProperty(): int{return $this->modalMode ? $this->filteredPackageCollection()->count() : min($this->limit,$this->filteredPackageCollection()->count());}public function getVisibleProperty(): int{return$this->packages->count();}
    public function prepareCampaign(): void
    {
        if ($this->modalMode && ! $this->filtersApplied) {
            $this->applyFilters();
        }

        $this->intervalSeconds = max(30, (int) $this->intervalSeconds);
        $rules = [
            'campaignName' => 'required|string|max:120',
            'campaignType' => 'required|in:operativa,promocional',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'intervalSeconds' => 'required|integer|min:30',
            'template' => 'required|string',
        ];
        if (! $this->modalMode) {
            $rules['limit'] = 'required|integer|min:0|max:1000';
        }
        $this->validate($rules);
        if ($this->campaignType === 'promocional') {
            $this->validate(['selectedMetaTemplate' => 'required|string|max:512']);
            $language = $this->metaTemplateLanguages[$this->selectedMetaTemplate] ?? 'es';
            $parameterCount = $this->metaTemplateParameterCounts[$this->selectedMetaTemplate] ?? 0;
            $this->template = 'meta:'.$this->selectedMetaTemplate.'|'.$language.'|'.$parameterCount;
        }
        $this->selectedEvents = $this->eventFilter === '' ? [] : [$this->eventFilter];
        $packages = $this->filteredPackageCollection();
        if (! $this->modalMode) {
            $packages = $packages->take(max(0, $this->limit));
        }
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
            'campaign_type' => $this->campaignType,
            'filters' => [
                'scope' => $this->scope,
                'source' => $this->source,
                'from' => $this->from ?: null,
                'to' => $this->to ?: null,
                'limit' => $this->modalMode ? $packages->count() : $this->limit,
                'origin_department' => $this->originDepartment ?: null,
                'destination_department' => $this->destinationDepartment ?: null,
            ],
            'events' => $this->selectedEvents,
            'template' => $this->template,
            'interval_seconds' => $this->intervalSeconds,
            'test_mode' => $this->testMode,
            'status' => $this->modalMode ? 'sending' : 'prepared',
            'total' => $packages->count(),
            'pending' => $packages->count(),
        ]);
        \App\Models\AuditLog::create(['user_id'=>auth()->id(),'action'=>'Campaña creada','category'=>'campañas','description'=>'Se creó la campaña '.$campaign->name,'metadata'=>['campaign_id'=>$campaign->id],'ip_address'=>request()->ip()]);

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

        if ($this->modalMode) {
            session()->flash('campaignCreated', "Campaña creada y envío automático iniciado para {$packages->count()} paquetes.");
            $this->dispatch('campaign-created', count: $packages->count());
            $this->dispatch('campaign-modal-close');
        } else {
            session()->flash('campaignCreated', "Campaña preparada con {$packages->count()} paquetes.");
        }
    }

    private function messageCategoryForPackage(PostalPackage $package): string
    {
        $categoryByEvent = $this->scope === 'internacional'
            ? [
                'Ventanilla para recojo' => 'pickup',
            ]
            : [
                'Recibido Origen' => 'origin',
                'En Camino' => 'transit',
                'Recibido Destino' => 'destination',
                'Ventanilla para recojo' => 'pickup',
                'Cartero' => 'courier',
                'Entregado' => 'delivered',
            ];

        return $categoryByEvent[$this->classifyPackage($package)]
            ?? ($this->scope === 'internacional' ? 'international' : 'other');
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
    public function placeholder(array $params = []): string
    {
        return '<div class="registry-creator-loading" role="status" aria-live="polite"><span class="registry-creator-spinner" aria-hidden="true"></span><span>Cargando paquetes y filtros...</span></div>';
    }

    public function render()
    {
        return view($this->modalMode ? 'livewire.postal-campaign-creator-modal' : 'livewire.postal-campaign-manager-root');
    }
}
