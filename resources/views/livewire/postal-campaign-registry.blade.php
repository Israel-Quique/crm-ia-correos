<div class="content postal-registry-page">
@php($campaigns = $this->campaigns)
@php($campaignSummary = $this->campaignSummary)
@php($isSending = (bool) $campaignSummary->is_sending)
<div class="welcome"><div><h1>Campañas {{ $scope === 'nacional' ? 'nacionales' : 'internacionales' }} @if($isSending)<span class="registry-system-badge"><i></i>Envíos activos</span>@endif</h1><p>Gestión de campañas postales y seguimiento directo.</p></div><button type="button" class="primary-btn registry-create-trigger" x-on:click="document.getElementById('registryCreateCampaign').showModal()" wire:click="openCreateCampaign">＋ Crear campaña</button></div>
@if($campaignFeedback)<div class="card" role="status">{{ $campaignFeedback }}</div>@endif
@if($campaignError)<div class="card" role="alert">{{ $campaignError }}</div>@endif
<section class="registry-summary" aria-label="Resumen de campañas">
    <article class="registry-summary-card"><div class="registry-summary-label"><span>Total de registros</span><i class="summary-icon navy">▱</i></div><strong>{{ number_format($campaignSummary->campaign_count) }}</strong><small>{{ number_format($campaignSummary->total_packages) }} paquetes en campañas filtradas</small></article>
    <article class="registry-summary-card"><div class="registry-summary-label"><span>Completados</span><i class="summary-icon blue">✓</i></div><strong>{{ number_format($campaignSummary->completed_campaigns) }}</strong><small>Campañas enviadas</small></article>
    <article class="registry-summary-card"><div class="registry-summary-label"><span>En preparación</span><i class="summary-icon yellow">◷</i></div><strong>{{ number_format($campaignSummary->pending_messages) }}</strong><small>Mensajes pendientes</small></article>
    <article class="registry-summary-card"><div class="registry-summary-label"><span>Mensajes enviados</span><i class="summary-icon navy">➤</i></div><strong>{{ number_format($campaignSummary->sent_messages) }}</strong><small>En todas las campañas filtradas</small></article>
</section>
<section class="registry-filters-panel">
    <div class="registry-filters-heading"><h2><span>▽</span> Filtros de búsqueda</h2><span class="result-count">{{ number_format($campaigns->total()) }} registros</span></div>
    <div class="registry-filters">
        <div class="registry-filter-main">
            <label>Buscar campaña
                <input class="input" type="search" wire:model.live.debounce.300ms="campaignSearch" placeholder="Nombre de campaña">
            </label>
            <label>Estado
                <select class="input" wire:model.live="campaignStatus">
                    <option value="">Todos los estados</option>
                    <option value="prepared">Preparada</option>
                    <option value="sending">Enviando</option>
                    <option value="paused">Pausada</option>
                    <option value="partial">Parcial</option>
                    <option value="sent">Enviada</option>
                    <option value="failed">Fallida</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </label>
        </div>
        <div class="registry-filter-dates">
            <div class="registry-filter-range">
                <span>Período de campaña</span>
                <div>
                    <label><input class="input" type="date" aria-label="Período de campaña desde" wire:model.live="campaignPeriodFrom"></label>
                    <label><input class="input" type="date" aria-label="Período de campaña hasta" wire:model.live="campaignPeriodTo"></label>
                </div>
            </div>
            <div class="registry-filter-range">
                <span>Fecha de creación</span>
                <div>
                    <label><input class="input" type="date" aria-label="Fecha de creación desde" wire:model.live="campaignCreatedFrom"></label>
                    <label><input class="input" type="date" aria-label="Fecha de creación hasta" wire:model.live="campaignCreatedTo"></label>
                </div>
            </div>
            <button type="button" class="secondary-btn registry-clear-filters" wire:click="clearCampaignFilters">Limpiar filtros</button>
        </div>
    </div>
    </section>
<section class="registry-table-card">
    <div class="registry-table-wrap" @if($isSending) wire:poll.500ms="processNextCampaignMessage" @endif><table class="registry-table"><thead><tr><th>Campaña</th><th>Rango de fechas</th><th>Departamentos</th><th>Paquetes</th><th>Mensajes</th><th>Progreso</th><th>Estado</th><th>Creada</th><th>Acciones</th></tr></thead><tbody>
