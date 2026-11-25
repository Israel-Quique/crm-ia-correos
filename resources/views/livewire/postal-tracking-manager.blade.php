<div class="content operations-page">
    <div class="welcome">
        <div><h1>Rastreo postal</h1><p>Consulta y sincroniza paquetes nacionales e internacionales desde un solo lugar.</p></div>
        <div class="tracking-header-actions"><span class="result-count">{{ number_format($this->packageCount) }} paquetes {{ $scope }}</span><button class="primary-btn" type="button" wire:click="openSyncModal">↻ Sincronizar {{ $scope }}</button></div>
    </div>
    <section class="tracking-scope-tabs" role="tablist" aria-label="Ámbito de rastreo">
        <button type="button" role="tab" aria-selected="{{ $scope === 'nacional' ? 'true' : 'false' }}" class="{{ $scope === 'nacional' ? 'is-active' : '' }}" wire:click="$set('scope', 'nacional')">Nacional <small>SIOP</small></button>
        <button type="button" role="tab" aria-selected="{{ $scope === 'internacional' ? 'true' : 'false' }}" class="{{ $scope === 'internacional' ? 'is-active' : '' }}" wire:click="$set('scope', 'internacional')">Internacional <small>SITRA</small></button>
    </section>
    <section class="card tracking-list-card">
        <div class="card-title"><div><h2>Paquetes {{ $scope }}</h2><p>Selecciona una guía para consultar el historial de eventos.</p><p class="tracking-scope-note">Clasificación basada solo en códigos de {{ $scope === 'nacional' ? 'SIOP (nacional)' : 'SITRA (internacional)' }}. Los paquetes de ambos sistemas se mantienen separados.</p></div><span class="result-count">{{ number_format($this->packages->total()) }} resultados</span></div>
        <div class="tracking-table-filters" aria-label="Filtros de paquetes">
            <label>Guía<input class="input" type="search" wire:model.live.debounce.300ms="trackingFilter" placeholder="Buscar guía"></label>
            <label>Remitente<input class="input" type="search" wire:model.live.debounce.300ms="senderFilter" placeholder="Buscar remitente"></label>
            <label>Destinatario<input class="input" type="search" wire:model.live.debounce.300ms="recipientFilter" placeholder="Buscar destinatario"></label>
            <label>Clasificación del evento ({{ $scope === 'nacional' ? 'SIOP' : 'SITRA' }})<select class="input" wire:model.live="eventFilter"><option value="">Todos los eventos</option>@foreach($this->eventOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Actualizado desde<input class="input" type="date" wire:model.live="filterFrom"></label>
            <label>Actualizado hasta<input class="input" type="date" wire:model.live="filterTo"></label>
            <button class="secondary-btn" type="button" wire:click="clearTableFilters">Limpiar filtros</button>
        </div>
        <div class="table-wrap"><table class="operations-table tracking-table">
            <thead><tr><th>Guía</th><th>Remitente</th><th>Destinatario</th><th>Último evento</th><th>Fecha actualización</th></tr></thead>
            <tbody>
            @forelse($this->packages as $package)
                @php($latestEvent = $package->latestEvent)
                <tr class="tracking-row" wire:key="tracking-package-{{ $package->id }}" wire:click="openPackage({{ $package->id }})" tabindex="0" role="button" aria-label="Ver eventos de {{ $package->tracking_number }}">
                    <td>{{ $package->tracking_number }}</td><td>{{ $package->sender ?: '—' }}</td><td>{{ $package->recipient ?: '—' }}</td>
                    <td><strong class="tracking-event-category">{{ $package->display_category ?: 'Sin movimientos o sin clasificación confiable' }}</strong><br>{{ $package->display_status ?: 'Aún no hay movimientos registrados.' }}</td>
                    <td>{{ ($latestEvent?->event_at ?: $package->updated_at_api)?->format('d/m/Y H:i') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-row">No se encontraron paquetes para este ámbito y búsqueda.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="tracking-pagination">
            <span>Mostrando {{ $this->packages->firstItem() ?? 0 }}–{{ $this->packages->lastItem() ?? 0 }} de {{ number_format($this->packages->total()) }} paquetes</span>
            @if($this->packages->hasPages())
                <nav aria-label="Paginación de paquetes">
                    <button class="secondary-btn" type="button" wire:click="previousPage('page')" @disabled($this->packages->onFirstPage())>Anterior</button>
                    <span>Página {{ $this->packages->currentPage() }} de {{ $this->packages->lastPage() }}</span>
                    <button class="secondary-btn" type="button" wire:click="nextPage('page')" @disabled(!$this->packages->hasMorePages())>Siguiente</button>
                </nav>
            @endif
        </div>
    </section>
    @if($syncModalOpen)
        <div class="tracking-overlay tracking-sync-overlay" wire:key="tracking-sync-modal-{{ $scope }}">
            <section class="card tracking-sync-modal" role="dialog" aria-modal="true" aria-labelledby="tracking-sync-title">
                <div class="modal-head"><div><h2 id="tracking-sync-title">Sincronizar {{ $scope }}</h2><p>Fuente: {{ $scope === 'nacional' ? 'SIOP' : 'SITRA' }} · Última sincronización: {{ $this->lastSyncAt ? \Illuminate\Support\Carbon::parse($this->lastSyncAt)->format('d/m/Y H:i') : 'Sin sincronizaciones' }}</p></div><button class="modal-close" type="button" wire:click="closeSyncModal" wire:loading.attr="disabled" wire:target="syncNow" aria-label="Cerrar">×</button></div>
                <form class="tracking-sync-form" wire:submit="syncNow">
                    <label>Desde<input class="input" type="date" wire:model="from" required></label>
                    <label>Hasta<input class="input" type="date" wire:model="to" required></label>
                    <label class="force-sync"><input type="checkbox" wire:model="force"> Forzar aunque ya existan datos</label>
                    @error('from')<small class="validation-error">{{ $message }}</small>@enderror
                    @error('to')<small class="validation-error">{{ $message }}</small>@enderror
                    <div class="tracking-sync-actions"><button class="secondary-btn" type="button" wire:click="closeSyncModal" wire:loading.attr="disabled" wire:target="syncNow">Cancelar</button><button class="primary-btn" type="submit" wire:loading.attr="disabled" wire:target="syncNow"><span wire:loading.remove wire:target="syncNow">Iniciar sincronización</span><span wire:loading wire:target="syncNow">Sincronizando…</span></button></div>
                </form>
                <div class="tracking-sync-progress" wire:loading.flex wire:target="syncNow" role="status" aria-live="polite"><span class="tracking-sync-spinner" aria-hidden="true"></span><strong>Sincronizando {{ $scope === 'nacional' ? 'SIOP' : 'SITRA' }}…</strong><small>La ventana permanecerá abierta hasta que termine el proceso.</small></div>
                @if($syncNotice)<div class="operation-alert success" role="status">{{ $syncNotice }}</div>@endif
                @if($syncError)<div class="operation-alert error" role="alert">{{ $syncError }}</div>@endif
            </section>
        </div>
    @endif
    @if($selectedPackage = $this->selectedPackage)
        <div class="tracking-people"><strong>Remitente:</strong> {{ $selectedPackage->sender ?: 'No registrado' }} · <strong>Destinatario:</strong> {{ $selectedPackage->recipient ?: 'No registrado' }}</div>
        <div class="tracking-overlay" wire:key="tracking-detail-{{ $selectedPackage->id }}" x-data="{ open: true }" x-show="open">
            <section class="card tracking-detail" role="dialog" aria-modal="true" aria-labelledby="tracking-detail-title">
                <div class="modal-head"><div><h2 id="tracking-detail-title">{{ $selectedPackage->tracking_number }}</h2><p>{{ $selectedPackage->sender ?: 'Remitente desconocido' }} → {{ $selectedPackage->recipient ?: 'Destinatario desconocido' }}</p></div><button class="modal-close" type="button" wire:click="closePackage" x-on:click="open = false" aria-label="Cerrar">×</button></div>
                <div class="tracking-detail-summary"><span><strong>Clasificación:</strong> {{ $selectedPackage->display_category ?: 'Sin movimientos o sin clasificación confiable' }}</span><span><strong>Estado del envío:</strong> {{ $selectedPackage->display_status ?: 'Aún no hay movimientos registrados.' }}</span><span><strong>Tipo de envío:</strong> {{ $selectedPackage->scope === 'nacional' ? 'Nacional' : 'Internacional' }}</span><span><strong>Fuente de eventos:</strong> {{ $selectedPackage->scope === 'nacional' ? 'SIOP' : 'SITRA' }}</span>@if($selectedPackage->destination_branch)<span><strong>Oficina de destino:</strong> {{ $selectedPackage->destination_branch }}</span>@endif</div>
                <p class="tracking-history-note">Movimientos más recientes primero.</p>
                <div class="table-wrap"><table class="operations-table">
                    <thead><tr><th>Fecha</th><th>Clasificación</th><th>Qué ocurrió</th></tr></thead>
                    <tbody>
                    @forelse($selectedPackage->events as $event)
                        <tr wire:key="tracking-event-{{ $event->id }}"><td>{{ $event->event_at?->format('d/m/Y H:i') ?: '—' }}</td><td><strong class="tracking-event-category">{{ $event->display_category }}</strong></td><td>{{ $event->display_name }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="empty-row">Sin evento</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
    @endif
    <style>
        .operations-page{display:grid;gap:18px}.tracking-event-category{color:#dfaa43}.tracking-scope-note{margin:6px 0 0;color:#94a3b8;font-size:12px}.tracking-header-actions{display:flex;align-items:center;gap:10px}.tracking-header-actions .primary-btn{margin:0}.tracking-scope-tabs{display:flex;gap:8px;padding:6px;border:1px solid #526373;border-radius:12px;background:#283542}.tracking-scope-tabs button{display:flex;align-items:center;gap:8px;padding:10px 16px;border:1px solid transparent;border-radius:8px;background:transparent;color:#dbe3eb;font-weight:700;cursor:pointer}.tracking-scope-tabs button.is-active{background:#dfaa43;color:#19222b}.tracking-scope-tabs small{font-size:10px;opacity:.75}.tracking-table-filters{display:grid;grid-template-columns:repeat(3,minmax(130px,1fr));align-items:end;gap:10px;margin-bottom:16px;padding:14px;border:1px solid #526373;border-radius:10px;background:#283542}.tracking-table-filters label{display:grid;gap:6px;color:#dbe3eb;font-size:12px;font-weight:700}.tracking-table-filters .input{min-width:0}.tracking-table-filters .secondary-btn{margin:0;min-height:40px}.tracking-sync-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:end;gap:12px;padding:20px}.tracking-sync-form label{display:grid;gap:6px}.tracking-sync-form .force-sync{display:flex;grid-column:1/-1;align-items:center;gap:8px;padding:6px 0}.tracking-sync-form button{margin:0}.tracking-sync-actions{display:flex;grid-column:1/-1;justify-content:flex-end;gap:8px;padding-top:8px}.table-wrap{overflow:auto}.operations-table{width:100%;border-collapse:collapse;min-width:760px}.operations-table th,.operations-table td{padding:12px;text-align:left;border-bottom:1px solid #dbe3ed;vertical-align:top}.operations-table th{background:#f1f5f9}.tracking-row{cursor:pointer}.tracking-row:hover{background:rgba(96,165,250,.08)}.tracking-row:focus-visible{outline:2px solid #3b82f6;outline-offset:-2px}.empty-row{padding:24px;color:#64748b;text-align:center}.tracking-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px}.tracking-pagination nav{display:flex;align-items:center;gap:10px}.tracking-pagination nav button{margin:0}.validation-error{display:block;grid-column:1/-1;color:#fca5a5}.tracking-overlay{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:16px;background:rgba(10,15,20,.78);backdrop-filter:blur(4px)}.tracking-detail,.tracking-sync-modal{width:min(1000px,94vw);max-height:calc(100vh - 32px);overflow:auto;margin:0}.tracking-sync-modal{position:relative;width:min(560px,94vw);background:#334352!important;border-color:#526373!important;color:#e2e8f0!important}.tracking-sync-modal .modal-head{display:flex;justify-content:space-between;align-items:flex-start;padding:18px 20px;border-bottom:1px solid #526373}.tracking-sync-modal .modal-head h2{margin:0;color:#f1f5f9}.tracking-sync-modal .modal-head p{margin:5px 0 0;color:#cbd5df}.tracking-detail .modal-head{display:flex;justify-content:space-between;align-items:flex-start;padding:16px;border-bottom:1px solid #dbe3ed}.tracking-detail .modal-head h2{margin:0}.tracking-detail .modal-head p{margin:5px 0 0;color:#64748b}.tracking-detail-summary{display:flex;flex-wrap:wrap;gap:18px;padding:14px 16px}.tracking-history-note{margin:0;padding:0 16px 10px;color:#94a3b8;font-size:12px}.tracking-sync-progress{position:absolute;inset:0;z-index:3;display:flex;align-items:center;flex-direction:column;justify-content:center;gap:10px;border-radius:14px;background:rgba(40,53,66,.94);padding:28px 20px;text-align:center;color:#e2e8f0}.tracking-sync-progress small{color:#cbd5df}.tracking-sync-spinner{width:32px;height:32px;border:3px solid #657687;border-top-color:#dfaa43;border-radius:50%;animation:tracking-spin .8s linear infinite}@keyframes tracking-spin{to{transform:rotate(360deg)}}.tracking-sync-modal .operation-alert{margin:0 20px 16px}.night-mode .operations-table th{background:#172640}.night-mode .operations-table td,.night-mode .operations-table th{border-color:#334766}
        @media(max-width:700px){.tracking-header-actions{align-items:flex-start;flex-direction:column}.tracking-table-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.tracking-sync-form{grid-template-columns:1fr}.tracking-sync-form .force-sync,.tracking-sync-actions{grid-column:1}.tracking-detail-summary{flex-direction:column;gap:8px}}
    </style>
</div>
