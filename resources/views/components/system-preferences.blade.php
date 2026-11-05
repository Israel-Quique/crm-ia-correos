@php
    $whatsappTestMode = filter_var(\Illuminate\Support\Facades\DB::table('app_settings')->where('key', 'test_mode')->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
    $whatsappAiEnabled = filter_var(\Illuminate\Support\Facades\DB::table('app_settings')->where('key', 'ai_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
    $automaticResponsesEnabled = filter_var(\Illuminate\Support\Facades\DB::table('app_settings')->where('key', 'automatic_responses_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
    $confirmActions = filter_var(\Illuminate\Support\Facades\DB::table('app_settings')->where('key', 'confirm_actions')->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
@endphp
<section id="systemPreferences" class="card settings-card preferences system-preferences-card">
    <div class="settings-heading">
        <div class="settings-icon">⚙</div>
        <div><h2>Preferencias del sistema</h2><p>Configura el entorno de trabajo para tu equipo.</p></div>
    </div>
    <label class="setting-row">
        <span class="setting-icon">✎</span>
        <span class="setting-copy"><strong>Modo prueba</strong><small>Limita las respuestas automáticas al número de prueba configurado.</small></span>
        <span class="switch"><input id="testMode" type="checkbox" @checked($whatsappTestMode)><i></i></span>
    </label>
    <label class="setting-row">
        <span class="setting-icon">♙</span>
        <span class="setting-copy"><strong>Asistente IA</strong><small>Permite que la IA genere respuestas cuando ninguna regla automática coincide.</small></span>
        <span class="switch"><input id="aiMode" type="checkbox" @checked($whatsappAiEnabled)><i></i></span>
    </label>
    <label class="setting-row">
        <span class="setting-icon">↻</span>
        <span class="setting-copy"><strong>Respuestas automáticas</strong><small>Activa o desactiva las respuestas configuradas por palabras y frases activadoras.</small></span>
        <span class="switch"><input id="automaticResponsesMode" type="checkbox" @checked($automaticResponsesEnabled)><i></i></span>
    </label>
    <label class="setting-row">
        <span class="setting-icon">♢</span>
        <span class="setting-copy"><strong>Confirmar acciones sensibles</strong><small>Solicita confirmación antes de sincronizar información.</small></span>
        <span class="switch"><input id="confirmActions" type="checkbox" @checked($confirmActions)><i></i></span>
    </label>
    <button class="primary-btn" id="saveSettings" type="button">Guardar configuración</button>
    <span id="savedMessage" class="saved-message" style="display:none" role="status" aria-live="polite"></span>
</section>
<style>
.system-preferences-card{padding:18px;background:#0d172b;border:1px solid #263858;color:#f8fafc}
.system-preferences-card .settings-heading{display:flex;align-items:center;gap:10px;border-bottom:1px solid #263858;padding-bottom:12px}
.system-preferences-card .settings-heading h2{font-size:15px;margin:0}
.system-preferences-card .settings-heading p{color:#94a3b8;font-size:12px;margin:3px 0 0}
.system-preferences-card .settings-icon{width:28px;height:28px;display:grid;place-items:center;color:#dbeafe}
.system-preferences-card .setting-row{display:flex;align-items:center;gap:10px;padding:9px;margin:8px 0;border:1px solid #263858;border-radius:9px;background:#111c31;cursor:pointer}
.system-preferences-card .setting-icon{width:28px;height:28px;display:grid;place-items:center;background:#263858;border-radius:6px;color:#f8fafc}
.system-preferences-card .setting-copy{flex:1}
.system-preferences-card .setting-copy strong,.system-preferences-card .setting-copy small{display:block}
.system-preferences-card .setting-copy small{color:#94a3b8;font-size:11px;margin-top:2px}
.system-preferences-card .switch input{position:absolute;opacity:0;width:1px;height:1px}
.system-preferences-card .switch i{display:block;width:38px;height:22px;border-radius:20px;background:#34435d;position:relative}
.system-preferences-card .switch i:after{content:'';position:absolute;width:16px;height:16px;top:3px;left:3px;border-radius:50%;background:#fff;transition:.2s}
.system-preferences-card .switch input:checked+i{background:#c79532}
.system-preferences-card .switch input:checked+i:after{transform:translateX(16px)}
.system-preferences-card .primary-btn{background:#f59e0b;color:#111820;padding:10px 15px;margin-top:4px}
.system-preferences-card .saved-message{color:#4ade80;margin-left:10px;font-size:12px}
body:not(.night-mode) .system-preferences-card{background:#fff;color:#172033;border-color:#dbe3ed}
body:not(.night-mode) .system-preferences-card .settings-heading{border-color:#dbe3ed}
body:not(.night-mode) .system-preferences-card .settings-heading h2{color:#172033}
body:not(.night-mode) .system-preferences-card .settings-heading p,body:not(.night-mode) .system-preferences-card .setting-copy small{color:#64748b}
body:not(.night-mode) .system-preferences-card .setting-row{background:#f8fafc;border-color:#dbe3ed}
body:not(.night-mode) .system-preferences-card .setting-copy strong{color:#172033}
@media(max-width:600px){.system-preferences-card .setting-row{align-items:flex-start}.system-preferences-card .switch{margin-left:auto}}
</style>
<script>
(() => {
    const root = document.getElementById('systemPreferences');
    const button = root.querySelector('#saveSettings');
    const message = root.querySelector('#savedMessage');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const serverPreferences = {
        testMode: @json($whatsappTestMode),
        aiMode: @json($whatsappAiEnabled),
        automaticResponsesMode: @json($automaticResponsesEnabled),
        confirmActions: @json($confirmActions),
    };

    Object.entries(serverPreferences).forEach(([key, value]) => localStorage.setItem(`crm-${key}`, String(value)));
    button.addEventListener('click', async () => {
        const values = {
            test_mode: root.querySelector('#testMode').checked,
            ai_enabled: root.querySelector('#aiMode').checked,
            automatic_responses_enabled: root.querySelector('#automaticResponsesMode').checked,
            confirm_actions: root.querySelector('#confirmActions').checked,
        };
        button.disabled = true;
        message.style.display = 'inline';
        message.style.color = '#f87171';
        message.textContent = 'Guardando configuración...';

        try {
            const response = await fetch(@json(route('settings.preferences')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(values),
            });
            if (!response.ok) {
                throw new Error('No se pudo guardar la configuración.');
            }
            await response.json();
            localStorage.setItem('crm-testMode', String(values.test_mode));
            localStorage.setItem('crm-aiMode', String(values.ai_enabled));
            localStorage.setItem('crm-automaticResponsesMode', String(values.automatic_responses_enabled));
            localStorage.setItem('crm-confirmActions', String(values.confirm_actions));
            message.style.color = '#4ade80';
            message.textContent = 'Configuración guardada';
        } catch (error) {
            message.textContent = error.message || 'No se pudo guardar la configuración.';
        } finally {
            button.disabled = false;
            setTimeout(() => message.style.display = 'none', 4000);
        }
    });
})();
</script>
