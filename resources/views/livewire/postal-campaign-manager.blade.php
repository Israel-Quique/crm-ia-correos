<div class="content">
    <div class="welcome"><div><h1>Campañas {{ $scope === 'nacional' ? 'Nacionales' : 'Internacionales' }} ✦</h1><p>Filtra paquetes con teléfono y prepara campañas postales.</p></div></div>
    @include('livewire.campaign-create-modal')
    @if (session()->has('campaignCreated'))
        <div class="card" role="status">{{ session('campaignCreated') }}</div>
    @endif
    @if (session()->has('campaignError'))
        <div class="card" role="alert">{{ session('campaignError') }}</div>
    @endif
    @if ($cdsSyncNotice)
        <div class="card" role="status">{{ $cdsSyncNotice }}</div>
    @endif
    <div class="card campaign-filters"><div class="card-title"><div><h3>Filtros {{ strtoupper($source) }}</h3><small class="filter-help">Solo se muestran paquetes con número telefónico.</small></div><span>{{ $filtersApplied ? $this->totalFiltered . ' encontrados' : 'Aplica un filtro para consultar' }}</span></div><div class="filter-grid"><label>Desde<input wire:model="from" type="date" class="input"></label><label>Hasta<input wire:model="to" type="date" class="input"></label><label>Evento<select wire:model="eventFilter" class="input"><option value="">Todos los eventos</option>@foreach($this->eventSummary as $event=>$count)<option value="{{ $event }}">{{ $event }} ({{ $count }})</option>@endforeach</select></label><label>Cantidad máxima<input wire:model="limit" type="number" min="1" max="1000" class="input"></label></div><div class="filter-buttons"><button type="button" class="primary-btn" wire:click="applyFilters">Aplicar filtros</button><button type="button" class="secondary-btn" wire:click="clearFilters">Limpiar filtros</button></div></div>
    @if($filtersApplied)<section class="card package-table-card"><div class="package-table-head"><strong>Paquetes {{ strtoupper($source) }}</strong><span>{{ $this->visible }} mostrados de {{ $this->totalFiltered }} encontrados</span></div><div class="package-table-wrap"><table class="package-table"><colgroup><col class="col-guide"><col class="col-phone"><col class="col-phone"><col class="col-recipient"><col class="col-event"><col class="col-date"></colgroup><thead><tr><th>Guía</th><th>Teléfono origen</th><th>Teléfono destino</th><th>Destinatario</th><th>Evento actual</th><th>Actualizado</th></tr></thead><tbody>@forelse($this->packages as $package)<tr><td class="tracking-cell">{{ $package->tracking_number }}</td><td>{{ $package->origin_phone ?: 'S/N' }}</td><td>{{ $this->displayPhone($package) }}</td><td class="recipient-cell">{{ $this->displayRecipient($package) }}</td><td><span class="package-event">{{ $package->current_event ?: 'Sin evento' }}</span></td><td class="updated-cell">{{ optional($package->updated_at_api ?: $package->updated_at)->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="6" class="package-empty">No hay paquetes para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div>{{ $this->packages->links() }}</section>@endif
</div>
