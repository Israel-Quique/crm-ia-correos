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
        .postal-template-module{--template-bg:#fff;--template-panel:#fff;--template-border:#dbe4ee;--template-text:#17365d;--template-muted:#647d97;--template-input:#fff;--template-message:#f1f5f9;color:var(--template-text)}
        .postal-template-module .template-page-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 22px}
        .postal-template-module .template-page-header h1{margin:0;color:#f8fafc;font-size:23px}
        .postal-template-module .template-page-header h1 span{color:#f59e0b}
        .postal-template-module .template-page-header p{margin:5px 0 0;color:#94a3b8;font-size:12px}
        .template-new-button,.template-save-button{margin:0;background:#f59e0b;color:#111827;border:0}
        .template-feedback{margin-bottom:14px;background:#ecfdf5;border-color:#a7f3d0;color:#047857}
        .template-category-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:24px;padding:8px;border:1px solid var(--template-border);border-radius:11px;background:var(--template-panel)}
        .template-category-tab{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--template-text);font-size:12px;cursor:pointer}
        .template-category-tab.is-active{border-color:#f59e0b;background:#fffbeb;color:#92400e;font-weight:700}
        .template-category-dot{width:8px;height:8px;border-radius:50%;background:#3b82f6}
        .dot-purple{background:#6366f1}.dot-cyan{background:#06b6d4}.dot-orange{background:#f59e0b}.dot-green{background:#10b981}.dot-red{background:#f43f5e}.dot-gray{background:#94a3b8}
        .template-editor-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(330px,1fr);gap:24px}
        .template-editor-card,.template-preview-card{display:flex;min-width:0;flex-direction:column;padding:20px;background:var(--template-panel);border-color:var(--template-border);color:var(--template-text)}
        .template-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--template-border)}
        .template-panel-heading h2{margin:0;color:var(--template-text);font-size:13px;font-weight:700;text-transform:uppercase}
        .template-panel-heading h2 span{margin-right:7px;color:#f59e0b}
        .template-category-badge{padding:5px 9px;border:1px solid #2563eb;border-radius:6px;color:#60a5fa;font-size:10px}
        .template-editor-card form{display:flex;flex:1;flex-direction:column;padding-top:18px}
        .template-editor-label{display:grid;gap:8px;margin-bottom:17px;color:var(--template-text);font-size:11px}
        .template-editor-label .input{background:var(--template-input);border-color:var(--template-border);color:var(--template-text);font-size:12px}
        .template-message-input{min-height:165px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;line-height:1.55}
        .template-validation-error{color:#dc2626;font-size:11px}
        .template-draft-notice{margin:0 0 14px;color:#b45309;font-size:11px}
        .template-editor-footer{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-top:auto;padding-top:18px;border-top:1px solid var(--template-border)}
        .template-quick-tags{display:grid;gap:9px}
        .template-quick-tags strong{color:var(--template-muted);font-size:9px;letter-spacing:.04em}
        .template-quick-tags>div{display:flex;flex-wrap:wrap;gap:6px}
        .template-quick-tags button{padding:5px 8px;border:1px solid var(--template-border);border-radius:5px;background:transparent;color:#f59e0b;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:10px;cursor:pointer}
        .template-save-button{flex:none;padding:9px 13px;font-size:11px}
        .template-live-badge{display:flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid #065f46;border-radius:5px;background:#052e2b;color:#34d399;font-size:10px}
        .template-live-badge i{width:6px;height:6px;border-radius:50%;background:#10b981}
        .template-preview-description{margin:16px 0;color:var(--template-muted);font-size:10px;line-height:1.5}
        .template-chat-preview{display:flex;flex:1;align-items:flex-start;padding:18px 12px;border:1px solid var(--template-border);border-radius:14px;background:#d9d3ca}
        .template-chat-message{position:relative;width:fit-content;max-width:96%;padding:14px 16px 28px;border-radius:4px 18px 18px 18px;background:#202c33;color:#f5f5f5;box-shadow:0 1px 1px #0002;font-family:Arial,sans-serif;font-size:clamp(16px,1.4vw,20px);line-height:1.45;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere}
        .template-chat-message>small{position:absolute;right:12px;bottom:6px;color:#b8c5cc;font-size:12px;white-space:nowrap}
        .template-preview-tokens{display:flex;justify-content:space-between;gap:8px;margin-top:auto;padding-top:15px;border-top:1px solid var(--template-border);color:var(--template-muted);font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:9px}
        body.night-mode .postal-template-module{--template-panel:#0b111e;--template-bg:#070b13;--template-border:#202b3d;--template-text:#e5edf8;--template-muted:#8fa4bf;--template-input:#070b13;--template-message:#101a2b}
        body.night-mode .template-category-tabs{background:#0b111e}
        body.night-mode .template-category-tab{color:#cbd5e1}
        body.night-mode .template-category-tab.is-active{background:#201a10;color:#fbbf24}
        body.night-mode .template-editor-card,body.night-mode .template-preview-card{background:#0b111e;border-color:#202b3d}
        body.night-mode .template-panel-heading{border-color:#202b3d}
        body.night-mode .template-panel-heading h2{color:#dbeafe}
        body.night-mode .template-editor-label{color:#cbd5e1}
        body.night-mode .template-editor-label .input{background:#070b13;color:#e5edf8;border-color:#263449}
        body.night-mode .template-editor-footer,body.night-mode .template-preview-tokens{border-color:#202b3d}
        body.night-mode .template-quick-tags strong,body.night-mode .template-preview-description,body.night-mode .template-preview-tokens{color:#8196b1}
        body.night-mode .template-quick-tags button{border-color:#263449;background:#080d17;color:#fbbf24}
        body.night-mode .template-chat-preview{background:#111b21;border-color:#202b3d}
        @media(max-width:900px){.template-editor-layout{grid-template-columns:1fr}.template-preview-card{min-height:360px}}
        @media(max-width:600px){.postal-template-module .template-page-header{align-items:flex-start;flex-direction:column}.template-editor-card,.template-preview-card{padding:14px}.template-editor-footer{align-items:stretch;flex-direction:column}.template-save-button{align-self:flex-end}.template-preview-tokens{flex-direction:column}}
    </style>
</div>
