import './bootstrap';

const dashboardStyles = `body{background:#080c10}.sidebar{background:#fff!important;width:265px!important;border-right:1px solid #dbe2eb!important;padding:14px 10px!important}.sidebar .brand{padding:0 7px 14px!important;margin-bottom:10px!important;border-bottom:1px solid #e5eaf0}.sidebar .brand-mark{background:#ffcb05!important;color:#111820!important;font-size:13px}.sidebar .brand small{color:#f59e0b}.nav-label{display:flex;align-items:center;gap:8px;color:#3f4d62;font-size:12px;padding:11px 16px;margin:3px 0;text-transform:uppercase;letter-spacing:.4px;cursor:pointer}.nav-label small{display:none}.nav-label b{font-size:11px;margin-left:auto}.nav-label.collapsed b{transform:rotate(-90deg)}.nav-item{color:#596980!important;border-radius:8px;margin:2px 0;padding:9px 15px 9px 45px!important;font-weight:500}.nav-item:hover{background:#fff8e8!important;color:#d97706!important}.nav-item.active{background:#fff8e8!important;border:1px solid #ffcb05;color:#d97706!important}.sidebar-bottom{border-top:1px solid #e5eaf0}.main{background:#f7f9fc!important}.content{color:#111820!important;padding:28px 24px!important}.welcome h1{font-size:25px}.welcome h1::after{content:' ✧';color:#f59e0b}.welcome p{color:#60728b!important}.primary-btn{background:#f59e0b!important;color:#111820!important;border-radius:12px}.card{border-color:#dbe3ed!important;box-shadow:0 2px 5px #1d35570b;border-radius:14px}.stat-value,.card-title h3{color:#111820!important}.stat-head{color:#60728b!important;text-transform:uppercase;letter-spacing:.5px}.stat-head b{background:#fff2d6;color:#f59e0b}.chart{background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,#dbe3ed 52px)!important;border-bottom-color:#cbd5e1!important}.chart i{background:#f59e0b!important}.activity{border-bottom-color:#e5eaf0!important}.search{background:#f1f5f9!important;border-color:#dbe3ed!important;width:450px!important}.profile{border-left:0!important}`;

window.addEventListener('system-notification', (event) => { const notice = document.createElement('div'); notice.className = 'system-notification'; notice.textContent = event.detail?.message || 'No se pudo completar la operación.'; document.body.appendChild(notice); setTimeout(() => notice.remove(), 8000); });

