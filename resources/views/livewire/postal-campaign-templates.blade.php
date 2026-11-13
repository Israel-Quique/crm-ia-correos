<div class="postal-template-module">
    <header class="template-page-header">
        <div>
            <h1>Mensajes predeterminados <span aria-hidden="true">▰</span></h1>
            <p>Selecciona una categoría de estado para editar y previsualizar el mensaje de campañas {{ $scope }}.</p>
        </div>
        <button type="button" class="primary-btn template-new-button" wire:click="startNewMessage">＋ Nuevo mensaje</button>
    </header>

    @if($feedback)
        <div class="card template-feedback" role="status">{{ $feedback }}</div>
    @endif

    <nav class="template-category-tabs" aria-label="Categorías de estado del envío">
        @foreach($categories as $key => $category)
            <button
                type="button"
                class="template-category-tab {{ $selectedCategory === $key ? 'is-active' : '' }}"
                wire:click="$set('selectedCategory', '{{ $key }}')"
                aria-pressed="{{ $selectedCategory === $key ? 'true' : 'false' }}"
            >
                <span class="template-category-dot dot-{{ $category['color'] }}" aria-hidden="true"></span>
                {{ $category['label'] }}
            </button>
        @endforeach
    </nav>

    <div class="template-editor-layout">
        <section class="card template-editor-card">
            <div class="template-panel-heading">
                <h2><span aria-hidden="true">✎</span> Edición del mensaje</h2>
                <span class="template-category-badge">{{ $categories[$selectedCategory]['label'] }}</span>
            </div>

            <form wire:submit="save" x-data="{
                insertTag(tag) {
                    const textarea = this.$refs.template;
                    const start = textarea.selectionStart ?? textarea.value.length;
                    const end = textarea.selectionEnd ?? textarea.value.length;
                    textarea.setRangeText(tag, start, end, 'end');
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.focus();
                }
            }">
                @if($isNewMessage)
                    <p class="template-draft-notice">Al guardar, este mensaje reemplazará el mensaje actual de la categoría seleccionada.</p>
                @endif
                <label class="template-editor-label">
                    Título del mensaje
                    <input wire:model.live="name" class="input" maxlength="120" placeholder="Ej. Confirmación de admisión en origen">
                    @error('name')<small class="template-validation-error">{{ $message }}</small>@enderror
                </label>
                <label class="template-editor-label">
                    Contenido del mensaje (con etiquetas)
                    <textarea x-ref="template" wire:model.live="template" class="input template-message-input" rows="7" maxlength="2000" placeholder="Hola {recipient}, tu paquete {tracking_number}..."></textarea>
                    @error('template')<small class="template-validation-error">{{ $message }}</small>@enderror
                </label>

                <div class="template-editor-footer">
                    <div class="template-quick-tags">
                        <strong>ETIQUETAS RÁPIDAS (CLIC PARA INSERTAR)</strong>
                        <div>
                            <button type="button" x-on:click="insertTag('{recipient}')">+{recipient}</button>
                            <button type="button" x-on:click="insertTag('{tracking_number}')">+{tracking_number}</button>
                            <button type="button" x-on:click="insertTag('{current_event}')">+{current_event}</button>
                            <button type="button" x-on:click="insertTag('{campaign_name}')">+{campaign_name}</button>
                            <button type="button" x-on:click="insertTag('{destination_branch}')">+{destination_branch}</button>
                        </div>
                    </div>
                    <button type="submit" class="primary-btn template-save-button" wire:loading.attr="disabled">▣ Guardar cambios</button>
                </div>
            </form>
        </section>

        <section class="card template-preview-card">
            <div class="template-panel-heading">
                <h2><span aria-hidden="true">▣</span> Vista al cliente</h2>
                <span class="template-live-badge"><i></i> En vivo</span>
            </div>
            <p class="template-preview-description">Así recibirá tu cliente este mensaje por WhatsApp:</p>
            <div class="template-chat-preview" aria-label="Vista simulada del chat recibido">
                <div class="template-chat-message">
                    <span>{{ trim(strtr($template, ['{recipient}' => 'Quique', '{tracking_number}' => 'EN000007494LPZ', '{current_event}' => $categories[$selectedCategory]['event'], '{campaign_name}' => $scope === 'nacional' ? 'Campaña nacional' : 'Campaña internacional', '{destination_branch}' => 'Sucursal Central, La Paz'])) }}</span>
                    <small>4:52 p. m.</small>
                </div>
            </div>
            <div class="template-preview-tokens">
                <span>{recipient} = "Juan Pérez"</span>
                <span>{tracking_number} = "{{ $scope === 'nacional' ? 'BO-847291' : 'LX-847291' }}"</span>
            </div>
        </section>
    </div>

    <style>
        .postal-template-module{--template-panel:#fff;--template-border:#dbe4ee;--template-text:#17365d;--template-muted:#5b7088;--template-input:#fff;color:var(--template-text)}
        .postal-template-module .template-page-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 20px}
        .postal-template-module .template-page-header h1{margin:0;color:#14263f;font-size:clamp(24px,2vw,30px);font-weight:800;letter-spacing:-.03em}
        .postal-template-module .template-page-header h1 span{color:#d99000}
        .postal-template-module .template-page-header p{max-width:650px;margin:7px 0 0;color:#384b63;font-size:14px;line-height:1.5}
        .template-new-button,.template-save-button{margin:0;background:#0b192c!important;color:#facc15!important;border:1px solid #1e293b!important}
        .template-feedback{margin-bottom:14px;background:#ecfdf5;border-color:#a7f3d0;color:#047857}
        .template-category-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:20px;padding:9px;border:1px solid var(--template-border);border-radius:12px;background:var(--template-panel);box-shadow:0 5px 16px #17365d0a}
        .template-category-tab{display:inline-flex;align-items:center;gap:9px;padding:10px 14px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--template-text);font-size:14px;font-weight:600;cursor:pointer;transition:background .15s ease,border-color .15s ease}
        .template-category-tab:hover{background:#f8fafc}
        .template-category-tab.is-active{border-color:#f2bd43;background:#fff8e6;color:#805400;font-weight:700}
        .template-category-tab:focus-visible,.template-quick-tags button:focus-visible{outline:3px solid #facc15;outline-offset:2px}
        .template-category-dot{width:9px;height:9px;flex:none;border-radius:50%;background:#3b82f6}
        .dot-purple{background:#6366f1}.dot-cyan{background:#06b6d4}.dot-orange{background:#f59e0b}.dot-green{background:#10b981}.dot-red{background:#f43f5e}.dot-gray{background:#94a3b8}
        .template-editor-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr);align-items:stretch;gap:20px}
        .template-editor-card,.template-preview-card{display:flex;min-width:0;flex-direction:column;padding:24px;background:var(--template-panel);border:1px solid var(--template-border);border-radius:14px;color:var(--template-text);box-shadow:0 8px 24px #17365d0d}
        .template-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:16px;border-bottom:1px solid var(--template-border)}
        .template-panel-heading h2{margin:0;color:var(--template-text);font-size:15px;font-weight:800;letter-spacing:.02em;text-transform:uppercase}
        .template-panel-heading h2 span{margin-right:8px;color:#d99000}
        .template-category-badge{padding:6px 10px;border:1px solid #bfdbfe;border-radius:7px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700}
        .template-editor-card form{display:flex;flex:1;flex-direction:column;padding-top:20px}
        .template-editor-label{display:grid;gap:9px;margin-bottom:20px;color:var(--template-text);font-size:14px;font-weight:700}
        .template-editor-label .input{width:100%;min-height:46px;padding:11px 13px;background:var(--template-input);border:1px solid #cbd5e1;border-radius:8px;color:var(--template-text);font-size:15px;font-weight:400;line-height:1.5}
        .template-editor-label .input:focus{border-color:#d99000;box-shadow:0 0 0 3px #facc1533;outline:none}
        .template-message-input{min-height:190px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;line-height:1.65}
        .template-validation-error{color:#dc2626;font-size:13px;font-weight:500}
        .template-draft-notice{margin:0 0 14px;padding:10px 12px;border:1px solid #fcd34d;border-radius:8px;background:#fffbeb;color:#92400e;font-size:13px;line-height:1.5}
        .template-editor-footer{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-top:auto;padding-top:20px;border-top:1px solid var(--template-border)}
        .template-quick-tags{display:grid;min-width:0;gap:10px}
        .template-quick-tags strong{color:var(--template-muted);font-size:11px;letter-spacing:.04em}
        .template-quick-tags>div{display:flex;flex-wrap:wrap;gap:8px}
        .template-quick-tags button{padding:7px 9px;border:1px solid #d5deea;border-radius:7px;background:#f8fafc;color:#8a5a00;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;font-weight:600;cursor:pointer;transition:background .15s ease,border-color .15s ease}
        .template-quick-tags button:hover{border-color:#eab308;background:#fffbeb}
        .template-save-button{flex:none;padding:11px 15px;font-size:13px}
        .template-live-badge{display:flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid #a7f3d0;border-radius:7px;background:#ecfdf5;color:#047857;font-size:12px;font-weight:700}
        .template-live-badge i{width:7px;height:7px;border-radius:50%;background:#10b981}
        .template-preview-description{margin:18px 0;color:var(--template-muted);font-size:13px;line-height:1.5}
        .template-chat-preview{display:flex;flex:1;min-height:285px;align-items:flex-start;padding:18px;border:1px solid #d8d1c5;border-radius:12px;background-color:#e9e3da;background-image:radial-gradient(#d2cabe 0.7px,transparent 0.7px);background-size:14px 14px}
        .template-chat-message{position:relative;width:fit-content;max-width:96%;padding:14px 16px 32px;border:1px solid #e6e9ec;border-radius:4px 14px 14px 14px;background:#fff;color:#263238;box-shadow:0 2px 5px #00000014;font-family:Arial,sans-serif;font-size:16px;line-height:1.55;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere}
        .template-chat-message>small{position:absolute;right:12px;bottom:7px;color:#6b7b83;font-size:11px;white-space:nowrap}
        .template-preview-tokens{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-top:auto;padding-top:16px;border-top:1px solid var(--template-border);color:var(--template-muted);font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:11px}
        body.night-mode .postal-template-module{--template-panel:#0b111e;--template-border:#202b3d;--template-text:#e5edf8;--template-muted:#a8b8cb;--template-input:#070b13}
        body.night-mode .postal-template-module .template-page-header h1{color:#f8fafc}
        body.night-mode .postal-template-module .template-page-header p{color:#bac8d9}
        body.night-mode .template-category-tabs{background:#0b111e}
        body.night-mode .template-category-tab{color:#cbd5e1}
        body.night-mode .template-category-tab:hover{background:#172235}
        body.night-mode .template-category-tab.is-active{border-color:#8a641c;background:#201a10;color:#fbbf24}
        body.night-mode .template-editor-card,body.night-mode .template-preview-card{background:#0b111e;border-color:#202b3d}
        body.night-mode .template-panel-heading{border-color:#202b3d}
        body.night-mode .template-panel-heading h2{color:#dbeafe}
        body.night-mode .template-category-badge{border-color:#1e40af;background:#172554;color:#bfdbfe}
        body.night-mode .template-editor-label{color:#cbd5e1}
        body.night-mode .template-editor-label .input{background:#070b13;color:#e5edf8;border-color:#263449}
        body.night-mode .template-draft-notice{border-color:#854d0e;background:#422006;color:#fde68a}
        body.night-mode .template-editor-footer,body.night-mode .template-preview-tokens{border-color:#202b3d}
        body.night-mode .template-quick-tags strong,body.night-mode .template-preview-description,body.night-mode .template-preview-tokens{color:#a8b8cb}
        body.night-mode .template-quick-tags button{border-color:#263449;background:#080d17;color:#fbbf24}
        body.night-mode .template-live-badge{border-color:#065f46;background:#052e2b;color:#6ee7b7}
        body.night-mode .template-chat-preview{border-color:#263449;background-color:#18232a;background-image:radial-gradient(#2b3940 0.7px,transparent 0.7px)}
        body.night-mode .template-chat-message{border-color:#34434a;background:#202c33;color:#f5f5f5}
        body.night-mode .template-chat-message>small{color:#b8c5cc}
        @media(max-width:900px){.template-editor-layout{grid-template-columns:1fr}.template-preview-card{min-height:390px}}
        @media(max-width:600px){.postal-template-module .template-page-header{align-items:flex-start;flex-direction:column}.template-new-button{align-self:flex-start}.template-editor-card,.template-preview-card{padding:17px}.template-editor-footer{align-items:stretch;flex-direction:column}.template-save-button{align-self:flex-end}.template-preview-tokens{flex-direction:column}.template-chat-preview{min-height:240px;padding:12px}.template-chat-message{max-width:100%;font-size:15px}}
    </style>
</div>
