<div class="postal-campaign-creator">
    @if (session()->has('campaignError'))
        <div class="card" role="alert">{{ session('campaignError') }}</div>
    @endif
    @if ($cdsSyncNotice)
        <div class="card" role="status">{{ $cdsSyncNotice }}</div>
    @endif

    <form wire:submit="prepareCampaign">
        <section class="card campaign-filters">
            <div class="card-title">
                <div>
                    <h3>Filtrar paquetes {{ strtoupper($source) }}</h3>
                    <small class="filter-help">Solo se incluyen paquetes con teléfono.</small>
                </div>
                <span>
                    @if($eventFilter !== '')
                        {{ $this->availablePackageCount }} paquetes en {{ $this->eventFilterLabel($eventFilter) }} para el rango seleccionado
                    @else
                        {{ $this->availablePackageCount }} paquetes para el rango seleccionado
                    @endif
                </span>@if(!$this->latestSyncAt || !$this->latestSyncAt->isSameMonth(now()))<button type="button" class="secondary-btn sync-now-button" wire:click="syncNow" wire:loading.attr="disabled"><span wire:loading.remove wire:target="syncNow">↻ Sincronizar ahora</span><span wire:loading wire:target="syncNow">⟳ Sincronizando...</span></button>@else<span class="sync-completed-label">✓ Sincronizado este mes · {{ $this->latestSyncAt->format('d/m/Y H:i') }}</span>@endif
            </div>
            <div class="campaign-event-buttons" role="group" aria-label="Eventos {{ $scope === 'nacional' ? 'nacionales' : 'internacionales' }}">
                <span class="campaign-event-buttons-label">Eventos {{ $scope === 'nacional' ? 'nacionales' : 'internacionales' }}</span>
                <button
                    type="button"
                    class="secondary-btn campaign-event-button {{ $eventFilter === '' ? 'is-active' : '' }}"
                    wire:click="filterByEvent('')"
                    aria-pressed="{{ $eventFilter === '' ? 'true' : 'false' }}"
                >Todos ({{ array_sum($this->eventSummary) }})</button>
                @foreach($this->eventSummary as $event => $count)
                    @if($count > 0 && !in_array($event, ['Sin evento', 'Otros'], true) && !($scope === 'nacional' && $event === 'Entregado'))
                        <button
                            type="button"
                            class="secondary-btn campaign-event-button {{ $eventFilter === $event ? 'is-active' : '' }}"
                            wire:click="filterByEvent(@js($event))"
                            aria-pressed="{{ $eventFilter === $event ? 'true' : 'false' }}"
                        >{{ $this->eventFilterLabel($event) }} ({{ $count }})</button>
                    @endif
                @endforeach
            </div>
            <div class="filter-grid">
                <label>Desde<input wire:model.live="from" type="date" class="input"></label>
                <label>Hasta<input wire:model.live="to" type="date" class="input"></label>
                <label>Departamento<select wire:model.live="destinationDepartment" class="input"><option value="">Todos los departamentos</option>@foreach($this->departments() as $department)<option value="{{ $department }}">{{ $department }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="card campaign-builder">
            <h3>Configuración de campaña</h3>
            <div class="campaign-builder-grid">
                <label>Nombre de la campaña<input wire:model="campaignName" class="input" placeholder="Campaña postal"></label>
                <label>Tipo de campaña<select wire:model.live="campaignType" class="input"><option value="operativa">Operativa</option><option value="promocional">Promocional</option></select></label>                @if($campaignType === 'promocional')
                    <label>Plantilla aprobada de Meta
                        <select wire:model="selectedMetaTemplate" class="input" required>
                            <option value="">Selecciona una plantilla</option>
                            @foreach($metaTemplates as $templateName => $templateLabel)
                                <option value="{{ $templateName }}">{{ $templateLabel }}</option>
                            @endforeach
                        </select>
                        @if(count($metaTemplates) === 0)<small>No se encontraron plantillas aprobadas de Meta.</small>@endif
                    </label>
                @endif
                <label>Duración total (segundos)<input wire:model="intervalSeconds" type="number" min="30" class="input"><small>Mínimo 30 segundos.</small></label>
            </div>
            <div class="campaign-modal-actions">
                <button type="button" class="secondary-btn" wire:loading.attr="disabled" wire:target="prepareCampaign" onclick="this.closest('dialog').close()">Cerrar</button>
                <button type="submit" class="primary-btn campaign-submit-button" wire:loading.attr="disabled" wire:target="prepareCampaign">
                    <span wire:loading.remove wire:target="prepareCampaign">Crear y enviar</span>
                    <span class="campaign-loading-label" wire:loading wire:target="prepareCampaign" role="status" aria-live="polite">
                        <span class="campaign-message-loader" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M4 5.75A2.75 2.75 0 0 1 6.75 3h10.5A2.75 2.75 0 0 1 20 5.75v6.5A2.75 2.75 0 0 1 17.25 15H12l-4.2 3.25V15h-1.05A2.75 2.75 0 0 1 4 12.25v-6.5Z"/><path d="m8 8 4 3 4-3"/></svg>
                            <i></i>
                        </span>
                        Preparando envío...
                    </span>
                </button>
            </div>
        </section>
    </form>
    <div class="campaign-loading-overlay" wire:loading wire:target="prepareCampaign" role="status" aria-live="polite">
        <div class="campaign-loading-card">
            <span class="campaign-message-loader campaign-message-loader-large" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M4 5.75A2.75 2.75 0 0 1 6.75 3h10.5A2.75 2.75 0 0 1 20 5.75v6.5A2.75 2.75 0 0 1 17.25 15H12l-4.2 3.25V15h-1.05A2.75 2.75 0 0 1 4 12.25v-6.5Z"/><path d="m8 8 4 3 4-3"/></svg>
                <i></i>
            </span>
            <strong>Programando envío automático</strong>
            <small>Estamos creando la campaña y programando sus mensajes.</small>
        </div>
    </div>
</div>
<style>
    .sync-now-button{min-width:165px}.sync-now-button span:last-child{animation:sync-spin 1s linear infinite}
    .campaign-submit-button{min-width:164px;display:inline-flex;justify-content:center;align-items:center;gap:8px}
    .campaign-loading-label{display:inline-flex;align-items:center;gap:8px}
    .campaign-message-loader{position:relative;width:19px;height:19px;display:inline-grid;place-items:center}
    .campaign-message-loader svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
    .campaign-message-loader i{position:absolute;inset:-2px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:campaign-loader-spin .8s linear infinite}
    .postal-campaign-creator{position:relative}
    .campaign-loading-overlay{position:absolute;inset:0;z-index:10;display:grid;place-items:center;border-radius:12px;background:rgba(7,18,37,.78);backdrop-filter:blur(2px);color:#f8fafc}
    .campaign-loading-card{display:flex;align-items:center;flex-direction:column;gap:8px;padding:22px 28px;text-align:center}
    .campaign-loading-card strong{font-size:15px}
    .campaign-loading-card small{color:#b9c8da;font-size:12px}
    .campaign-message-loader-large{width:48px;height:48px;margin-bottom:4px}
    .campaign-message-loader-large svg{width:32px;height:32px;stroke-width:1.5}
    .campaign-message-loader-large i{inset:-3px;border-width:3px}
    @keyframes campaign-loader-spin{to{transform:rotate(360deg)}}
    @keyframes sync-spin{to{opacity:.45}}
    @media(prefers-reduced-motion:reduce){.campaign-message-loader i{animation:none}}
</style>
