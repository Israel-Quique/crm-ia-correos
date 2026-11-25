<section class="decision-tree-studio">
    @if(session()->has('decisionFlowUpdated'))
        <p class="decision-tree-feedback" role="status">{{ session('decisionFlowUpdated') }}</p>
    @endif

    <div class="decision-tree-studio-grid">
        <section class="decision-tree-panel decision-tree-map-panel" aria-label="Mapa de bifurcación">
            <header class="decision-tree-panel-header">
                <div>
                    <span class="decision-tree-eyebrow">MAPA DE BIFURCACIÓN</span>
                    <h2>Desarrollo de preguntas por nodos</h2>
                </div>
                <div class="decision-tree-legend" aria-label="Tipos de nodo">
                    <span><i class="legend-root"></i>ROOT Node</span>
                    <span><i class="legend-decision"></i>Decision Node</span>
                    <span><i class="legend-terminal"></i>Terminal Node</span>
                </div>
            </header>

            <div class="decision-tree-map-scroll">
                <div class="decision-tree-map-canvas">
                    <div class="decision-tree-root-node {{ $simulatorNode === 'root' ? 'is-current' : '' }}">
                        <small>▶ ROOT NODE · INICIO</small>
                        <strong>Saludo y bienvenida</strong>
                        <span>Dispara la primera pregunta</span>
                    </div>
                    <div class="decision-tree-root-connector" aria-hidden="true"><span>⌁ Splitting</span></div>
                    <div class="decision-tree-map-fork" aria-hidden="true"></div>

                    <div class="decision-tree-map-branches">
                        <article class="decision-tree-map-flow quote-flow {{ ($simulatorContext['flow'] ?? null) === 'quote' ? 'is-current-flow' : '' }} {{ $decisionFlows['quote']['active'] ? '' : 'is-paused' }}">
                            <header>
                                <span class="flow-index">A</span>
                                <div>
                                    <small>RAMA DE COTIZACIÓN</small>
                                    <strong>Cotizar un envío</strong>
                                </div>
                                <span class="flow-status">{{ $decisionFlows['quote']['active'] ? 'ACTIVO' : 'PAUSADO' }}</span>
                            </header>

                            <div class="map-node decision-node {{ $simulatorNode === 'quote_scope' ? 'is-current' : '' }}">
                                <small>DECISION NODE</small>
                                <strong>¿Qué tipo de envío deseas cotizar?</strong>
                            </div>
                            <div class="map-node-connector" aria-hidden="true"></div>

                            <div class="quote-scope-branches">
                                <div class="map-node terminal-node {{ ($simulatorContext['service'] ?? '') === 'ems_nat' && in_array($simulatorNode, ['quote_origin', 'quote_destination', 'quote_weight', 'quote_dimensions', 'quote_terminal'], true) ? 'is-current' : '' }}">
                                    <small>TERMINAL NODE</small>
                                    <strong>1 · Nacional · EMS NAT</strong>
                                </div>
                                <div class="map-node terminal-node {{ ($simulatorContext['service'] ?? '') === 'ems_int' && in_array($simulatorNode, ['quote_destination', 'quote_weight', 'quote_dimensions', 'quote_terminal'], true) ? 'is-current' : '' }}">
                                    <small>TERMINAL NODE</small>
                                    <strong>2 · Internacional · EMS INT</strong>
                                </div>
                                <div class="map-node terminal-node {{ ($simulatorContext['service'] ?? '') === 'delivery_express' && in_array($simulatorNode, ['quote_origin', 'quote_destination', 'quote_weight', 'quote_mode', 'quote_dimensions', 'quote_terminal'], true) ? 'is-current' : '' }}">
                                    <small>TERMINAL NODE</small>
                                    <strong>3 · Delivery Express</strong>
                                </div>
                            </div>
                            <div class="map-node terminal-node map-terminal-summary {{ in_array($simulatorNode, ['quote_origin', 'quote_destination', 'quote_weight', 'quote_mode', 'quote_dimensions', 'quote_oversize', 'quote_terminal'], true) ? 'is-current' : '' }}">
                                <small>DATOS Y RESPUESTA FINAL</small>
                                <strong>Origen · destino · peso · tarifa verificada</strong>
                            </div>
                        </article>

                        <article class="decision-tree-map-flow tracking-flow {{ ($simulatorContext['flow'] ?? null) === 'tracking' ? 'is-current-flow' : '' }} {{ $decisionFlows['tracking']['active'] ? '' : 'is-paused' }}">
                            <header>
                                <span class="flow-index">B</span>
                                <div>
                                    <small>RAMA DE RASTREO</small>
                                    <strong>Rastrear un paquete</strong>
                                </div>
                                <span class="flow-status">{{ $decisionFlows['tracking']['active'] ? 'ACTIVO' : 'PAUSADO' }}</span>
                            </header>

                            <div class="map-node decision-node {{ in_array($simulatorNode, ['tracking_number', 'tracking_scope'], true) ? 'is-current' : '' }}">
                                <small>DECISION NODE</small>
                                <strong>Número de guía y alcance del envío</strong>
                            </div>
                            <div class="map-node-connector" aria-hidden="true"></div>
                            <div class="tracking-result-branches">
                                <div class="map-node terminal-node {{ in_array($simulatorContext['tracking_result'] ?? '', ['courier', 'pickup', 'other'], true) ? 'is-current' : '' }}">
                                    <small>TERMINAL NODE · EVENTO ENCONTRADO</small>
                                    <strong>Cartero · Ventanilla · último estado</strong>
                                </div>
                                <div class="map-node terminal-node {{ ($simulatorContext['tracking_result'] ?? '') === 'not_found' ? 'is-current' : '' }}">
                                    <small>TERMINAL NODE</small>
                                    <strong>Guía no encontrada · solicitar verificación</strong>
                                </div>
                            </div>
                            <p class="decision-tree-map-note">La respuesta depende del último evento disponible para la guía.</p>
                        </article>
                    </div>
                </div>
            </div>

        </section>

        <section class="decision-tree-panel decision-tree-simulator" aria-label="Simulador de chat en vivo">
            <header class="simulator-header">
                <span class="simulator-whatsapp-icon" aria-hidden="true">◉</span>
                <div class="simulator-title">
                    <h2>Simulador de chat en vivo</h2>
                    <span>Activo: {{ $simulatorActiveLabel }}</span>
                </div>
                <button
                    type="button"
                    class="simulator-copy"
                    x-data="{ copied: false }"
                    x-on:click="
                        const chat = $el.closest('.decision-tree-simulator')?.querySelector('.simulator-chat');
                        const lines = [...(chat?.querySelectorAll('.simulator-message') || [])].map((message) => {
                            const node = message.querySelector('.simulator-bubble small')?.textContent?.trim();
                            const text = message.querySelector('.simulator-bubble p')?.textContent?.trim() || '';
                            return [message.classList.contains('is-client') ? text : (node ? '**' + node + '**\\n' + text : text)].join('');
                        });
                        const text = lines.join('\\n');
                        const copy = navigator.clipboard
                            ? navigator.clipboard.writeText(text)
                            : (() => {
                                const area = document.createElement('textarea');
                                area.value = text;
                                area.style.position = 'fixed';
                                area.style.opacity = '0';
                                document.body.appendChild(area);
                                area.select();
                                const copied = document.execCommand('copy');
                                area.remove();
                                return copied ? Promise.resolve() : Promise.reject();
                            })();
                        copy.then(() => { copied = true; setTimeout(() => copied = false, 1800); }).catch(() => {});
                    "
                    x-text="copied ? 'Copiado' : 'Copiar chat'"
                    aria-label="Copiar conversación del simulador"
                ></button>
                <span class="simulator-active-badge">DETONANTES ACTIVOS</span>
            </header>

            <div class="simulator-disclaimer">
                Los detonantes activos inician cada flujo. No envía mensajes: consulta tarifas y paquetes sincronizados.
            </div>

            <div
                class="simulator-chat"
                x-ref="simulatorChat"
                role="log"
                aria-live="polite"
                aria-label="Mensajes de la simulación"
                x-data
                x-init="$el._messageObserver = new MutationObserver(() => { $el.scrollTop = $el.scrollHeight }); $el._messageObserver.observe($el, { childList: true }); $el.scrollTop = $el.scrollHeight"
            >
                @if($simulatorMessages === [])
                    <p class="simulator-empty">Escribe «Hola», «Quisiera cotizar el envío de un paquete» o «Quiero rastrear un paquete» para iniciar.</p>
                @endif
                @foreach($simulatorMessages as $message)
                    <article wire:key="simulator-message-{{ $loop->index }}" class="simulator-message {{ $message['speaker'] === 'client' ? 'is-client' : 'is-bot' }}">
                        @if($message['speaker'] === 'bot')
                            <span class="simulator-avatar" aria-hidden="true"> </span>
                        @endif
                        <div class="simulator-bubble">
                            @if($message['speaker'] === 'bot' && !empty($message['node']))
                                <small>{{ $message['node'] }}</small>
                            @endif
                            <p>{{ $message['text'] }}</p>
                        </div>
                        @if($message['speaker'] === 'client')
                            <span class="simulator-avatar client-avatar" aria-hidden="true">TÚ</span>
                        @endif
                    </article>
                @endforeach
            </div>

            <form class="simulator-input-row" wire:submit="sendSimulatorMessage">
                <input
                    type="text"
                    wire:model="simulatorInput"
                    placeholder="{{ $simulatorPlaceholder }}"
                    aria-label="Respuesta para la simulación"
                    autocomplete="off"
                >
                <button type="submit" aria-label="Enviar mensaje de prueba">Enviar <span aria-hidden="true">➤</span></button>
                <button type="button" class="simulator-reset" wire:click="resetSimulator" aria-label="Reiniciar simulación">↻</button>
            </form>
        </section>
    </div>
