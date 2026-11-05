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
                        {{ $this->availablePackageCount }} paquetes en {{ $eventFilter }} para el rango seleccionado
                    @else
                        {{ $this->availablePackageCount }} paquetes para el rango seleccionado
                    @endif
                </span>
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
                    @if($count > 0)
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
                <label>Cantidad máxima<input wire:model.live="limit" type="number" min="0" max="{{ $this->availablePackageCount }}" class="input"></label>
            </div>
        </section>

        <section class="card campaign-builder">
            <h3>Configuración de campaña</h3>
            <div class="campaign-builder-grid">
                <label>Nombre de la campaña<input wire:model="campaignName" class="input" placeholder="Campaña postal"></label>
                <label>Duración total (segundos)<input wire:model="intervalSeconds" type="number" min="30" class="input"><small>Mínimo 30 segundos.</small></label>
            </div>
            <div class="campaign-modal-actions">
                <button type="button" class="secondary-btn" onclick="this.closest('dialog').close()">Cerrar</button>
                <button type="submit" class="primary-btn" wire:loading.attr="disabled">Crear campaña</button>
            </div>
        </section>
    </form>
</div>
