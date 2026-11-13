<div class="content operations-page">
    <div class="welcome"><div><h1>Módulo de tarifas</h1><p>Consulta los tarifarios oficiales y calcula cotizaciones EMS.</p></div></div>
    <section class="card">
        <div class="operations-tabs" role="tablist" aria-label="Tipo de tarifa">
            <button type="button" class="secondary-btn {{ $activeTab === 'delivery' ? 'is-active' : '' }}" wire:click="$set('activeTab', 'delivery')">Delivery Express · Tarifario API</button>
            <button type="button" class="secondary-btn {{ $activeTab === 'ems' ? 'is-active' : '' }}" wire:click="$set('activeTab', 'ems')">EMS · Consultar tarifa</button>
        </div>
        @if($activeTab === 'delivery')
            <div class="card-title"><div><h2>Tarifas de Delivery Express</h2><p>Datos consultados directamente desde el tarifario del proveedor. Este módulo no modifica sus tarifas.</p></div><button type="button" class="primary-btn" wire:click="loadDeliveryRates" wire:loading.attr="disabled" wire:target="loadDeliveryRates"><span wire:loading.remove wire:target="loadDeliveryRates">Consultar API</span><span wire:loading wire:target="loadDeliveryRates">Consultando…</span></button></div>
            @if($ratesError)<div class="operation-alert error" role="alert">{{ $ratesError }}</div>@endif
            @if($ratesLoadedAt)<div class="operation-alert success" role="status">Consulta realizada: {{ $ratesLoadedAt }} · {{ count($deliveryRates) }} tarifas recibidas.</div>@endif
            @if($deliveryRates)
                <div class="delivery-rate-filters">
                    <label>Departamento
                        <select class="input" wire:model.live="rateDepartment">
                            <option value="">Todos los departamentos</option>
                            @foreach($this->deliveryDepartments as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Modalidad
                        <select class="input" wire:model.live="rateMode">
                            <option value="">Todas las modalidades</option>
                            @foreach($this->deliveryModes as $mode)
                                <option value="{{ $mode }}">{{ $mode }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="operation-search">Buscar tarifa
                        <input class="input" type="search" wire:model.live.debounce.300ms="rateSearch" placeholder="Ej. La Paz o Bs. 25">
                    </label>
                </div>
                <div class="table-wrap"><table class="operations-table">
                    <thead><tr><th>Origen</th><th>Destino</th><th>Modalidad</th><th>Tarifas</th></tr></thead>
                    <tbody>
                    @forelse($this->filteredRates as $rate)
                        <tr wire:key="delivery-rate-{{ $this->filteredRates->firstItem() + $loop->index }}">
                            <td>{{ data_get($rate, 'origen.nombre_origen', data_get($rate, 'origen', '—')) }}</td>
                            <td>{{ data_get($rate, 'destino.nombre_destino', data_get($rate, 'destino', '—')) }}</td>
                            <td>{{ data_get($rate, 'servicio_extra.nombre', data_get($rate, 'servicio_extra', '—')) }}</td>
                            <td class="rate-values">
                                @foreach(['peso1' => 'Paquete', 'peso2' => 'Peso 2', 'peso3' => 'Peso 3', 'peso_extra' => 'Extra'] as $key => $label)
                                    @if(isset($rate[$key]))<span>{{ $label }}: Bs. {{ $rate[$key] }}</span>@endif
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-row">No hay tarifas que coincidan con los filtros seleccionados.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                @if($this->filteredRates->total() > 0)
                    <div class="delivery-rates-pagination">
                        <span>Mostrando {{ $this->filteredRates->firstItem() }}–{{ $this->filteredRates->lastItem() }} de {{ $this->filteredRates->total() }} tarifas</span>
                        {{ $this->filteredRates->links() }}
                    </div>
                @endif
            @elseif($ratesLoadedAt)
                <div class="empty-row">La API no tiene tarifas disponibles.</div>
            @else
                <div class="empty-row">Consulta la API para cargar el tarifario vigente.</div>
            @endif
        @else
            <div class="card-title"><div><h2>Consultar tarifa EMS</h2><p>EMS se calcula en tiempo real con Postar; el proveedor no expone un catálogo descargable desde esta API.</p></div></div>
            @if($emsError)<div class="operation-alert error" role="alert">{{ $emsError }}</div>@endif
            @if($errors->has('dimensions'))<div class="operation-alert error" role="alert">{{ $errors->first('dimensions') }}</div>@endif
            <form class="ems-quote-form" wire:submit="quoteEms">
                <label>Servicio
                    <select class="input" wire:model.live="category"><option value="EMS NAT">EMS Nacional</option><option value="EMS INT">EMS Internacional</option></select>
                </label>
                <label>Destino
                    <select class="input" wire:model="destination" required>
                        <option value="">Selecciona {{ $category === 'EMS NAT' ? 'un departamento' : 'un país' }}</option>
                        @foreach($this->emsDestinations as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('destination')<span class="ems-field-error">{{ $message }}</span>@enderror
                </label>
                <label>Peso (kg)
                    <input class="input" type="number" min="0.01" step="0.01" wire:model="weight" required>
                    @error('weight')<span class="ems-field-error">{{ $message }}</span>@enderror
                </label>
                <label>Largo (cm) <input class="input" type="number" min="0.01" step="0.01" wire:model="length"></label>
                <label>Ancho (cm) <input class="input" type="number" min="0.01" step="0.01" wire:model="width"></label>
                <label>Alto (cm) <input class="input" type="number" min="0.01" step="0.01" wire:model="height"></label>
                <div class="form-actions">
                    <button class="primary-btn" type="submit" wire:loading.attr="disabled" wire:target="quoteEms">
                        <span wire:loading.remove wire:target="quoteEms">Consultar tarifa</span>
                        <span wire:loading wire:target="quoteEms">Consultando…</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
    @if($showEmsModal && $emsQuote && $activeTab === 'ems')
        <div class="ems-quote-overlay" wire:click.self="closeEmsModal" wire:keydown.escape.window="closeEmsModal">
            <section class="ems-quote-modal" role="dialog" aria-modal="true" aria-labelledby="ems-quote-title" wire:key="ems-quote-result">
                <div class="ems-quote-modal-head">
                    <div>
                        <h2 id="ems-quote-title">Resultado de cotización EMS</h2>
                        <p>Tarifa consultada con Postar.</p>
                    </div>
                    <button class="ems-quote-close" type="button" wire:click="closeEmsModal" aria-label="Cerrar">×</button>
                </div>
                <div class="ems-quote-modal-body">
                    <div class="ems-quote-result" role="status" aria-live="polite">
                        <strong>{{ $emsQuote['category'] }} · Bs. {{ number_format($emsQuote['tariff'], 2) }}</strong>
                        <span>Destino: {{ $this->emsDestinations[$emsQuote['destination']] ?? $emsQuote['destination'] }} · Peso: {{ number_format($emsQuote['weight'], 2) }} kg · Moneda: {{ $emsQuote['currency'] }}</span>
                    </div>
                </div>
            </section>
        </div>
    @endif
    <style>
        .operations-page{display:grid;gap:18px}.operations-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}.operations-tabs .secondary-btn{margin:0}.operations-tabs .is-active{border-color:#f59e0b;background:#fffbeb;color:#92400e}
        .operation-alert{display:grid;gap:5px;margin:12px 0;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px}.operation-alert.success{background:#f0fdf4;border-color:#86efac;color:#166534}.operation-alert.error{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
        .delivery-rate-filters{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.delivery-rate-filters label,.operation-search{display:grid;gap:7px}.operation-search{margin:0}.table-wrap{overflow:auto}.operations-table{width:100%;border-collapse:collapse;min-width:800px}.operations-table th,.operations-table td{padding:12px;text-align:left;border-bottom:1px solid #dbe3ed;vertical-align:top}.operations-table th{background:#f1f5f9}.rate-values{display:grid;gap:4px}.empty-row{padding:24px;color:#64748b;text-align:center}
        .delivery-rates-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 4px;color:#64748b;font-size:13px}.delivery-rates-pagination nav{margin-left:auto}
        .ems-quote-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.ems-quote-form label{display:grid;gap:7px}.form-actions{display:flex;align-items:end}
        .ems-quote-overlay{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:16px;background:rgba(2,8,23,.82);backdrop-filter:blur(4px)}
        .ems-quote-modal{width:min(760px,96vw);max-height:calc(100vh - 32px);overflow:auto;margin:0;border:1px solid #526373!important;border-radius:16px;background:#334352!important;background-image:none!important;color:#e2e8f0!important;box-shadow:0 24px 70px rgba(2,8,23,.55)}
        .ems-quote-modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:20px 24px;border-bottom:1px solid #526373!important;background:#334352!important;background-image:none!important}
        .ems-quote-modal-head h2{margin:0;color:#f1f5f9!important;font-size:1.25rem}.ems-quote-modal-head p{margin:6px 0 0;color:#cbd5df!important}
        .ems-quote-close{flex:none;width:36px;height:36px;border:1px solid #facc15;border-radius:9px;background:#facc15;color:#0b192c;font-size:26px;line-height:1;cursor:pointer}
        .ems-quote-modal-body{padding:22px 24px;background:#334352!important;color:#e2e8f0!important}.ems-quote-form label{color:#e2e8f0}.ems-quote-form .input{min-height:46px;background:#1f2a34!important;color:#f8fafc!important;border:1px solid #526373!important;color-scheme:dark}.ems-quote-form .input:focus{border-color:#dfaa43!important;outline:2px solid rgba(223,170,67,.25);outline-offset:1px}
        .ems-field-error{color:#fecaca;font-size:.875rem}.ems-quote-result{display:grid;gap:7px;margin-top:18px;padding:16px;border:1px solid #657687!important;border-radius:10px;background:#3b4b59!important;color:#e2e8f0!important}
        .ems-quote-result strong{color:#f3c969!important;font-size:1.1rem}.ems-quote-result span{color:#e2e8f0!important}
        .ems-quote-modal .operation-alert.success{background:#3b4b59!important;border-color:#657687!important;color:#e2e8f0!important}
        body.night-mode .operations-tabs .is-active{background:#3a2b0b;color:#fde68a}.night-mode .operation-alert.success{background:#10291e;border-color:#276749;color:#bbf7d0}.night-mode .operation-alert.error{background:#301919;border-color:#7f1d1d;color:#fecaca}.night-mode .operations-table th{background:#172640}.night-mode .operations-table td,.night-mode .operations-table th{border-color:#334766}
        @media(max-width:750px){.ems-quote-form,.delivery-rate-filters{grid-template-columns:1fr}.ems-quote-modal-head,.ems-quote-modal-body{padding:16px}.delivery-rates-pagination{align-items:flex-start;flex-direction:column}.delivery-rates-pagination nav{margin-left:0}}
    </style>
</div>
