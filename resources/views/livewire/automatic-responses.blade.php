<section class="card automatic-responses-card">
<div class="card-title"><div><h2>Respuestas automáticas</h2><p class="filter-help">Los saludos, cotizaciones y rastreos se activan por el mensaje del cliente. Las respuestas de evento se usan al consultar una guía, según su último movimiento.</p></div><button type="button" class="primary-btn" wire:click="startCreate">＋ Crear mensaje</button></div>
@if(session()->has('automaticResponseSaved'))
    <p class="automatic-response-feedback" role="status">{{ session('automaticResponseSaved') }}</p>
@endif
<div class="automatic-response-filters">
    <label>Ámbito
        <select wire:model.live="scopeFilter" class="input">
            <option value="all">Todos los ámbitos</option><option value="general">General</option><option value="nacional">Nacional</option><option value="internacional">Internacional</option>
        </select>
    </label>
    <label>Tipo
        <select wire:model.live="typeFilter" class="input">
            <option value="all">Todos los tipos</option><option value="event">Eventos</option><option value="quote">Cotizaciones</option><option value="tracking">Rastreo</option>
        </select>
    </label>
    <label>Clave
        <select wire:model.live="keyFilter" class="input">
            <option value="all">Todas las claves disponibles</option>
            @foreach($keys as $key)<option value="{{ $key }}">{{ $key }}</option>@endforeach
        </select>
    </label>
    <label>Estado
        <select wire:model.live="statusFilter" class="input">
            <option value="all">Todos los estados</option><option value="active">Activas</option><option value="inactive">Inactivas</option>
        </select>
    </label>
    <button type="button" wire:click="clearFilters" class="secondary-btn">Limpiar filtros</button>
</div>
<div class="automatic-response-list">
@forelse($responses as $response)
    @php
        $responseTriggers = [];
        foreach ([
            ['Clave', $response->key],
            ['Nombre', $response->name],
            ...array_map(fn ($phrase) => ['Frase configurada', $phrase], $response->trigger_phrases ?? []),
        ] as [$source, $term]) {
            $normalizedTerm = mb_strtolower(str_replace('_', ' ', trim($term)));
            if ($normalizedTerm !== '') {
                $responseTriggers[$normalizedTerm][] = $source;
            }
        }
    @endphp
    <article class="response-card">
        <div class="response-card-head">
            <div>
                <h3>{{ $response->name }}</h3>
                <div class="response-meta">
                    <span class="scope-badge scope-{{ $response->scope ?? 'general' }}">{{ ucfirst($response->scope ?? 'general') }}</span>
                    <span>{{ ['event' => 'Evento', 'quote' => 'Cotización', 'tracking' => 'Rastreo'][$response->type] ?? 'Otro' }}</span>
                    <code>{{ $response->key }}</code>
                </div>
            </div>
            <span class="response-status {{ $response->active ? 'is-active' : 'is-inactive' }}">{{ $response->active ? 'Activa' : 'Inactiva' }}</span>
        </div>
        <div class="response-match-context">
            <strong>Detonantes:</strong>
            <div class="response-match-examples">
                @foreach($responseTriggers as $trigger => $sources)
                    <span>“{{ $trigger }}”</span>
                @endforeach
            </div>
            <p>
                @if($response->type === 'event' && $response->key === 'saludo')
                    Se activa cuando el mensaje coincide con el detonante de saludo.
                @elseif($response->type === 'event')
                    Se compara con el último evento de la guía cuando el cliente solicita un rastreo.
                @else
                    Se activa cuando el mensaje entrante coincide con uno de estos detonantes.
                @endif
            </p>
        </div>
        <div class="response-copy">{{ $response->template }}</div>
        <div class="response-card-actions">
            <button type="button" wire:click="toggle({{ $response->id }})" class="action-btn">{{ $response->active ? '◉ Desactivar' : '○ Activar' }}</button>
            <button type="button" wire:click="edit({{ $response->id }})" class="action-btn">✎ Editar</button>
            <button type="button" wire:click="delete({{ $response->id }})" wire:confirm="¿Eliminar esta respuesta?" class="action-btn danger">⌫ Eliminar</button>
        </div>
    </article>
@empty
    <div class="empty-log">{{ $scopeFilter !== 'all' || $typeFilter !== 'all' || $keyFilter !== 'all' || $statusFilter !== 'all' ? 'No hay respuestas que coincidan con los filtros seleccionados.' : 'Todavía no hay respuestas configuradas.' }}</div>