@forelse($campaigns as $campaign)
@php($progress = $campaign->total ? min(100, round(($campaign->sent / $campaign->total) * 100)) : 0)
@php($campaignFrom = data_get($campaign->filters, 'from'))
@php($campaignTo = data_get($campaign->filters, 'to'))
<tr class="registry-campaign-row" wire:click="openCampaignInfo({{ $campaign->id }})" wire:keydown.enter="openCampaignInfo({{ $campaign->id }})" wire:keydown.space.prevent="openCampaignInfo({{ $campaign->id }})" tabindex="0" aria-label="Ver paquetes y mensajes de {{ $campaign->name }}" title="Selecciona para ver los paquetes y mensajes de esta campaña">
    <td><span class="registry-campaign-name"><i class="{{ $campaign->status === 'sent' ? 'completed' : '' }}">{{ $campaign->status === 'sent' ? '✓' : '▱' }}</i><strong>{{ $campaign->name }}</strong></span></td>
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
    <td class="registry-departments">
        <small>Origen: {{ data_get($campaign->filters, 'origin_department') ?: 'Todos' }}</small>
        <small>Destino: {{ data_get($campaign->filters, 'destination_department') ?: 'Todos' }}</small>
    </td>
    <td>{{ $campaign->total }}</td>
    <td>{{ $campaign->messages_count }}</td>
    <td><div class="registry-progress-display"><span class="registry-progress"><i style="width:{{ $progress }}%"></i></span><small>{{ $campaign->sent }}/{{ $campaign->total }} ({{ $progress }}%)</small></div></td>
    <td><span class="registry-status-badge status-{{ $campaign->status }}"><i></i>{{ $this->campaignStatusLabel($campaign->status) }}</span><small class="registry-state-counts">{{ $campaign->pending }} pend. · {{ $campaign->failed }} fallidos</small></td>
    <td>{{ optional($campaign->created_at)->format('d/m/Y H:i') }}</td>
    <td class="registry-actions">
        <div class="registry-action-group" x-on:click.stop x-on:keydown.stop>
            @if($campaign->pending > 0 && in_array($campaign->status, ['prepared', 'partial'], true))
                <button type="button" class="primary-btn registry-send-btn" wire:click="sendCampaign({{ $campaign->id }})" wire:confirm="¿Enviar los {{ $campaign->pending }} mensajes pendientes de esta campaña?" wire:loading.attr="disabled">▶ Enviar</button>
            @endif
            @if($campaign->status === 'sending')
                <button type="button" class="secondary-btn registry-pause-btn" wire:click="pauseCampaign({{ $campaign->id }})" wire:loading.attr="disabled">Ⅱ Pausar</button>
            @elseif($campaign->status === 'paused' && $campaign->pending > 0)
                <button type="button" class="primary-btn" wire:click="resumeCampaign({{ $campaign->id }})" wire:loading.attr="disabled">▶ Reanudar</button>
            @endif
            @if($campaign->pending > 0 && in_array($campaign->status, ['prepared', 'partial', 'sending', 'paused'], true))
                <button type="button" class="secondary-btn registry-cancel-btn" wire:click="cancelCampaign({{ $campaign->id }})" wire:confirm="¿Cancelar los envíos pendientes de esta campaña? Un mensaje que ya esté siendo enviado podría terminar; los mensajes ya enviados se conservarán." wire:loading.attr="disabled">⊗ Cancelar</button>
            @endif
            @if($campaign->failed > 0)
                <button type="button" class="secondary-btn" wire:click="retryFailedCampaign({{ $campaign->id }})" wire:confirm="¿Reintentar {{ $campaign->failed }} mensaje(s) fallido(s)? Los mensajes ya enviados no se repetirán." wire:loading.attr="disabled">Reintentar fallidos</button>
            @endif
        </div>
    </td>
</tr>
@empty <tr><td colspan="9">Aún no hay campañas registradas.</td></tr>
@endforelse
</tbody></table></div>
<div class="registry-table-footer">
    <span>Mostrando {{ $campaigns->firstItem() ?? 0 }}–{{ $campaigns->lastItem() ?? 0 }} de {{ number_format($campaigns->total()) }} campañas</span>
    @if($campaigns->hasPages())
        <nav class="registry-pagination" aria-label="Paginación de campañas">
            <button type="button" class="secondary-btn" wire:click="previousPage('campaignPage')" @disabled($campaigns->onFirstPage()) aria-label="Página anterior">← Anterior</button>
            <span>Página {{ $campaigns->currentPage() }} de {{ $campaigns->lastPage() }}</span>
            <button type="button" class="secondary-btn" wire:click="nextPage('campaignPage')" @disabled(!$campaigns->hasMorePages()) aria-label="Página siguiente">Siguiente →</button>
        </nav>
    @endif
    <span>{{ number_format($campaignSummary->total_packages) }} paquetes · {{ number_format($campaignSummary->pending_messages) }} pendientes</span>
