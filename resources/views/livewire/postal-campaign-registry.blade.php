<div class="content postal-registry-page">
<div class="welcome"><div><h1>Campañas {{ $scope === 'nacional' ? 'nacionales' : 'internacionales' }}</h1><p>Consulta campañas, progreso y paquetes incluidos.</p></div><button type="button" class="primary-btn registry-create-trigger" wire:click="openCreateCampaign">＋ Crear campaña</button></div>
@if($campaignFeedback)<div class="card" role="status">{{ $campaignFeedback }}</div>@endif
@if($campaignError)<div class="card" role="alert">{{ $campaignError }}</div>@endif
@php($campaigns = $this->campaigns)
<section class="card registry-card"><div class="card-title"><h2>Campañas</h2><span class="result-count">{{ $campaigns->count() }} registros</span></div><div class="registry-table-wrap" @if($campaigns->contains('status', 'sending')) wire:poll.500ms="processNextCampaignMessage" @endif><table class="registry-table"><thead><tr><th>Campaña</th><th>Rango de fechas</th><th>Paquetes</th><th>Mensajes</th><th>Progreso</th><th>Estado</th><th>Creada</th><th>Acciones</th></tr></thead><tbody>
@forelse($campaigns as $campaign)
@php($progress = $campaign->total ? min(100, round(($campaign->sent / $campaign->total) * 100)) : 0)
@php($campaignFrom = data_get($campaign->filters, 'from'))
@php($campaignTo = data_get($campaign->filters, 'to'))
<tr>
    <td>{{ $campaign->name }}</td>
    <td>
        @if($campaignFrom && $campaignTo)
            {{ \Illuminate\Support\Carbon::parse($campaignFrom)->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($campaignTo)->format('d/m/Y') }}
        @elseif($campaignFrom)
            Desde {{ \Illuminate\Support\Carbon::parse($campaignFrom)->format('d/m/Y') }}
        @elseif($campaignTo)
            Hasta {{ \Illuminate\Support\Carbon::parse($campaignTo)->format('d/m/Y') }}
        @else
            Todas las fechas
        @endif
    </td>
    <td>{{ $campaign->total }}</td>
    <td>{{ $campaign->messages_count }}</td>
    <td><button type="button" class="registry-progress-trigger" wire:click="openCampaignInfo({{ $campaign->id }})" aria-label="Ver progreso y mensajes de {{ $campaign->name }}"><span class="registry-progress"><i style="width:{{ $progress }}%"></i></span><small>{{ $campaign->sent }}/{{ $campaign->total }} ({{ $progress }}%)</small></button></td>
    <td>{{ ucfirst($campaign->status) }}<small class="registry-state-counts">{{ $campaign->pending }} pendientes · {{ $campaign->failed }} fallidos</small></td>
    <td>{{ optional($campaign->created_at)->format('d/m/Y H:i') }}</td>
    <td class="registry-actions">
        <div class="registry-action-group">
            @if($campaign->pending > 0 && in_array($campaign->status, ['prepared', 'partial'], true))
                <button type="button" class="primary-btn" wire:click="sendCampaign({{ $campaign->id }})" wire:confirm="¿Enviar los {{ $campaign->pending }} mensajes pendientes de esta campaña?" wire:loading.attr="disabled">Enviar pendientes</button>
            @endif
            @if($campaign->pending > 0 && in_array($campaign->status, ['prepared', 'partial', 'sending'], true))
                <button type="button" class="secondary-btn registry-cancel-btn" wire:click="cancelCampaign({{ $campaign->id }})" wire:confirm="¿Cancelar los envíos pendientes de esta campaña? Un mensaje que ya esté siendo enviado podría terminar; los mensajes ya enviados se conservarán." wire:loading.attr="disabled">Cancelar envíos</button>
            @endif
            @if($campaign->failed > 0)
                <button type="button" class="secondary-btn" wire:click="retryFailedCampaign({{ $campaign->id }})" wire:confirm="¿Reintentar {{ $campaign->failed }} mensaje(s) fallido(s)? Los mensajes ya enviados no se repetirán." wire:loading.attr="disabled">Reintentar fallidos</button>
            @endif
            @if($campaign->status !== 'sending')
                <button type="button" class="secondary-btn registry-delete-btn" wire:click="deleteCampaign({{ $campaign->id }})" wire:confirm="¿Eliminar permanentemente la campaña «{{ $campaign->name }}» y todos sus mensajes? Esta acción no se puede deshacer.">Eliminar</button>
            @endif
        </div>
    </td>