@endforelse
</div>
@if($responses->total() > 0)
    @php
        $visiblePages = range(max(1, $responses->currentPage() - 1), min($responses->lastPage(), $responses->currentPage() + 1));
        $firstVisiblePage = $visiblePages[0];
        $lastVisiblePage = $visiblePages[count($visiblePages) - 1];
    @endphp
    <div class="automatic-response-pagination">
        <span class="automatic-response-pagination-summary">Mostrando {{ $responses->firstItem() }}–{{ $responses->lastItem() }} de {{ number_format($responses->total()) }} respuestas</span>
        <nav aria-label="Paginación de respuestas">
            <button type="button" class="automatic-page-button" wire:click="previousPage" @disabled($responses->onFirstPage()) aria-label="Página anterior">‹</button>
            @if($firstVisiblePage > 1)
                <button type="button" class="automatic-page-button" wire:click="gotoPage(1)" aria-label="Ir a página 1">1</button>
                @if($firstVisiblePage > 2)<span class="automatic-page-ellipsis" aria-hidden="true">…</span>@endif
            @endif
            @foreach($visiblePages as $page)
                <button type="button" class="automatic-page-button {{ $responses->currentPage() === $page ? 'is-current' : '' }}" wire:click="gotoPage({{ $page }})" aria-label="Ir a página {{ $page }}" @if($responses->currentPage() === $page) aria-current="page" @endif>{{ $page }}</button>
            @endforeach
            @if($lastVisiblePage < $responses->lastPage())
                @if($lastVisiblePage < $responses->lastPage() - 1)<span class="automatic-page-ellipsis" aria-hidden="true">…</span>@endif
                <button type="button" class="automatic-page-button" wire:click="gotoPage({{ $responses->lastPage() }})" aria-label="Ir a página {{ $responses->lastPage() }}">{{ $responses->lastPage() }}</button>
            @endif
            <button type="button" class="automatic-page-button" wire:click="nextPage" @disabled(!$responses->hasMorePages()) aria-label="Página siguiente">›</button>
        </nav>
    </div>
@endif
<dialog id="automaticResponseModal" class="automatic-response-modal" wire:ignore.self>
    <div class="modal-head">
        <div><h2>{{ $editingResponseId ? 'Editar mensaje personalizado' : 'Crear mensaje personalizado' }}</h2><p>Define qué texto lo activa y la respuesta que recibirá el cliente.</p></div>
        <button type="button" class="modal-close" aria-label="Cerrar" onclick="this.closest('dialog').close()">×</button>
    </div>
    <form wire:submit="save" class="automatic-response-form">
        <label>Ámbito
            <select wire:model="scope" class="input" @error('scope') aria-invalid="true" @enderror>
                <option value="general">General</option><option value="nacional">Nacional</option><option value="internacional">Internacional</option>
            </select>
            @error('scope')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label>Categoría
            <select wire:model="type" class="input" @error('type') aria-invalid="true" @enderror>
                <option value="event">Evento de paquete</option><option value="quote">Cotización</option><option value="tracking">Consulta de rastreo</option>
            </select>
            @error('type')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label>Palabra clave
            <input wire:model="key" class="input" list="automaticResponseKeySuggestions" maxlength="80" placeholder="Ej. en_camino" @error('key') aria-invalid="true" @enderror>
            <datalist id="automaticResponseKeySuggestions">
                @foreach($keySuggestions as $suggestion)<option value="{{ $suggestion }}">@endforeach
            </datalist>
            <small>Escríbela o elige una sugerencia; también puedes usar una clave propia.</small>
            @error('key')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label>Nombre
            <input wire:model="name" class="input" maxlength="120" placeholder="Ej. Paquete recibido" @error('name') aria-invalid="true" @enderror>
            @error('name')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label class="wide">Palabras o frases activadoras
            <textarea wire:model="triggerPhrases" class="input" rows="2" maxlength="1000" placeholder="Ej.: hola, buenos días, quiero saludar" @error('triggerPhrases') aria-invalid="true" @enderror></textarea>
            <small>Sepáralas por comas. Cualquiera de ellas puede activar esta respuesta.</small>
            @error('triggerPhrases')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label class="wide">Respuesta
            <textarea wire:model="template" class="input" rows="4" maxlength="1000" placeholder="Escribe el mensaje que recibirá el cliente" @error('template') aria-invalid="true" @enderror></textarea>
            @error('template')<small class="form-error">{{ $message }}</small>@enderror
        </label>
        <label class="check"><input wire:model="active" type="checkbox"> Activa</label>
        <button class="primary-btn" type="submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $editingResponseId ? 'Guardar cambios' : 'Guardar mensaje' }}</span>
            <span wire:loading wire:target="save">Guardando...</span>
        </button>
    </form>
