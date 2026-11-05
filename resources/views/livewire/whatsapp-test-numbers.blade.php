<div class="content test-numbers-page">
    <div class="welcome">
        <div>
            <h1>Números de prueba ✦</h1>
            <p>Administra contactos para probar campañas y respuestas automáticas sin escribir a clientes.</p>
        </div>
    </div>

    <section class="card test-number-manager">
        <div class="test-number-heading">
            <div>
                <h2>Contactos de prueba</h2>
                <p>Guarda el nombre y el teléfono con código de país para reconocer cada destino.</p>
            </div>
            <span class="test-number-count">{{ count($testNumbers) }} {{ count($testNumbers) === 1 ? 'contacto' : 'contactos' }}</span>
        </div>

        <form wire:submit="addTestNumber" class="test-number-form">
            <label class="test-number-field">
                Nombre del contacto
                <input wire:model="newTestName" type="text" class="input" placeholder="Ej. Mi celular" autocomplete="name" maxlength="80">
                @error('newTestName')<small class="chat-error">{{ $message }}</small>@enderror
            </label>
            <label class="test-number-field">
                Número con código de país
                <input wire:model="newTestNumber" type="tel" class="input" placeholder="+591 70000000" autocomplete="tel">
                @error('newTestNumber')<small class="chat-error">{{ $message }}</small>@enderror
            </label>
            <button type="submit" class="primary-btn">Guardar contacto</button>
        </form>

        <p class="test-number-hint">Se aceptan de 8 a 15 dígitos. El signo + y los espacios se normalizan al guardar.</p>
        @if (session()->has('testNumberSaved'))<p class="test-number-feedback" role="status">{{ session('testNumberSaved') }}</p>@endif

        <div class="test-number-list">
            @forelse($testNumbers as $entry)
                @php($isConfigured = $entry['number'] === preg_replace('/\D+/', '', (string) config('services.whatsapp.test_recipient')))
                <article class="test-number-row" wire:key="test-number-{{ $entry['number'] }}">
                    <div class="test-number-avatar">{{ mb_strtoupper(mb_substr($entry['name'], 0, 1)) }}</div>
                    <div class="test-number-details">
                        @if($editingTestNumber === $entry['number'])
                            <form wire:submit="saveTestName" class="test-number-edit-form">
                                <input wire:model="editingTestName" type="text" class="input" maxlength="80" aria-label="Nombre del contacto">
                                <button type="submit" class="secondary-btn">Guardar</button>
                                <button type="button" class="test-number-cancel" wire:click="$set('editingTestNumber', '')">Cancelar</button>
                                @error('editingTestName')<small class="chat-error">{{ $message }}</small>@enderror
                            </form>
                        @else
                            <strong>{{ $entry['name'] }}</strong>
                        @endif
                        <span>{{ $entry['number'] }}</span>
                    </div>
                    @if($isConfigured)
                        <span class="test-number-badge">Predeterminado</span>
                    @else
                        <div class="test-number-actions">
                            @if($editingTestNumber !== $entry['number'])
                                <button type="button" class="test-number-edit" wire:click="editTestName('{{ $entry['number'] }}')">Editar nombre</button>
                            @endif
                            <button type="button" class="secondary-btn" wire:click="removeTestNumber('{{ $entry['number'] }}')" wire:confirm="¿Quitar este contacto de prueba?">
                                Quitar
                            </button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="test-number-empty">
                    <strong>Aún no hay contactos de prueba</strong>
                    <span>Agrega un nombre y un número para comenzar a probar.</span>
                </div>
            @endforelse
        </div>
    </section>
</div>

<style>
    .test-numbers-page{max-width:1100px;margin:0 auto}
    .test-number-manager{padding:clamp(18px,3vw,30px);margin-top:22px}
    .test-number-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:24px}
    .test-number-heading h2{margin:0 0 7px;font-size:1.2rem;color:#172b4d}
    .test-number-heading p{margin:0;color:#64748b}
    .test-number-count{white-space:nowrap;border-radius:999px;padding:7px 12px;background:#eff6ff;color:#1d4ed8;font-size:.85rem;font-weight:700}
    .test-number-form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(220px,1fr) auto;align-items:end;gap:14px}
    .test-number-field{display:grid;gap:7px;color:#334155;font-size:.9rem;font-weight:600}
    .test-number-field .input{width:100%;min-height:44px}
    .test-number-hint{margin:11px 0 0;color:#64748b;font-size:.82rem}
    .test-number-feedback{margin:16px 0 0;padding:10px 12px;border-radius:8px;background:#ecfdf5;color:#047857}
    .test-number-list{display:grid;gap:10px;margin-top:24px}
    .test-number-row{display:flex;align-items:center;gap:13px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
    .test-number-avatar{display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:50%;background:#e0eaff;color:#1d4ed8;font-weight:800}
    .test-number-details{display:grid;gap:4px;min-width:0;flex:1}
    .test-number-details strong{color:#172b4d}
    .test-number-details span{color:#64748b;font-size:.9rem;letter-spacing:.02em}
    .test-number-row .secondary-btn{padding:8px 12px}
    .test-number-actions{display:flex;align-items:center;gap:12px}
    .test-number-edit{padding:6px 0;border:0;background:transparent;color:#2563eb;font:inherit;font-size:.85rem;font-weight:600;cursor:pointer}
    .test-number-edit-form{display:flex;align-items:center;flex-wrap:wrap;gap:8px}
    .test-number-edit-form .input{max-width:220px;min-height:38px}
    .test-number-edit-form .secondary-btn{padding:7px 10px}
    .test-number-cancel{border:0;background:transparent;color:#64748b;cursor:pointer}
    .test-number-badge{padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.78rem;font-weight:700}
    .test-number-empty{display:grid;gap:6px;padding:28px 16px;text-align:center;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b}
    .test-number-empty strong{color:#334155}
    body.night-mode .test-number-heading h2,body.night-mode .test-number-details strong{color:#e2e8f0}
    body.night-mode .test-number-heading p,body.night-mode .test-number-hint,body.night-mode .test-number-details span{color:#94a3b8}
    body.night-mode .test-number-count{background:#172554;color:#bfdbfe}
    body.night-mode .test-number-field{color:#cbd5e1}
    body.night-mode .test-number-row,body.night-mode .test-number-empty{background:#111c31;border-color:#334155}
    body.night-mode .test-number-avatar{background:#1e3a8a;color:#bfdbfe}
    body.night-mode .test-number-badge{background:#263449;color:#cbd5e1}
    body.night-mode .test-number-edit{color:#93c5fd}
    body.night-mode .test-number-cancel{color:#94a3b8}
    body.night-mode .test-number-empty strong{color:#e2e8f0}
    body.night-mode .test-number-feedback{background:#064e3b;color:#a7f3d0}
    @media(max-width:700px){.test-number-form{grid-template-columns:1fr}.test-number-form .primary-btn{width:100%}}
    @media(max-width:520px){.test-number-heading{flex-direction:column}.test-number-row{gap:10px;padding:12px;flex-wrap:wrap}.test-number-details{flex-basis:calc(100% - 60px)}.test-number-actions{width:100%;justify-content:flex-end}.test-number-row .secondary-btn{padding:7px 9px}}
</style>