</div>
</section>
<dialog id="registryCreateCampaign" class="campaign-history-modal registry-create-modal" wire:ignore.self x-effect="$wire.showCreateCampaign && !$el.open && $el.showModal()" x-on:campaign-modal-close.window="$el.close()" x-on:close="$wire.set('showCreateCampaign', false)">
    <div class="modal-head"><div><h2>Nueva campaña {{ $scope }}</h2><p>Filtra los paquetes y configura los mensajes; se enviarán automáticamente al crearla.</p></div><button type="button" class="modal-close" onclick="this.closest('dialog').close()" aria-label="Cerrar">×</button></div>
    <div class="registry-creator-loading" wire:loading wire:target="openCreateCampaign" role="status" aria-live="polite">
        <span class="registry-creator-spinner" aria-hidden="true"></span>
        <span>Cargando paquetes y filtros...</span>
    </div>
    @if($showCreateCampaign)
    <livewire:postal-campaign-manager :scope="$scope" :modal-mode="true" :key="'campaign-creator-'.$scope" lazy />
    @endif
</dialog>
@if($infoCampaignId && $this->infoCampaign)
    @php($infoCampaign = $this->infoCampaign)
    <div class="registry-info-overlay" wire:key="registry-info-overlay-{{ $infoCampaign->id }}">
        <section id="registryCampaignInfo" class="campaign-history-modal registry-info-panel" role="dialog" aria-modal="true" aria-labelledby="registry-info-title" wire:poll.2s>
            <div class="modal-head"><div><h2 id="registry-info-title">{{ $infoCampaign->name }}</h2><p>Los estados y las respuestas se actualizan automáticamente cada 2 segundos.</p></div><button type="button" class="modal-close" wire:click="closeCampaignInfo" aria-label="Cerrar">×</button></div>
            <div class="registry-info-controls">
                <input
                    class="input registry-info-search"
                    type="search"
                    wire:model.live.debounce.300ms="infoSearch"
                    placeholder="Buscar por número de paquete o celular"
                    aria-label="Buscar por número de paquete o celular"
                >
            </div>
            @php($infoMessageCounts = $this->infoMessageCounts)
            <div class="registry-info-summary" role="group" aria-label="Filtrar mensajes por estado">
                <button type="button" class="registry-info-summary-filter {{ $infoStatus === 'sent' ? 'is-active' : '' }}" wire:click="filterInfoStatus('sent')" aria-pressed="{{ $infoStatus === 'sent' ? 'true' : 'false' }}">
                    <strong>{{ number_format($infoMessageCounts['sent']) }}/{{ number_format($infoCampaign->total) }}</strong><small>Enviados</small>
                </button>
                <button type="button" class="registry-info-summary-filter {{ $infoStatus === 'pending' ? 'is-active' : '' }}" wire:click="filterInfoStatus('pending')" aria-pressed="{{ $infoStatus === 'pending' ? 'true' : 'false' }}">
                    <strong>{{ number_format($infoMessageCounts['pending']) }}</strong><small>Pendientes</small>
                </button>
                <button type="button" class="registry-info-summary-filter {{ $infoStatus === 'failed' ? 'is-active' : '' }}" wire:click="filterInfoStatus('failed')" aria-pressed="{{ $infoStatus === 'failed' ? 'true' : 'false' }}">
                    <strong>{{ number_format($infoMessageCounts['failed']) }}</strong><small>Fallidos</small>
                </button>
            </div>
            @php($infoMessages = $this->infoMessages)
            <div class="registry-table-wrap"><table class="registry-table"><thead><tr><th>Guía</th><th>Teléfono</th><th>Mensaje</th><th>Estado / error</th><th>Respondió</th></tr></thead><tbody>
                @forelse($infoMessages as $message)
                    <tr class="registry-message-row" wire:key="registry-message-{{ $message->id }}" wire:click="openCampaignChat({{ $message->id }})" wire:keydown.enter="openCampaignChat({{ $message->id }})" wire:keydown.space.prevent="openCampaignChat({{ $message->id }})" tabindex="0" aria-label="Abrir chat del paquete {{ $message->package?->tracking_number ?? '' }}">
                        <td>{{ $message->package?->tracking_number ?? '—' }}</td>
                        <td>{{ $message->phone ?: '—' }}</td>
                        <td class="message-cell">{{ $message->message }}</td>
                        <td>{{ $this->messageStatusLabel($message->status) }}@if($message->error_message)<small class="registry-message-error">{{ $message->error_message }}</small>@endif</td>
                        <td class="reply-check"><span class="{{ $message->has_reply ? 'registry-replied' : 'registry-no-reply' }}">{{ $message->has_reply ? 'Sí · Respondió' : 'No' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay mensajes que coincidan con la búsqueda y el estado seleccionados.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="registry-message-pagination" aria-label="Paginación de mensajes">
                <small>Mostrando {{ $infoMessages->firstItem() ?? 0 }}–{{ $infoMessages->lastItem() ?? 0 }} de {{ $infoMessages->total() }} mensajes</small>
                <div>
                    <button type="button" class="secondary-btn" wire:click="previousInfoPage" @disabled(!$infoMessages->previousPageUrl()) aria-label="Página anterior">Anterior</button>
                    <span>Página {{ $infoMessages->currentPage() }} de {{ $infoMessages->lastPage() }}</span>
                    <button type="button" class="secondary-btn" wire:click="nextInfoPage" @disabled(!$infoMessages->nextPageUrl()) aria-label="Página siguiente">Siguiente</button>
                </div>
            </div>
        </section>
    </div>
@endif
<style>
    .registry-state-counts,.registry-message-error{display:block;margin-top:4px;color:#dc2626;font-size:.78rem}
    .registry-campaign-row{cursor:pointer}
    .registry-campaign-row:hover{background:rgba(96,165,250,.08)}
    .registry-campaign-row:focus-visible{outline:2px solid #3b82f6;outline-offset:-2px}
    .registry-actions{min-width:330px}
    .registry-action-group{display:flex;align-items:center;flex-wrap:wrap;gap:7px;min-width:280px}
    .registry-progress-display{display:grid;gap:6px;color:inherit;text-align:left}
    .registry-info-summary{display:flex;flex-wrap:wrap;gap:18px;padding:14px 18px;color:#526b87}
    .registry-message-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 18px;color:#526b87}
    .registry-message-pagination>div{display:flex;align-items:center;gap:10px}
    .registry-message-pagination .secondary-btn{margin:0;padding:7px 10px}
    .registry-message-pagination .secondary-btn:disabled{opacity:.5;cursor:not-allowed}
    .registry-info-summary strong{color:#102a4c}
    .registry-info-overlay{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:16px;background:#02061799}
    .registry-info-panel{position:relative;width:min(1100px,94vw);max-height:calc(100vh - 32px);margin:0;overflow:auto}
    .registry-message-row{animation:registry-message-appear .4s ease-out both}
    .registry-message-row{cursor:pointer}
    .registry-message-row:hover{background:rgba(96,165,250,.08)}
    .registry-message-row:focus-visible{outline:2px solid #3b82f6;outline-offset:-2px}
    .registry-replied{color:#15803d;font-weight:600}
    .registry-no-reply{color:#64748b}
    @keyframes registry-message-appear{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(prefers-reduced-motion:reduce){.registry-message-row{animation:none}}
    .registry-actions .primary-btn,.registry-actions .secondary-btn{margin:0;padding:8px 11px;font-size:11px;white-space:nowrap}
    .registry-actions .registry-cancel-btn{color:#b45309;border-color:#f59e0b}
    .registry-create-trigger{margin-left:auto}
    .registry-creator-loading{min-height:180px;display:flex;align-items:center;justify-content:center;gap:12px;padding:24px;color:#526b87}
    .registry-creator-spinner{width:20px;height:20px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:registry-creator-spin .8s linear infinite}
    @keyframes registry-creator-spin{to{transform:rotate(360deg)}}
    @media(prefers-reduced-motion:reduce){.registry-creator-spinner{animation:none}}
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
    body.night-mode .postal-registry-page .registry-message-pagination{color:#aabbd0}
    body.night-mode .postal-registry-page .registry-info-summary strong{color:#f8fafc}
    body.night-mode .postal-registry-page .registry-replied{color:#86efac}
    body.night-mode .postal-registry-page .registry-no-reply{color:#aabbd0}
    @media(max-width:1100px){.registry-table{min-width:1100px}}
    @media(max-width:700px){.registry-create-trigger{margin:12px 0 0}.registry-create-modal .campaign-builder-grid{grid-template-columns:1fr}.registry-create-modal .filter-grid{grid-template-columns:1fr}.registry-message-pagination{align-items:flex-start;flex-direction:column}}
    body.night-mode .registry-state-counts,body.night-mode .registry-message-error{color:#fca5a5}
    body.night-mode .registry-actions .registry-cancel-btn{color:#fcd34d;border-color:#b45309}
</style>
</div>