</tr>
@empty <tr><td colspan="8">Aún no hay campañas registradas.</td></tr>
@endforelse
</tbody></table></div></section>
<dialog id="registryCreateCampaign" class="campaign-history-modal registry-create-modal" x-effect="$wire.showCreateCampaign && !$el.open && $el.showModal()" x-on:campaign-modal-close.window="$el.close()" x-on:close="$wire.set('showCreateCampaign', false)">
    @if($showCreateCampaign)
    <div class="modal-head"><div><h2>Nueva campaña {{ $scope }}</h2><p>Filtra los paquetes y configura el envío antes de crearla.</p></div><button type="button" class="modal-close" onclick="this.closest('dialog').close()" aria-label="Cerrar">×</button></div>
    <livewire:postal-campaign-manager :scope="$scope" :modal-mode="true" :key="'campaign-creator-'.$scope" />
    @endif
</dialog>
@if($infoCampaignId && $this->infoCampaign)
    @php($infoCampaign = $this->infoCampaign)
    <div class="registry-info-overlay" wire:key="registry-info-overlay-{{ $infoCampaign->id }}">
        <section id="registryCampaignInfo" class="campaign-history-modal registry-info-panel" role="dialog" aria-modal="true" aria-labelledby="registry-info-title">
            <div class="modal-head"><div><h2 id="registry-info-title">{{ $infoCampaign->name }}</h2><p>Los estados se actualizan conforme se procesan los mensajes.</p></div><button type="button" class="modal-close" wire:click="closeCampaignInfo" aria-label="Cerrar">×</button></div>
            <div class="registry-info-summary">
                <span><strong>{{ $infoCampaign->sent }}/{{ $infoCampaign->total }}</strong> enviados</span>
                <span><strong>{{ $infoCampaign->pending }}</strong> pendientes</span>
                <span><strong>{{ $infoCampaign->failed }}</strong> fallidos</span>
            </div>
            <div class="registry-table-wrap"><table class="registry-table"><thead><tr><th>Guía</th><th>Teléfono</th><th>Mensaje</th><th>Estado / error</th><th>Respondió</th></tr></thead><tbody>
                @forelse($infoCampaign->messages as $message)
                    <tr class="registry-message-row" wire:key="registry-message-{{ $message->id }}"><td>{{ $message->package?->tracking_number ?? '—' }}</td><td>{{ $message->phone ?: '—' }}</td><td class="message-cell">{{ $message->message }}</td><td>{{ $this->messageStatusLabel($message->status) }}@if($message->error_message)<small class="registry-message-error">{{ $message->error_message }}</small>@endif</td><td class="reply-check"><input type="checkbox" disabled @checked($message->status === 'replied') aria-label="{{ $message->status === 'replied' ? 'Respondió' : 'Sin respuesta' }}"></td></tr>
                @empty
                    <tr><td colspan="5">Los mensajes enviados aparecerán aquí uno por uno.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>