if (document.querySelector('.sidebar')) {
    if (location.pathname.startsWith('/campanas-postales')) {
        const campaignStyle = document.createElement('style'); campaignStyle.textContent = '.content label:has(input[readonly]){display:none!important}'; document.head.appendChild(campaignStyle);
    }
    document.querySelectorAll('.sidebar .nav-item').forEach((item) => {
        if (item.textContent.trim().toLowerCase() === 'chat') item.href = '/chat';
          if (item.textContent.trim().toLowerCase() === 'configuraciones') item.href = '/configuraciones';
          if (item.textContent.trim().toLowerCase() === 'pruebas') item.href = '/pruebas';
          if (item.textContent.trim().toLowerCase() === 'asistente ia') item.href = '/assistant';
    });
      if (!document.querySelector('.sidebar .postal-campaign-link')) {
        const link = document.createElement('a');
        link.href = '/campanas-postales';
        link.className = 'nav-item postal-campaign-link';
        link.innerHTML = '<span>▤</span><em>Campañas Postales</em>';
        if (location.pathname === '/campanas-postales') link.classList.add('active');
        document.querySelector('.sidebar-bottom')?.before(link);
      }
      if (!document.querySelector('.sidebar .tests-link')) {
        const tests = document.createElement('a'); tests.href='/pruebas'; tests.className='nav-item tests-link'; tests.innerHTML='<span>✓</span><em>Pruebas</em>'; if(location.pathname==='/pruebas') tests.classList.add('active'); document.querySelector('.sidebar-bottom')?.before(tests);
      }
      if (!document.querySelector('.sidebar .assistant-link')) {
        const assistantLink = document.createElement('a'); assistantLink.href='/assistant'; assistantLink.className='nav-item assistant-link'; assistantLink.innerHTML='<span>✦</span><em>Asistente IA</em>'; if(location.pathname==='/assistant') assistantLink.classList.add('active'); document.querySelector('.sidebar-bottom')?.before(assistantLink);
      }
    if (!document.querySelector('.sidebar .national-campaign-link')) {
        const national = document.createElement('a'); national.href='/campanas-postales/nacional'; national.className='nav-item national-campaign-link'; national.innerHTML='<span>▤</span><em>Campañas Nacional</em>'; if(location.pathname==='/campanas-postales/nacional') national.classList.add('active');
        const international = document.createElement('a'); international.href='/campanas-postales/internacional'; international.className='nav-item international-campaign-link'; international.innerHTML='<span>▤</span><em>Campañas Internacional</em>'; if(location.pathname==='/campanas-postales/internacional') international.classList.add('active');
        const groups = document.querySelectorAll('.sidebar .nav-label'); const target = groups[0]?.nextElementSibling; target?.parentElement?.insertBefore(national, target); const internationalGroup = Array.from(groups).find((el) => el.textContent.toLowerCase().includes('internacional')); internationalGroup?.parentElement?.insertBefore(international, internationalGroup.nextElementSibling);
    }
    const style = document.createElement('style');
      style.textContent = dashboardStyles;
      document.head.appendChild(style);
      const responsiveStyle = document.createElement('style');
      responsiveStyle.textContent = '.content{width:100%!important;max-width:none!important;min-width:0}.main{width:calc(100% - 265px)}@media(max-width:900px){.sidebar{width:70px!important;padding:20px 9px!important}.main{width:calc(100% - 70px)}}@media(max-width:560px){.sidebar{width:58px!important}.main{width:calc(100% - 58px)}}';
      document.head.appendChild(responsiveStyle);

    const darkStyles = `.sidebar{background:#111827!important;border-right-color:#243149!important}.sidebar .brand{border-bottom-color:#243149}.nav-label,.nav-item{color:#cbd5e1!important}.nav-label small{color:#8492aa}.nav-item:hover,.nav-item.active{background:#292d31!important;color:#ffcb05!important}.sidebar-bottom{border-top-color:#243149}.main{background:#030817!important}.topbar{background:#0b1325!important;border-bottom-color:#1f2b40!important}.search{background:#182338!important;border-color:#2b3a52!important;color:#9aa9c0}.content{color:#f8fafc!important}.welcome p{color:#7da0c7!important}.card{background:#0c1528!important;border-color:#233451!important;box-shadow:none}.stat-head,.card-title h3,.stat-value{color:#f8fafc!important}.stat-head{color:#8eabd0!important}.chart{background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,#20304a 52px)!important;border-bottom-color:#2c3c57!important}.activity{border-bottom-color:#233451!important}.activity p{color:#8eabd0}.profile strong{color:#f8fafc}.topbar .profile small{color:#8eabd0}`;

    const themeButton = document.createElement('button');
    themeButton.type = 'button';
    themeButton.textContent = '◐';
    themeButton.title = 'Cambiar modo día/noche';
    themeButton.style.cssText = 'border:1px solid #dbe3ed;background:#f1f5f9;border-radius:10px;width:36px;height:36px;cursor:pointer;font-size:17px;margin-right:12px';
    const profile = document.querySelector('.topbar .profile');
    profile?.parentElement?.insertBefore(themeButton, profile);
    themeButton.addEventListener('click', () => {
        document.body.classList.toggle('night-mode');
        const night = document.body.classList.contains('night-mode');
        localStorage.setItem('crm-theme', night ? 'night' : 'day');
        style.textContent = night ? dashboardStyles + darkStyles : dashboardStyles;
        themeButton.textContent = night ? '☀' : '◐';
        themeButton.style.background = night ? '#182338' : '#f1f5f9';
        themeButton.style.color = night ? '#ffcb05' : '#243149';
    });

    if (localStorage.getItem('crm-theme') === 'night') {
        document.body.classList.add('night-mode');
        style.textContent = dashboardStyles + darkStyles;
        themeButton.textContent = '☀';
        themeButton.style.background = '#182338';
        themeButton.style.color = '#ffcb05';
    }

    document.querySelectorAll('.sidebar .nav-label').forEach((section) => {
        const label = section.textContent.trim().toLowerCase();
        if (!label.includes('sistema') && !(location.pathname === '/chat' && label.includes('clientes'))) {
            section.classList.add('collapsed');
            let item = section.nextElementSibling;
            while (item && !item.classList.contains('nav-label')) {
                item.hidden = true;
                item = item.nextElementSibling;
            }
        }
        section.addEventListener('click', () => {
            section.classList.toggle('collapsed');
            let next = section.nextElementSibling;
            while (next && !next.classList.contains('nav-label')) {
                next.hidden = section.classList.contains('collapsed');
                next = next.nextElementSibling;
            }
        });
    });
}