</dialog>
</section>
<style>
.automatic-responses-card{padding:18px;background:#fff!important;border:1px solid #dbe3ed!important;color:#172033}
.automatic-responses-page .welcome h1{color:#172033}
.automatic-response-feedback{margin:12px 0;padding:10px 12px;border:1px solid #a7f3d0;border-radius:8px;background:#ecfdf5;color:#047857;font-size:13px}
.automatic-response-filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin:16px 0}
.automatic-response-filters label{display:grid;gap:5px;font-size:12px;color:#475569}
.automatic-response-filters .input{min-width:170px;padding:9px}
.automatic-response-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:12px}
.response-card{background:#fff;border:1px solid #dbe3ed;border-radius:12px;padding:16px;min-width:0}
.response-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.response-card h3{margin:0 0 9px;font-size:15px;color:#172033}
.response-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;color:#475569;font-size:11px}
.response-meta code{background:#f1f5f9;color:#334155;border-radius:5px;padding:3px 6px}
.response-match-context{margin-top:12px;padding:10px 12px;border:1px solid #e8d7aa;border-radius:9px;background:#fffbeb;color:#475569}
.response-match-context>strong{display:block;color:#172033;font-size:12px}
.response-match-examples{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px}
.response-match-examples>span{display:inline-block;padding:5px 8px;border:1px solid #f1dfb8;border-radius:6px;background:#fff;color:#704d0a;font-size:11px;font-weight:600;overflow-wrap:anywhere}
.response-match-context p{margin:7px 0 0;color:#596579;font-size:10px;line-height:1.4}
.scope-badge{padding:4px 8px;border-radius:999px;font-weight:600}
.scope-general{background:#fef3c7;color:#92400e}
.scope-nacional{background:#dbeafe;color:#1d4ed8}
.scope-internacional{background:#ede9fe;color:#6d28d9}
.response-copy{margin:16px 0;min-height:58px;color:#334155;font-size:13px;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}
.response-card-actions{display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e2e8f0;padding-top:12px}
.action-btn{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:7px 10px;color:#1d4ed8;cursor:pointer;font-size:11px}
.action-btn:hover{background:#eff6ff}
.action-btn.danger{color:#b91c1c}
.response-status{font-size:11px;padding:4px 9px;border-radius:999px;white-space:nowrap}
.response-status.is-active{background:#dcfce7;color:#166534}
.response-status.is-inactive{background:#f1f5f9;color:#475569}
.automatic-response-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:16px;padding:12px 16px;border:1px solid #dbe3ed;border-radius:10px;background:#f1f5f9;color:#475569}
.automatic-response-pagination nav{display:flex;align-items:center;justify-content:center;gap:8px}
.automatic-response-pagination-summary{font-size:12px}
.automatic-page-button{display:grid;min-width:36px;height:36px;place-items:center;padding:0 9px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-size:13px;cursor:pointer}
.automatic-page-button:hover:not(:disabled){border-color:#d6a53c;background:#fffaf0}
.automatic-page-button.is-current{border-color:#d6a53c;background:#e6b34b;color:#17212b;font-weight:700}
.automatic-page-button:disabled{opacity:.45;cursor:not-allowed}
.automatic-page-button:focus-visible{outline:2px solid #d6a53c;outline-offset:2px}
.automatic-page-ellipsis{padding:0 2px;color:#64748b}
.automatic-response-modal{position:fixed;inset:0;margin:auto;border:1px solid #dbe3ed;border-radius:12px;padding:0;width:min(720px,calc(100% - 28px));max-height:calc(100dvh - 28px);overflow:auto;background:#fff;color:#172033;box-shadow:0 20px 70px #0f172a33}
.automatic-response-modal::backdrop{background:#0b122066}
.automatic-response-modal .modal-head{display:flex;justify-content:space-between;align-items:flex-start;padding:18px;border-bottom:1px solid #e2e8f0}
.automatic-response-modal .modal-head p{margin:0;color:#475569;font-size:13px}
.automatic-response-modal .modal-close{border:0;background:transparent;color:#334155;font-size:25px;cursor:pointer}
.automatic-response-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:18px}
.automatic-response-form label{display:grid;gap:5px;color:#334155;font-size:12px}
.automatic-response-form .input{background:#fff;color:#172033;border-color:#cbd5e1}
.automatic-response-form .input[aria-invalid="true"]{border-color:#dc2626}
.automatic-response-form .form-error{color:#b91c1c;font-size:11px}
.automatic-response-form label>small:not(.form-error){color:#64748b;font-size:10px;line-height:1.4}
.automatic-response-form .wide{grid-column:1/-1}
.automatic-response-form .primary-btn{grid-column:1/-1}
.secondary-btn{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;padding:9px 13px;cursor:pointer}
.secondary-btn:hover{border-color:#94a3b8;background:#f8fafc}
body:not(.night-mode) .automatic-response-filters .input{background:#fff!important;color:#172033!important;border-color:#cbd5e1!important;color-scheme:light}
body:not(.night-mode) .automatic-response-form .input{background:#fff!important;color:#172033!important;border-color:#cbd5e1!important;color-scheme:light}
body.night-mode .automatic-responses-card{background:#0d172b!important;border-color:#263858!important;color:#f8fafc}
body.night-mode .automatic-responses-page .welcome h1{color:#f8fafc}
body.night-mode .automatic-response-filters label{color:#cbd5e1}
body.night-mode .automatic-response-filters .input{background:#111c31!important;color:#f8fafc!important;border-color:#405472!important;color-scheme:dark}
body.night-mode .automatic-response-filters .input option{background:#111c31;color:#f8fafc}
body.night-mode .automatic-response-pagination{border-color:#334766;background:#243240;color:#cbd5e1}
body.night-mode .automatic-page-button{border-color:#526373;background:#1f2d3a;color:#dbe3eb}
body.night-mode .automatic-page-button:hover:not(:disabled){border-color:#dfaa43;background:#283542}
body.night-mode .automatic-page-button.is-current{border-color:#dfaa43;background:#283542;color:#facc15}
body.night-mode .automatic-page-ellipsis{color:#a8b8cb}
body.night-mode .response-card{background:#14213a;border-color:#334766}
body.night-mode .response-card h3{color:#f8fafc}
body.night-mode .response-meta{color:#cbd5e1}
body.night-mode .response-meta code{background:#263858;color:#e2e8f0}
body.night-mode .response-match-context{border-color:#70551d;background:#211c12;color:#cbd5e1}
body.night-mode .response-match-context>strong{color:#f8fafc}
body.night-mode .response-match-examples>span{border-color:#70551d;background:#2c2518;color:#facc15}
body.night-mode .response-match-context p{color:#cbd5e1}
body.night-mode .response-copy{color:#e2e8f0}
body.night-mode .response-card-actions{border-top-color:#334766}
body.night-mode .action-btn{background:#111c31;border-color:#405472;color:#93c5fd}
body.night-mode .action-btn:hover{background:#1e3150}
body.night-mode .action-btn.danger{color:#fca5a5}
body.night-mode .response-status.is-inactive{background:#263858;color:#cbd5e1}
body.night-mode .automatic-response-modal{background:#0d172b;border-color:#334766;color:#f8fafc;box-shadow:0 20px 70px #0008}
body.night-mode .automatic-response-modal::backdrop{background:#0b1220aa}
body.night-mode .automatic-response-modal .modal-head{border-bottom-color:#263858}
body.night-mode .automatic-response-modal .modal-head p{color:#cbd5e1}
body.night-mode .automatic-response-modal .modal-close{color:#e2e8f0}
body.night-mode .automatic-response-form label{color:#cbd5e1}
body.night-mode .automatic-response-form .input{background:#111c31!important;color:#f8fafc!important;border-color:#405472!important;color-scheme:dark}
body.night-mode .automatic-response-form label>small:not(.form-error){color:#a8b8cb}
body.night-mode .automatic-response-form .form-error{color:#fca5a5}
body.night-mode .automatic-response-feedback{border-color:#065f46;background:#052e2b;color:#6ee7b7}
body.night-mode .automatic-responses-card .secondary-btn{background:#111c31;color:#e2e8f0;border-color:#405472}
body.night-mode .automatic-responses-card .secondary-btn:hover{background:#1e3150}
@media(prefers-color-scheme:dark){
    body:not(.night-mode) .automatic-responses-card{background:#283542!important;border-color:#455767!important;color:#e2e8f0}
    body:not(.night-mode) .automatic-responses-card .card-title h2,
    body:not(.night-mode) .automatic-responses-page .welcome h1{color:#f1f5f9}
    body:not(.night-mode) .automatic-responses-card .filter-help{color:#cbd5df}
    body:not(.night-mode) .automatic-response-filters label{color:#dbe3eb}
    body:not(.night-mode) .automatic-response-filters .input{background:#1f2d3a!important;color:#f8fafc!important;border-color:#526373!important;color-scheme:dark!important}
    body:not(.night-mode) .automatic-response-filters .input option{background:#1f2d3a!important;color:#f8fafc!important}
    body:not(.night-mode) .response-card{background:#334352;border-color:#455767}
    body:not(.night-mode) .response-card h3{color:#f1f5f9}
    body:not(.night-mode) .response-meta{color:#dbe3eb}
    body:not(.night-mode) .response-meta code{background:#283542;color:#e2e8f0}
    body:not(.night-mode) .response-match-context{border-color:#705d32;background:#383324;color:#e2e8f0}
    body:not(.night-mode) .response-match-context>strong{color:#f8fafc}
    body:not(.night-mode) .response-match-examples>span{border-color:#786236;background:#403722;color:#facc15}
    body:not(.night-mode) .response-match-context p{color:#cbd5df}
    body:not(.night-mode) .response-copy{color:#e2e8f0}
    body:not(.night-mode) .response-card-actions{border-top-color:#526373}
    body:not(.night-mode) .action-btn{background:#283542;border-color:#526373;color:#bfdbfe}
    body:not(.night-mode) .action-btn:hover{background:#3b4d5e}
    body:not(.night-mode) .action-btn.danger{color:#fca5a5}
    body:not(.night-mode) .response-status.is-inactive{background:#283542;color:#dbe3eb}
    body:not(.night-mode) .automatic-response-pagination{border-color:#455767;background:#334352;color:#dbe3eb}
    body:not(.night-mode) .automatic-page-button{border-color:#526373;background:#283542;color:#e2e8f0}
    body:not(.night-mode) .automatic-page-button:hover:not(:disabled){border-color:#dfaa43;background:#3b4d5e}
    body:not(.night-mode) .automatic-page-button.is-current{border-color:#dfaa43;background:#dfaa43;color:#19222b}
    body:not(.night-mode) .automatic-page-ellipsis{color:#cbd5df}
    body:not(.night-mode) .automatic-response-feedback{border-color:#16775a;background:#123d34;color:#a7f3d0}
    body:not(.night-mode) .automatic-responses-card .secondary-btn{background:#283542;color:#e2e8f0;border-color:#526373}
    body:not(.night-mode) .automatic-responses-card .secondary-btn:hover{background:#3b4d5e}
}
@media(max-width:700px){.automatic-response-list{grid-template-columns:1fr}.automatic-response-filters{display:grid;grid-template-columns:1fr 1fr}.automatic-response-filters label,.automatic-response-filters .input{min-width:0}.automatic-response-filters .secondary-btn{align-self:end}.automatic-response-pagination{align-items:flex-start;flex-direction:column}.automatic-response-pagination nav{width:100%;justify-content:flex-start}.response-card-head{flex-direction:column}.response-card-actions{justify-content:stretch;flex-wrap:wrap}.action-btn{flex:1}.automatic-response-form{grid-template-columns:1fr}.automatic-response-form .wide{grid-column:auto}}
</style>
<script>
document.addEventListener('automatic-response-open',function(){
    var modal=document.getElementById('automaticResponseModal');
    if(modal&&!modal.open)modal.showModal();
});
document.addEventListener('automatic-response-saved',function(){
    var modal=document.getElementById('automaticResponseModal');
    if(modal?.open)modal.close();
});
document.addEventListener('click',function(event){
    var modal=document.getElementById('automaticResponseModal');
    if(modal?.open&&event.target===modal)modal.close();
});
</script>