</section>
<style>
.decision-tree-studio{--studio-bg:#17232d;--studio-panel:#1c2933;--studio-panel-2:#263641;--studio-border:#3b4b57;--studio-text:#f2f5f7;--studio-muted:#bac5cd;--studio-blue:#83b6df;--studio-orange:#e6bb55;--studio-green:#69c6a6;display:grid;gap:14px;color:var(--studio-text)}
.decision-tree-feedback{margin:0;padding:11px 14px;border:1px solid #4d9278;border-radius:10px;background:#203b34;color:#b1ead4;font-size:13px}
.decision-tree-studio-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(360px,1fr);gap:16px;align-items:stretch}
.decision-tree-panel{min-width:0;overflow:hidden;border:1px solid var(--studio-border);border-radius:18px;background:var(--studio-bg);box-shadow:0 12px 30px #02061730}
.decision-tree-map-panel,.decision-tree-simulator{height:min(740px,calc(100vh - 260px));min-height:460px}
.decision-tree-map-panel{display:flex;flex-direction:column}
.decision-tree-panel-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px 14px}
.decision-tree-eyebrow{display:block;color:var(--studio-orange);font-size:11px;font-weight:800;letter-spacing:.13em}
.decision-tree-panel-header h2{margin:5px 0 0;color:#f8fafc;font-size:19px}
.decision-tree-legend{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}
.decision-tree-legend span{display:flex;align-items:center;gap:6px;padding:6px 9px;border:1px solid var(--studio-border);border-radius:7px;color:#c5d3e8;font-size:11px;white-space:nowrap}
.decision-tree-legend i{width:9px;height:9px;border-radius:2px}
.legend-root{background:var(--studio-orange)}
.legend-decision{background:var(--studio-blue)}
.legend-terminal{background:var(--studio-green)}
.decision-tree-map-scroll{min-height:0;flex:1;overflow:auto;padding:0 16px}
.decision-tree-map-canvas{position:relative;min-width:690px;min-height:500px;padding:20px;border:1px solid #3b4b57;border-radius:14px;background-color:#1a2731;background-image:radial-gradient(#3a4b56 1px,transparent 1px);background-size:18px 18px}
.decision-tree-root-node{position:relative;z-index:1;display:grid;width:min(270px,90%);min-height:88px;justify-items:center;gap:5px;margin:0 auto;padding:13px;border:2px solid var(--studio-orange);border-radius:13px;background:#343328;color:#f5f3e8;text-align:center;transition:box-shadow .2s,transform .2s}
.decision-tree-root-node small{color:#f2d783;font-size:10px;font-weight:800}
.decision-tree-root-node strong{font-size:14px}
.decision-tree-root-node span{color:#d0d6d8;font-size:11px}
.decision-tree-root-connector{position:relative;height:45px;margin:0 50%}
.decision-tree-root-connector:before{position:absolute;left:50%;height:100%;border-left:2px solid #71818b;content:""}
.decision-tree-root-connector span{position:absolute;top:10px;left:calc(50% + 10px);padding:4px 9px;border:1px solid #526773;border-radius:20px;background:#263641;color:#d4e5ef;font-size:10px}
.decision-tree-map-fork{position:relative;width:68%;height:24px;margin:0 auto}
.decision-tree-map-fork:before{position:absolute;top:0;right:0;left:0;border-top:2px solid #71818b;content:""}
.decision-tree-map-fork:after{position:absolute;top:-45px;left:50%;height:45px;border-left:2px solid #71818b;content:""}
.decision-tree-map-branches{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}
.decision-tree-map-flow{position:relative;min-width:0;padding:15px 13px 16px;border:1px solid #3b4b57;border-radius:14px;background:#1c2933;transition:border-color .2s,opacity .2s}
.decision-tree-map-flow:before{position:absolute;top:-25px;left:50%;height:24px;border-left:2px solid #71818b;content:""}
.decision-tree-map-flow.quote-flow{--node-color:var(--studio-orange);--node-rgb:230,187,85}
.decision-tree-map-flow.tracking-flow{--node-color:var(--studio-blue);--node-rgb:131,182,223}
.decision-tree-map-flow.is-paused{opacity:.62}
.decision-tree-map-flow.is-current-flow{border-color:rgba(var(--node-rgb),.95);box-shadow:0 0 0 2px rgba(var(--node-rgb),.17),0 0 28px rgba(var(--node-rgb),.2);opacity:1}
.decision-tree-map-flow.is-current-flow:before,.decision-tree-map-flow.is-current-flow .map-node-connector{border-left-color:var(--node-color);box-shadow:0 0 8px rgba(var(--node-rgb),.55)}
.decision-tree-map-flow.is-current-flow .tracking-result-branches:before{border-top-color:var(--node-color);box-shadow:0 0 8px rgba(var(--node-rgb),.55)}
.decision-tree-map-flow.is-current-flow .tracking-result-branches:after{border-left-color:var(--node-color);box-shadow:0 0 8px rgba(var(--node-rgb),.55)}
.decision-tree-map-flow>header{display:flex;align-items:center;gap:9px;margin-bottom:13px;padding-bottom:10px;border-bottom:1px solid #3b4b57}
.flow-index{display:grid;width:27px;height:27px;place-items:center;border-radius:50%;background:var(--node-color);color:#152136;font-size:12px;font-weight:900}
.decision-tree-map-flow>header div{display:grid;flex:1;gap:2px}
.decision-tree-map-flow>header small{color:var(--node-color);font-size:10px;font-weight:800;letter-spacing:.09em}
.decision-tree-map-flow>header strong{color:#f1f5f9;font-size:14px}
.flow-status{padding:4px 6px;border:1px solid #4d806e;border-radius:20px;color:#a8dfc7;font-size:8px;font-weight:800}
.is-paused .flow-status{border-color:#786b4c;color:#e3d3a6}
.map-node{position:relative;display:grid;min-height:70px;align-content:center;justify-items:center;gap:6px;padding:11px 10px;border:1px solid rgba(var(--node-rgb),.7);border-radius:11px;background:rgba(var(--node-rgb),.09);text-align:center;transition:box-shadow .2s,border-color .2s,transform .2s}
.map-node small{color:var(--node-color);font-size:9px;font-weight:800;letter-spacing:.06em}
.map-node strong{color:#edf1f3;font-size:13px;line-height:1.4}
.decision-node{--node-color:var(--studio-blue);--node-rgb:131,182,223;background:rgba(131,182,223,.12)}
.terminal-node{--node-color:var(--studio-green);--node-rgb:105,198,166;border-color:rgba(105,198,166,.7);background:rgba(105,198,166,.12)}
.terminal-node small{color:#91dfbf}
.map-node.is-current,.decision-tree-root-node.is-current{z-index:1;border-color:var(--node-color,var(--studio-orange));box-shadow:0 0 0 2px rgba(var(--node-rgb,230,187,85),.2),0 0 18px rgba(var(--node-rgb,230,187,85),.24);transform:translateY(-1px)}
.decision-tree-root-node.is-current{--node-color:var(--studio-orange);--node-rgb:230,187,85}
.map-node-connector{position:relative;height:18px;width:50%;margin:0 auto;border-left:1px solid #71818b}
.quote-scope-branches{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:start}
.map-terminal-summary{min-height:53px;margin-top:12px;border-style:dashed}
.tracking-result-branches{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:8px;padding-top:18px}
.tracking-result-branches:before{position:absolute;top:0;left:25%;right:25%;border-top:1px solid #71818b;content:""}
.tracking-result-branches:after{position:absolute;top:-1px;left:50%;height:18px;border-left:1px solid #71818b;content:""}
.tracking-result-branches .map-node{min-height:94px}
.decision-tree-map-note{margin:10px 0 0;color:var(--studio-muted);font-size:11px;text-align:center}
.decision-tree-simulator{display:flex;flex-direction:column}
.simulator-header{display:flex;min-height:66px;align-items:center;gap:11px;padding:13px 16px;border-bottom:1px solid #3b4b57;background:#202d37}
.simulator-whatsapp-icon{display:grid;flex:0 0 37px;height:37px;place-items:center;border:1px solid #5c9b81;border-radius:50%;color:#91dfbf;font-size:21px}
.simulator-title{display:grid;min-width:0;flex:1;gap:3px}
.simulator-title h2{margin:0;color:#f8fafc;font-size:14px}
.simulator-title span{overflow:hidden;color:#b6d5e9;font-family:monospace;font-size:9px;text-overflow:ellipsis;white-space:nowrap}
.simulator-active-badge{padding:6px 8px;border:1px solid #5c9b81;border-radius:20px;color:#a8dfc7;font-size:8px;font-weight:900;letter-spacing:.04em;white-space:nowrap}
.simulator-copy{padding:6px 8px;border:1px solid #637984;border-radius:20px;background:#263641;color:#dbe8ee;font-size:9px;font-weight:800;white-space:nowrap;cursor:pointer}.simulator-copy:hover{border-color:#e6bb55;color:#f0d17b}
.simulator-disclaimer{padding:9px 14px;border-bottom:1px solid #3b4b57;background:#26343e;color:#c0cbd2;font-size:11px;line-height:1.5}
.simulator-chat{display:flex;min-height:0;flex:1;flex-direction:column;gap:14px;overflow:auto;padding:18px 14px;background-color:#1a2731;background-image:radial-gradient(#3a4b56 .7px,transparent .7px);background-size:18px 18px}
.simulator-empty{align-self:center;margin:auto;color:#b6c4cd;font-size:12px;line-height:1.6;text-align:center}
.simulator-message{display:flex;max-width:95%;align-items:flex-end;gap:8px}
.simulator-message.is-client{align-self:flex-end}
.simulator-avatar{display:grid;flex:0 0 25px;height:25px;place-items:center;border:1px solid #526773;border-radius:50%;background:#2a3943;color:#e5ebef;font-size:11px}
.simulator-avatar.client-avatar{border-color:#887441;background:#3a3528;color:#f0d17b;font-size:8px;font-weight:800}
.simulator-bubble{max-width:100%;padding:10px 12px;border:1px solid #465762;border-radius:4px 14px 14px;background:#283741;color:#f0f3f5}
.is-client .simulator-bubble{border-color:#806b3c;border-radius:14px 4px 14px 14px;background:#393528}
.simulator-bubble small{display:block;margin-bottom:5px;color:#b6d5e9;font-size:8px;font-weight:800;letter-spacing:.04em}
.simulator-bubble p{margin:0;font-size:13px;line-height:1.55;white-space:pre-line;overflow-wrap:anywhere}
.is-client .simulator-bubble p{color:#f4ecd5}
.simulator-input-row{display:flex;gap:7px;padding:11px 12px;border-top:1px solid #3b4b57;background:#202d37}
.simulator-input-row input{min-width:0;flex:1;padding:10px 12px;border:1px solid #637984;border-radius:11px;outline:none;background:#18252f;color:#f2f5f7;font-size:11px}
.simulator-input-row input:focus{border-color:var(--studio-orange);box-shadow:0 0 0 2px rgba(230,187,85,.28)}
.simulator-input-row input::placeholder{color:#b0bdc5}
.simulator-input-row button{padding:0 13px;border:1px solid #e6bb55;border-radius:11px;background:#e6bb55;color:#202b32;font-size:11px;font-weight:800;cursor:pointer}
.simulator-input-row button:hover{background:#f0d17b}
.simulator-input-row .simulator-reset{padding:0 10px;border-color:#596a76;background:#2a3943;color:#e4e9ec;font-size:17px}
.decision-tree-studio button:focus-visible{outline:2px solid var(--studio-orange);outline-offset:2px}
@media(max-width:1100px){
    .decision-tree-studio-grid{grid-template-columns:1fr}
    .decision-tree-map-panel,.decision-tree-simulator{height:auto;min-height:0}
    .decision-tree-map-canvas{min-height:530px}
    .simulator-chat{min-height:350px;max-height:520px}
}
@media(max-width:640px){
    .decision-tree-panel-header{align-items:flex-start;flex-direction:column;padding:17px 15px 13px}
    .decision-tree-panel-header h2{font-size:16px}
    .decision-tree-legend{justify-content:flex-start}
    .decision-tree-legend span{font-size:9px}
    .decision-tree-map-scroll{padding:0 10px}
    .decision-tree-map-canvas{min-width:650px;padding:16px}
    .decision-tree-map-branches{gap:12px}
    .decision-tree-map-flow{padding:12px 9px}
    .decision-tree-map-flow>header strong{font-size:12px}
    .flow-status{font-size:7px}
    .quote-scope-branches{grid-template-columns:1fr}
    .simulator-header{gap:8px;padding:11px 9px}
    .simulator-active-badge{max-width:88px;white-space:normal;text-align:center}
    .simulator-chat{min-height:300px;padding:14px 9px}
    .simulator-input-row{padding:9px}
    .simulator-input-row button{padding:0 10px}
}
</style>