if (document.querySelector('.dashboard-grid') && localStorage.getItem('crm-aiMode') !== 'false') {
    const assistant = document.createElement('button');
    assistant.textContent = '✦ Asistente IA';
    assistant.className = 'primary-btn';
    assistant.style.cssText = 'position:fixed;right:22px;bottom:22px;z-index:20';
    document.body.appendChild(assistant);
    const panel = document.createElement('section');
    panel.innerHTML = `<div class="ai-head"><strong>Asistente IA</strong><button type="button" class="ai-close">×</button></div><div class="ai-messages"><div class="ai-bubble">Hola, soy el asistente de Conversa OS. Puedo ayudarte con clientes, oportunidades, contactos y actividades del CRM.</div></div><form class="ai-form"><input placeholder="Escribe tu consulta aquí..." autocomplete="off"><button>➤</button></form>`;
    panel.className = 'ai-panel';
    document.body.appendChild(panel);
    const style = document.createElement('style');
    style.textContent = `.ai-panel{display:none;position:fixed;right:22px;bottom:78px;width:360px;height:480px;background:#fffdf5;border:1px solid #e8c76a;border-radius:18px;box-shadow:0 18px 45px #17203338;z-index:30;overflow:hidden}.ai-head{height:64px;background:linear-gradient(135deg,#ffcb05,#f59e0b);display:flex;align-items:center;justify-content:space-between;padding:0 18px;color:#111820;font-size:17px}.ai-close{border:1px solid #9a6b00;background:#ffffff30;border-radius:8px;font-size:22px;width:30px;height:30px;cursor:pointer}.ai-messages{height:350px;padding:18px;overflow:auto;background:#fffdf5}.ai-bubble{background:#fff;border:1px solid #ecd28d;border-radius:4px 16px 16px 16px;padding:14px;color:#5f5a52;line-height:1.5;font-size:13px}.ai-form{display:flex;gap:8px;padding:12px;background:#fff;border-top:1px solid #ecd28d}.ai-form input{flex:1;border:2px solid #f59e0b;border-radius:12px;padding:11px;outline:none}.ai-form button{width:42px;border:0;border-radius:10px;background:#1e4c9b;color:#fff;font-size:19px;cursor:pointer}@media(max-width:600px){.ai-panel{right:12px;left:12px;bottom:78px;width:auto}}`;
    document.head.appendChild(style);
    const messages = panel.querySelector('.ai-messages');
    const form = panel.querySelector('.ai-form');
    assistant.addEventListener('click', () => { panel.style.display = panel.style.display === 'flex' ? 'none' : 'flex'; panel.style.flexDirection = 'column'; if (panel.style.display === 'flex') panel.querySelector('input').focus(); });
    panel.querySelector('.ai-close').addEventListener('click', () => panel.style.display = 'none');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('input'); const question = input.value.trim(); if (!question) return;
        messages.insertAdjacentHTML('beforeend', `<div class="ai-bubble" style="margin:12px 0 12px auto;background:#fff1bd;border-radius:16px 4px 16px 16px">${question.replace(/</g, '&lt;')}</div>`);
        input.value = ''; messages.scrollTop = messages.scrollHeight;
        const thinking = document.createElement('div'); thinking.className = 'ai-bubble'; thinking.textContent = 'Consultando Ollama...'; messages.appendChild(thinking);
        try { const token = document.querySelector('meta[name="csrf-token"]')?.content || ''; const response = await fetch('/api/assistant/chat', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({message:question})}); const data = await response.json(); thinking.textContent = data.reply || data.message || 'No hubo respuesta.'; } catch { thinking.textContent = 'No se pudo conectar con Ollama.'; }
        messages.scrollTop = messages.scrollHeight;
    });
}
// Notificación de mensajes entrantes: el webhook debe disparar este evento con
// {name, message, image}: window.dispatchEvent(new CustomEvent('crm:incoming-message', {detail: data}));
(function messageNotifications() {
  let audioContext;
  const unlock = () => {
    audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
    audioContext.resume();
  };
  document.addEventListener('click', unlock, { once: true });
  window.addEventListener('crm:incoming-message', async ({ detail = {} }) => {
    unlock();
    if (audioContext) {
      const start = audioContext.currentTime;
      [0, 0.16].forEach((delay, index) => {
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.frequency.value = index ? 880 : 660;
        gain.gain.setValueAtTime(0.0001, start + delay);
        gain.gain.exponentialRampToValueAtTime(0.16, start + delay + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + delay + 0.13);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start(start + delay);
        oscillator.stop(start + delay + 0.14);
      });
    }
    if ('Notification' in window) {
      if (Notification.permission === 'default') await Notification.requestPermission();
      if (Notification.permission === 'granted') {
        const notification = new Notification(detail.name || 'Nuevo mensaje', {
          body: detail.message || 'Tienes un nuevo mensaje de WhatsApp',
          icon: detail.image || '/favicon.ico',
          tag: 'crm-incoming-message'
        });
        notification.onclick = () => { window.focus(); notification.close(); };
      }
    }
  });
})();

// La cuenta administradora puede ver todos los módulos desde cualquier vista.
document.querySelectorAll('.sidebar .nav-label').forEach((section) => {
  section.classList.remove('collapsed');
  let item = section.nextElementSibling;
  while (item && !item.classList.contains('nav-label')) {
    item.hidden = false;
    item = item.nextElementSibling;
  }
});