@endif
<style>
    .registry-state-counts,.registry-message-error{display:block;margin-top:4px;color:#dc2626;font-size:.78rem}
    .registry-actions{min-width:330px}
    .registry-action-group{display:flex;align-items:center;flex-wrap:wrap;gap:7px;min-width:280px}
    .registry-progress-trigger{display:grid;gap:6px;padding:0;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer}
    .registry-progress-trigger:hover .registry-progress{outline:2px solid #60a5fa;outline-offset:2px}
    .registry-progress-trigger:focus-visible{outline:2px solid #3b82f6;outline-offset:4px;border-radius:4px}
    .registry-info-summary{display:flex;flex-wrap:wrap;gap:18px;padding:14px 18px;color:#526b87}
    .registry-info-summary strong{color:#102a4c}
    .registry-info-overlay{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:16px;background:#02061799}
    .registry-info-panel{position:relative;width:min(1100px,94vw);max-height:calc(100vh - 32px);margin:0;overflow:auto}
    .registry-message-row{animation:registry-message-appear .4s ease-out both}
    @keyframes registry-message-appear{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(prefers-reduced-motion:reduce){.registry-message-row{animation:none}}
    .registry-actions .primary-btn,.registry-actions .secondary-btn{margin:0;padding:8px 11px;font-size:11px;white-space:nowrap}
    .registry-actions .registry-cancel-btn{color:#b45309;border-color:#f59e0b}
    .registry-actions .registry-delete-btn{color:#dc2626;border-color:#fca5a5}
    .postal-registry-page .registry-actions .registry-delete-btn:hover{background:#fef2f2;border-color:#ef4444;color:#b91c1c}
    .registry-create-trigger{margin-left:auto}
    .registry-create-modal{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;width:min(760px,94vw);max-height:calc(100vh - 32px);overflow:auto}
    .registry-create-modal{background:#fff;color:#17365d;border-color:#d5e0eb;color-scheme:light}
    .registry-create-modal .modal-head{position:sticky;top:0;z-index:3;background:#f8fafc;border-bottom-color:#e1e8f0}
    .registry-create-modal .modal-head h2{color:#102a4c}
    .registry-create-modal .modal-head p{color:#647d97}
    .registry-create-modal .modal-close{color:#284968}
    .registry-create-modal .postal-campaign-creator{padding:16px;color:#284968}
    .registry-create-modal .campaign-filters{margin:0 0 14px;background:#f8fafc;border-color:#dbe4ee;color:#284968}
    .registry-create-modal .campaign-filters .card-title h3,.registry-create-modal .campaign-builder h3{color:#102a4c}
    .registry-create-modal .campaign-filters .card-title span,.registry-create-modal .campaign-filters .filter-help{color:#647d97}
    .registry-create-modal .campaign-event-buttons{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin:12px 0}
    .registry-create-modal .campaign-event-buttons-label{flex-basis:100%;color:#526b87;font-size:12px;font-weight:600}
    .registry-create-modal .campaign-event-button{margin:0;padding:7px 10px;font-size:11px}
    .registry-create-modal .campaign-event-button.is-active{border-color:#d99a00;background:#fffbeb;color:#805b00}
    .registry-create-modal .campaign-builder{padding:16px;background:#f8fafc;border-color:#dbe4ee;color:#284968}
    .registry-create-modal .campaign-builder h3{margin:0 0 14px;font-size:15px}
    .registry-create-modal .campaign-builder-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .registry-create-modal .campaign-builder label{display:grid;gap:6px}
    .registry-create-modal .campaign-builder label,.registry-create-modal .campaign-filters label{color:#284968}
    .registry-create-modal .campaign-builder small,.registry-create-modal .campaign-mode-help{color:#526b87}
    .registry-create-modal .input,.registry-create-modal select.input,.registry-create-modal textarea.input{background:#fff;color:#17365d;border-color:#cbd8e5;color-scheme:light}
    .registry-create-modal select.input option{background:#fff;color:#17365d}
    .registry-create-modal .secondary-btn{background:#fff;color:#294866;border-color:#cbd8e5}
    .registry-create-modal .campaign-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
    .registry-create-modal .campaign-modal-actions .primary-btn,.registry-create-modal .campaign-modal-actions .secondary-btn{margin:0}
    body.night-mode .postal-registry-page .registry-create-modal{background:#0d172b;color:#e2e8f0;border-color:#334766;color-scheme:dark}
    body.night-mode .postal-registry-page .registry-create-modal .modal-head{background:#0d172b;border-bottom-color:#263858}
    body.night-mode .postal-registry-page .registry-create-modal .modal-head h2,body.night-mode .postal-registry-page .registry-create-modal .campaign-filters .card-title h3,body.night-mode .postal-registry-page .registry-create-modal .campaign-builder h3{color:#f8fafc}
    body.night-mode .postal-registry-page .registry-create-modal .modal-head p,body.night-mode .postal-registry-page .registry-create-modal .campaign-filters .card-title span,body.night-mode .postal-registry-page .registry-create-modal .campaign-filters .filter-help,body.night-mode .postal-registry-page .registry-create-modal .campaign-builder small{color:#aabbd0}
    body.night-mode .postal-registry-page .registry-create-modal .modal-close,body.night-mode .postal-registry-page .registry-create-modal .campaign-builder label,body.night-mode .postal-registry-page .registry-create-modal .campaign-filters label{color:#dbe5f0}
    body.night-mode .postal-registry-page .registry-create-modal .campaign-event-buttons-label{color:#aabbd0}
    body.night-mode .postal-registry-page .registry-create-modal .campaign-event-button.is-active{border-color:#d99a00;background:#3a2b0b;color:#fde68a}
    body.night-mode .postal-registry-page .registry-create-modal .campaign-filters,body.night-mode .postal-registry-page .registry-create-modal .campaign-builder{background:#111c31;border-color:#263858;color:#e2e8f0}
    body.night-mode .postal-registry-page .registry-create-modal .input,body.night-mode .postal-registry-page .registry-create-modal select.input,body.night-mode .postal-registry-page .registry-create-modal textarea.input{background:#0b1424;color:#f8fafc;border-color:#405472;color-scheme:dark}
    body.night-mode .postal-registry-page .registry-create-modal select.input option{background:#0b1424;color:#f8fafc}
    body.night-mode .postal-registry-page .registry-create-modal .secondary-btn{background:#111c31;color:#dbe5f0;border-color:#405472}
    body.night-mode .postal-registry-page .registry-info-summary{color:#aabbd0}
    body.night-mode .postal-registry-page .registry-info-summary strong{color:#f8fafc}
    @media(max-width:1100px){.registry-table{min-width:1100px}}
    @media(max-width:700px){.registry-create-trigger{margin:12px 0 0}.registry-create-modal .campaign-builder-grid{grid-template-columns:1fr}.registry-create-modal .filter-grid{grid-template-columns:1fr}}
    body.night-mode .registry-state-counts,body.night-mode .registry-message-error{color:#fca5a5}
    body.night-mode .registry-actions .registry-cancel-btn{color:#fcd34d;border-color:#b45309}
    body.night-mode .registry-actions .registry-delete-btn{color:#fca5a5;border-color:#7f1d1d}
</style>
</div>
