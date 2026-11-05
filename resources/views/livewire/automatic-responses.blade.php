<section class="card automatic-responses-card">
<div class="card-title"><div><h2>Respuestas automáticas</h2><p class="filter-help">Mensajes breves para eventos y cotizaciones. No usan la IA.</p></div><button type="button" class="primary-btn" onclick="document.getElementById('automaticResponseModal').showModal()">＋ Crear mensaje</button></div>
<div class="automatic-response-filters"><label>Ámbito<select wire:model.live="scopeFilter" class="input"><option value="all">Todos los ámbitos</option><option value="general">General</option><option value="nacional">Nacional</option><option value="internacional">Internacional</option></select></label><label>Tipo<select wire:model.live="typeFilter" class="input"><option value="all">Todos los tipos</option><option value="event">Eventos</option><option value="quote">Cotizaciones</option></select></label><label>Clave<select wire:model.live="keyFilter" class="input"><option value="all">Todas las claves</option>@foreach($keys as $key)<option value="{{ $key }}">{{ $key }}</option>@endforeach</select></label><button type="button" wire:click="clearFilters" class="secondary-btn">Limpiar filtros</button></div>
<div class="automatic-response-list">@forelse($responses as $response)<article class="response-card"><div class="response-card-head"><div><h3>{{ $response->name }}</h3><div class="response-meta"><span class="scope-badge scope-{{ $response->scope ?? 'general' }}">{{ ucfirst($response->scope ?? 'general') }}</span><span>{{ $response->type === 'event' ? 'Evento' : 'Cotización' }}</span><code>{{ $response->key }}</code></div></div><span class="response-status {{ $response->active ? 'is-active' : 'is-inactive' }}">{{ $response->active ? 'Activa' : 'Inactiva' }}</span></div><div class="response-copy">{{ $response->template }}</div><div class="response-card-actions"><button type="button" wire:click="toggle({{ $response->id }})" class="action-btn">{{ $response->active ? '◉ Desactivar' : '○ Activar' }}</button><button type="button" wire:click="delete({{ $response->id }})" wire:confirm="¿Eliminar esta respuesta?" class="action-btn danger">⌫ Eliminar</button></div></article>@empty<div class="empty-log">Todavía no hay respuestas configuradas.</div>@endforelse</div><div class="automatic-response-pagination">{{ $responses->links() }}</div>
<dialog id="automaticResponseModal" class="automatic-response-modal"><div class="modal-head"><div><h2>Crear mensaje personalizado</h2><p>Este mensaje pertenece a la automatización y no modifica la IA.</p></div><button type="button" class="modal-close" onclick="this.closest('dialog').close()">×</button></div><form wire:submit="save" class="automatic-response-form"><label>Ámbito<select wire:model="scope" class="input"><option value="general">General</option><option value="nacional">Nacional</option><option value="internacional">Internacional</option></select></label><label>Tipo<select wire:model="type" class="input"><option value="event">Evento de paquete</option><option value="quote">Cotización</option></select></label><label>Clave<select wire:model="key" class="input"><option value="">Selecciona una clave</option>@if($type==='event')<option value="saludo">Saludo</option><option value="recibido_origen">Recibido en origen</option><option value="en_camino">En camino</option><option value="entregado">Entregado</option><option value="aduana">En aduana</option>@else<option value="EMS NAT">EMS nacional</option><option value="EMS INT">EMS internacional</option><option value="Delivery Express">Delivery Express</option>@endif</select></label><label>Nombre<input wire:model="name" class="input" placeholder="Ej. Paquete recibido"></label><label class="wide">Palabras o frases activadoras<textarea wire:model="triggerPhrases" class="input" rows="2" placeholder="Ej.: hola, buenos días, quiero saludar"></textarea><small>Sepáralas por comas. Si el mensaje contiene una, se usará esta respuesta.</small></label><label class="wide">Respuesta<textarea wire:model="template" class="input" rows="4" placeholder="Usa {tracking_number}, {recipient} o {price}"></textarea></label><label class="check"><input wire:model="active" type="checkbox"> Activa</label><button class="primary-btn" type="submit">Guardar mensaje</button></form></dialog>
</section>
<style>
.automatic-responses-card{padding:18px;background:#fff!important;border:1px solid #dbe3ed!important;color:#172033}
.automatic-responses-page .welcome h1{color:#172033}
.automatic-response-filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin:16px 0}
.automatic-response-filters label{display:grid;gap:5px;font-size:12px;color:#475569}
.automatic-response-filters .input{min-width:170px;padding:9px}
.automatic-response-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:12px}
.response-card{background:#fff;border:1px solid #dbe3ed;border-radius:12px;padding:16px;min-width:0}
.response-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.response-card h3{margin:0 0 9px;font-size:15px;color:#172033}
.response-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;color:#475569;font-size:11px}
.response-meta code{background:#f1f5f9;color:#334155;border-radius:5px;padding:3px 6px}
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
.automatic-response-pagination{padding:16px 0}
.automatic-response-pagination nav{display:flex;justify-content:center}
.automatic-response-pagination svg{width:18px}
.automatic-response-pagination a,.automatic-response-pagination span{padding:6px 10px}
.automatic-response-modal{border:1px solid #dbe3ed;border-radius:12px;padding:0;width:min(720px,calc(100% - 28px));background:#fff;color:#172033;box-shadow:0 20px 70px #0f172a33}
.automatic-response-modal::backdrop{background:#0b122066}
.automatic-response-modal .modal-head{display:flex;justify-content:space-between;align-items:flex-start;padding:18px;border-bottom:1px solid #e2e8f0}
.automatic-response-modal .modal-head p{margin:0;color:#475569;font-size:13px}
.automatic-response-modal .modal-close{border:0;background:transparent;color:#334155;font-size:25px;cursor:pointer}
.automatic-response-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:18px}
.automatic-response-form label{display:grid;gap:5px;color:#334155;font-size:12px}
.automatic-response-form .input{background:#fff;color:#172033;border-color:#cbd5e1}
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
body.night-mode .response-card{background:#14213a;border-color:#334766}
body.night-mode .response-card h3{color:#f8fafc}
body.night-mode .response-meta{color:#cbd5e1}
body.night-mode .response-meta code{background:#263858;color:#e2e8f0}
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
body.night-mode .automatic-responses-card .secondary-btn{background:#111c31;color:#e2e8f0;border-color:#405472}
body.night-mode .automatic-responses-card .secondary-btn:hover{background:#1e3150}
@media(max-width:700px){.automatic-response-list{grid-template-columns:1fr}.response-card-head{flex-direction:column}.response-card-actions{justify-content:stretch}.action-btn{flex:1}.automatic-response-form{grid-template-columns:1fr}.automatic-response-form .wide{grid-column:auto}}
</style>
<script>document.addEventListener('livewire:init',function(){Livewire.on('automatic-response-saved',function(){var modal=document.getElementById('automaticResponseModal');if(modal)modal.close()})})</script>
