import './bootstrap';

const sidebarNav = document.querySelector('.sidebar-nav');
if (sidebarNav && !sidebarNav.querySelector('[data-dashboard-link]')) {
    const dashboardLink = document.createElement('a');
    dashboardLink.className = `nav-item${window.location.pathname === '/dashboard' ? ' active' : ''}`;
    dashboardLink.href = '/dashboard';
    dashboardLink.dataset.dashboardLink = 'true';
    dashboardLink.innerHTML = '<span>⌂</span><em>Panel principal</em>';
    sidebarNav.prepend(dashboardLink);
}
if (sidebarNav && !sidebarNav.querySelector('[data-audit-link]')) {
    const auditLink = document.createElement('a');
    auditLink.className = `nav-item${window.location.pathname === '/auditoria' ? ' active' : ''}`;
    auditLink.href = '/auditoria';
    auditLink.dataset.auditLink = 'true';
    auditLink.innerHTML = '<span>☷</span><em>Historial y auditoría</em>';
    sidebarNav.append(auditLink);
}

document.querySelectorAll('.sidebar-nav .nav-group').forEach((group) => {
    group.addEventListener('toggle', () => {
        if (group.open) {
            document.querySelectorAll('.sidebar-nav .nav-group').forEach((other) => {
                if (other !== group) other.open = false;
            });
        }
    });
});

const dashboardStyles = `
body{background:#fffbeb;color:#0b192c}
.main{background-color:#eab308!important;background-image:linear-gradient(rgba(11,25,44,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(11,25,44,.045) 1px,transparent 1px),radial-gradient(ellipse at 85% 15%,rgba(11,25,44,.3),transparent 38%),linear-gradient(110deg,#eab308 0%,#facc15 54%,#eab308 100%)!important;background-size:40px 40px,40px 40px,auto,auto!important}
.content{min-width:0;color:#0b192c!important;padding:30px 28px!important;background:radial-gradient(ellipse at 95% 0%,rgba(250,204,21,.11),transparent 38%)}
.app-shell .content h1,.app-shell .content h2,.app-shell .content h3{font-family:'Space Grotesk','DM Sans',sans-serif}
.app-shell .content h1{color:#0b192c}
.app-shell .content .welcome{display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:74px;padding:10px 16px!important}
.app-shell .content .welcome h1{line-height:1.2!important}
.app-shell .content .welcome p{line-height:1.4!important}
.app-shell .content .welcome>.primary-btn{margin-left:0!important}
.app-shell .content.dashboard-page>.welcome{padding:14px 20px!important}
.app-shell .content .welcome h1{margin:0;color:#0b192c!important;font-size:clamp(22px,2.2vw,30px);font-weight:800;letter-spacing:-.45px}
.app-shell .content .welcome p{margin:5px 0 0;color:#52657e!important;font-size:13px}
.app-shell .content .card,.app-shell .content .settings-card,.app-shell .content .prompt-manager-card,.app-shell .content .automatic-responses-card,.app-shell .content .response-card,.app-shell .content .template-editor-card,.app-shell .content .template-preview-card,.app-shell .content .package-table-card,.app-shell .content .audit-module,.app-shell .content .dashboard-stat,.app-shell .content .dashboard-panel{border:1px solid #eab308!important;border-radius:14px!important;background:#fff!important;box-shadow:0 5px 16px rgba(11,25,44,.1)!important;color:#0b192c}
.app-shell .content .card-title h2,.app-shell .content .card-title h3,.app-shell .content .settings-heading h2{color:#0b192c!important;font-family:'Space Grotesk','DM Sans',sans-serif}
.app-shell .content .card-title p,.app-shell .content .card-title small,.app-shell .content .filter-help,.app-shell .content .settings-heading p{color:#64748b}
.app-shell .content label,.app-shell .content legend{color:#334155}
.app-shell .content .result-count{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:1px solid #eab308;border-radius:999px;background:#fef3c7;color:#0b192c!important;font-size:11px;font-weight:800}
.app-shell .content .empty-row,.app-shell .content .empty-log,.app-shell .content .dashboard-empty{color:#64748b}
.app-shell .content .custom-sync-grid form,.app-shell .content .sync-monitor-item{border-color:#fde68a;background:#fffbeb;color:#0b192c}
.app-shell .content .custom-sync-grid h3{color:#0b192c}
.app-shell .content .custom-sync-grid label{color:#475569}
.app-shell .content nav[role="navigation"] a,.app-shell .content nav[role="navigation"] button,.app-shell .content .delivery-rates-pagination nav a,.app-shell .content .delivery-rates-pagination nav button{border-color:#eab308!important;border-radius:8px!important;color:#0b192c!important}
.app-shell .content nav[role="navigation"] a[aria-current="page"],.app-shell .content nav[role="navigation"] button[aria-current="page"]{background:#0b192c!important;color:#facc15!important}
.app-shell .chat-content{background:transparent!important}
.app-shell .chat-list,.app-shell .contact-profile{background:#fff!important;border-color:#eab308!important}
.app-shell .conversation{background:#fffbeb!important;border-color:#eab308!important}
.app-shell .chat-list-head,.app-shell .conversation-head,.app-shell .contact-profile-head,.app-shell .contact-profile-identity{background:#fff!important;border-color:#fde68a!important}
.app-shell .chat-list-head h2,.app-shell .conversation-head strong,.app-shell .contact-profile-title,.app-shell .contact-profile-identity strong{color:#0b192c!important}
.app-shell .chat-tabs .chat-tab.active{background:#0b192c!important;border-color:#0b192c!important;color:#facc15!important}
.app-shell .chat-tabs .chat-tab:not(.active),.app-shell .conversation-mode{border-color:#eab308!important;color:#0b192c!important}
.app-shell .chat-tabs .chat-tab:not(.active){background:#fffbeb!important}
.app-shell .conversation-mode.active,.app-shell .conversation-mode:hover,.app-shell .thread:hover,.app-shell .thread.selected{background:#fef3c7!important;border-color:#eab308!important;color:#0b192c!important}
.app-shell .thread{border-bottom-color:#fde68a!important}
.app-shell .contact-avatar,.app-shell .contact-media-icon{background:#fef3c7!important;color:#0b192c!important}
.app-shell .messages,.app-shell .ai-chat-messages{background:#fffbeb!important}
.app-shell .message.out,.app-shell .ai-message.ai-user{background:#0b192c!important;color:#fff!important}
.app-shell .message.in,.app-shell .ai-message.ai-agent{background:#fff!important;border-color:#fde68a!important;color:#172b45!important}
.app-shell .chat-composer,.app-shell .ai-composer{background:#fff!important;border-color:#fde68a!important}
.app-shell .chat-attach{background:#fffbeb!important;border-color:#eab308!important;color:#0b192c!important}
.app-shell .ai-empty strong{color:#0b192c!important}
.app-shell .topbar{height:58px!important;min-height:58px;padding:0 20px!important;background:#0b192c!important;border-bottom:2px solid #1e293b!important;color:#fff}
.app-shell .topbar .search{width:min(280px,38vw)!important;background:#fff!important;border:1px solid #eab308!important;border-radius:999px!important;color:#52657e!important;padding:9px 14px!important}
.app-shell .topbar .profile{border-color:#334155}
.main:has(.postal-registry-page){background-color:#eab308!important;background-image:linear-gradient(rgba(11,25,44,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(11,25,44,.045) 1px,transparent 1px),radial-gradient(ellipse at 85% 15%,rgba(11,25,44,.36),transparent 38%),linear-gradient(110deg,#eab308 0%,#facc15 54%,#eab308 100%)!important;background-size:40px 40px,40px 40px,auto,auto!important}
.app-shell .content.postal-registry-page{display:grid;gap:14px;padding:20px 20px 28px!important;background:transparent!important}
.postal-registry-page .welcome{margin:0;align-items:center}
.postal-registry-page .welcome h1{font-size:clamp(20px,2vw,26px);font-weight:800;letter-spacing:-.4px}
.postal-registry-page .welcome p{margin:4px 0 0;font-size:14px}
.registry-system-badge{display:inline-flex;align-items:center;gap:6px;margin-left:8px;padding:5px 10px;border-radius:999px;background:#0b192c;color:#facc15;font:700 11px 'DM Sans',sans-serif;vertical-align:middle;letter-spacing:0}
.registry-system-badge i,.registry-status-badge i{width:7px;height:7px;flex:none;border-radius:50%;background:currentColor}
.registry-create-trigger{min-height:36px;padding:8px 15px!important;border-radius:999px!important;white-space:nowrap}
.registry-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.registry-summary-card{min-width:0;padding:16px 18px 14px;border:1px solid #fde68a;border-radius:12px;background:#fff;box-shadow:0 4px 14px rgba(11,25,44,.12)}
.registry-summary-label{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#334155;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.35px}
.registry-summary-card>strong{display:block;margin:8px 0 3px;color:#0b192c;font:800 28px 'Space Grotesk',sans-serif;font-variant-numeric:tabular-nums}
.registry-summary-card>small{color:#64748b;font-size:11px}
.summary-icon{display:grid;place-items:center;width:25px;height:25px;border-radius:8px;font-style:normal;font-size:14px}
.summary-icon.navy{background:#0b192c;color:#facc15}
.summary-icon.blue{background:#1e3a8a;color:#fff}
.summary-icon.yellow{background:#facc15;color:#0b192c}
.registry-filters-panel{padding:14px 15px;border:1px solid #eab308;border-radius:14px;background:rgba(254,240,138,.93);box-shadow:0 4px 14px rgba(11,25,44,.12)}
.registry-filters-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.registry-filters-heading h2{margin:0;color:#0b192c;font:800 15px 'Space Grotesk',sans-serif}
.registry-filters-heading h2 span{color:#1e3a8a}
.registry-filters-heading .result-count{padding:5px 10px;border-radius:99px;background:#0b192c;color:#fff;font-size:11px;font-weight:800}
.postal-registry-page .registry-filters{display:grid;gap:10px;margin:0!important;padding:0!important}
.postal-registry-page .registry-filter-main{display:grid;grid-template-columns:minmax(200px,1.8fr) minmax(155px,.9fr);gap:10px}
.postal-registry-page .registry-filter-main>label,.postal-registry-page .registry-filter-range{display:grid;gap:6px;color:#0b192c;font-size:12px;font-weight:800}
.postal-registry-page .registry-filter-main .input,.postal-registry-page .registry-filter-range .input{height:38px;min-height:38px;padding:7px 11px!important;font-size:13px}
.postal-registry-page .registry-filter-dates{display:grid;grid-template-columns:minmax(230px,1fr) minmax(230px,1fr) auto;align-items:end;gap:10px}
.postal-registry-page .registry-filter-range>div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
.postal-registry-page .registry-clear-filters{height:38px;min-height:38px;padding:7px 14px!important;background:#0b192c!important;color:#facc15!important;border:1px solid #1e293b!important;font-size:11px;font-weight:800;white-space:nowrap}
.postal-registry-page .registry-clear-filters:hover{background:#1e293b!important;color:#fff!important}
.registry-table-card{overflow:hidden;border:1px solid #eab308;border-radius:14px;background:#fff;box-shadow:0 6px 18px rgba(11,25,44,.16)}
.registry-table-wrap{overflow:auto}
.postal-registry-page .registry-table{min-width:1120px;font-size:13px}
.postal-registry-page .registry-table th{padding:12px 11px!important;background:#0b192c!important;color:#fff!important;border:0!important;font-size:11px;font-weight:800;letter-spacing:.25px;text-transform:uppercase;white-space:nowrap}
.postal-registry-page .registry-table td{padding:11px 11px!important;background:#fff!important;color:#172b45!important;border-bottom:1px solid #fde68a!important;font-size:13px;vertical-align:middle}
.postal-registry-page .registry-table tbody tr:nth-child(even) td{background:#fff!important}
.postal-registry-page .registry-table tbody tr:hover td{background:#fffbeb!important}
.postal-registry-page .registry-table tbody tr:has(.status-sent) td{background:#f0fdf4!important}
.postal-registry-page .registry-campaign-row{cursor:pointer}
.postal-registry-page .registry-campaign-name{display:flex;align-items:center;gap:7px;min-width:105px;color:#0b192c}
.postal-registry-page .registry-campaign-name strong{font-size:13px;font-weight:800}
.postal-registry-page .registry-campaign-name>i{display:grid;place-items:center;width:21px;height:21px;flex:none;border-radius:6px;background:#0b192c;color:#facc15;font-size:11px;font-style:normal}
.postal-registry-page .registry-campaign-name>i.completed{background:#059669;color:#fff}
.postal-registry-page .registry-departments{min-width:100px}
.postal-registry-page .registry-departments small,.postal-registry-page .registry-state-counts{display:block;color:#64748b!important;font-size:11px;line-height:1.5;white-space:nowrap}
.postal-registry-page .registry-status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid #1e293b;border-radius:999px;background:#0b192c;color:#facc15;font-size:11px;font-weight:800;white-space:nowrap}
.postal-registry-page .registry-status-badge i{width:6px;height:6px}
.postal-registry-page .registry-status-badge.status-sent{background:#059669;border-color:#047857;color:#fff}
.postal-registry-page .registry-status-badge.status-failed{background:#e11d48;border-color:#be123c;color:#fff}
.postal-registry-page .registry-status-badge.status-sending{background:#1e3a8a;border-color:#1e40af;color:#fff}
.postal-registry-page .registry-status-badge.status-paused,.postal-registry-page .registry-status-badge.status-partial{background:#d97706;border-color:#b45309;color:#fff}
.postal-registry-page .registry-progress{display:block;width:88px;height:7px;margin:0 0 4px;background:#e2e8f0;border:1px solid #cbd5e1}
.postal-registry-page .registry-progress i{background:#1e3a8a}
.postal-registry-page .registry-progress-display small{color:#64748b;font-size:10px;white-space:nowrap}
.postal-registry-page .registry-actions{min-width:330px}
.postal-registry-page .registry-action-group{display:flex;align-items:center;gap:4px}
.postal-registry-page .registry-actions button{margin:0!important;padding:7px 9px!important;border-radius:7px!important;font-size:10px!important;line-height:1.2;white-space:nowrap;box-shadow:none}
.postal-registry-page .registry-actions .registry-send-btn{background:#1e3a8a!important;color:#fff!important;border:1px solid #1e40af!important}
.postal-registry-page .registry-actions .registry-cancel-btn{background:#d97706!important;color:#fff!important;border:1px solid #b45309!important}
.registry-table-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:13px 16px;background:#f8fafc;color:#334155;font-size:12px;font-weight:700}
.registry-pagination{display:flex;align-items:center;justify-content:center;gap:10px}
.registry-pagination .secondary-btn{margin:0;padding:8px 12px;font-size:12px;font-weight:700}
.registry-pagination .secondary-btn:disabled{opacity:.5;cursor:not-allowed}
@media(max-width:900px){.registry-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.postal-registry-page .registry-filter-dates{grid-template-columns:1fr 1fr}.postal-registry-page .registry-clear-filters{justify-self:end}}
@media(max-width:600px){.app-shell .content.postal-registry-page{padding:16px 10px!important}.postal-registry-page .welcome{align-items:flex-start;flex-direction:column;gap:10px}.registry-system-badge{display:table;margin:7px 0 0}.registry-summary{gap:8px}.registry-summary-card{padding:11px}.postal-registry-page .registry-filter-main,.postal-registry-page .registry-filter-dates{grid-template-columns:1fr}.postal-registry-page .registry-clear-filters{justify-self:start}.registry-table-footer,.registry-pagination{align-items:stretch;flex-direction:column}.registry-pagination{width:100%}.registry-pagination .secondary-btn{width:100%}}
.app-shell .content .welcome h1{font-size:25px;color:#0b192c!important}
.welcome h1::after{content:none}
.app-shell .content .welcome p{color:#64748b!important}
.app-shell .content .card,.app-shell .content.postal-registry-page .registry-card{background:#fff!important;border:1px solid #fde68a!important;border-radius:14px!important;box-shadow:0 4px 14px rgba(11,25,44,.08)!important;color:#0b192c}
.app-shell .content .card-title h2,.app-shell .content .card-title h3,.app-shell .content .settings-heading h2{color:#0b192c!important}
.app-shell .content .card-title p,.app-shell .content .card-title small,.app-shell .content .filter-help{color:#64748b}
.primary-btn,.btn{background:#0b192c!important;color:#facc15!important;border:2px solid #1e293b!important;border-radius:9px!important;font-weight:800;box-shadow:0 4px 12px rgba(11,25,44,.18);transition:transform .15s ease,background .15s ease,box-shadow .15s ease}
.primary-btn:hover,.btn:hover{background:#1e293b!important;color:#fff!important;transform:translateY(-1px);box-shadow:0 6px 16px rgba(11,25,44,.22)}
.secondary-btn{background:#fff!important;color:#0b192c!important;border:1px solid #eab308!important;border-radius:9px!important}
.secondary-btn:hover{background:#fef3c7!important;border-color:#ca8a04!important;color:#0b192c!important}
.app-shell .content input:not([type=checkbox]):not([type=radio]),.app-shell .content select,.app-shell .content textarea,.app-shell .content .input{background:#fff!important;color:#0b192c!important;border:1px solid #ca8a04!important;border-radius:9px}
.app-shell .content input::placeholder,.app-shell .content textarea::placeholder{color:#64748b}
.app-shell .content input:focus,.app-shell .content select:focus,.app-shell .content textarea:focus,.app-shell .content .input:focus{border-color:#0b192c!important;box-shadow:0 0 0 3px rgba(250,204,21,.3)!important;outline:none}
.app-shell .content table thead th,.app-shell .content.postal-registry-page .registry-table th,.app-shell .content.operations-page .operations-table th,.app-shell .content.settings-page .update-log-table th,.app-shell .content.audit-page .audit-table th{background:#0b192c!important;color:#fff!important;border-color:#1e293b!important;font-weight:800}
.app-shell .content table tbody td,.app-shell .content.postal-registry-page .registry-table td,.app-shell .content.operations-page .operations-table td,.app-shell .content.settings-page .update-log-table td,.app-shell .content.audit-page .audit-table td{color:#172b45}
.app-shell .content table tbody tr:nth-child(even) td,.app-shell .content.postal-registry-page .registry-table tbody tr:nth-child(even) td,.app-shell .content.operations-page .operations-table tbody tr:nth-child(even) td,.app-shell .content.settings-page .update-log-table tbody tr:nth-child(even) td,.app-shell .content.audit-page .audit-table tbody tr:nth-child(even) td{background:#fffbeb}
.app-shell .content table tbody tr:hover td,.app-shell .content.postal-registry-page .registry-table tbody tr:hover td,.app-shell .content.operations-page .operations-table tbody tr:hover td,.app-shell .content.settings-page .update-log-table tbody tr:hover td,.app-shell .content.audit-page .audit-table tbody tr:hover td{background:#fef3c7}
.app-shell .content table td,.app-shell .content table th{border-color:#fde68a}
.app-shell .content.postal-registry-page .registry-table tbody tr:nth-child(even) td{background:#fffbeb!important}
.app-shell .content.postal-registry-page .registry-table tbody tr:hover td{background:#fef3c7!important}
.app-shell .content .campaign-history-modal,.app-shell .content dialog{background:linear-gradient(145deg,#e8e8f6,#e4edf8 58%,#eee5f7)!important;color:#0b192c!important;border:1px solid #6366f1!important;border-radius:16px;box-shadow:0 24px 70px rgba(30,58,138,.28),0 0 0 4px rgba(250,204,21,.14)!important}
.app-shell .content .campaign-history-modal .modal-head{border-color:#bfdbfe}
.app-shell .content .campaign-history-modal h2,.app-shell .content .campaign-history-modal strong{color:#0b192c}
.app-shell .content .campaign-history-modal p,.app-shell .content .campaign-history-modal small{color:#52657e}
.app-shell dialog[open],.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal){color-scheme:light;background:linear-gradient(145deg,#e8e8f6,#e4edf8 58%,#eee5f7)!important;color:#0b192c!important;border:1px solid #6366f1!important;border-radius:16px!important;box-shadow:0 24px 70px rgba(30,58,138,.28),0 0 0 4px rgba(250,204,21,.14)!important}
.app-shell .content dialog[open],.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal){color-scheme:light!important;background:linear-gradient(145deg,#e8e8f6,#e4edf8 58%,#eee5f7)!important;color:#0b192c!important;border:1px solid #6366f1!important;border-radius:16px!important;box-shadow:0 24px 70px rgba(30,58,138,.28),0 0 0 4px rgba(250,204,21,.14)!important}
.app-shell dialog[open]::backdrop{background:rgba(11,25,44,.68);backdrop-filter:blur(3px)}
.app-shell dialog[open] .modal-head,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head{background:linear-gradient(115deg,#1e3a8a 0%,#4338ca 58%,#7c3aed 100%)!important;border-bottom:3px solid #facc15!important}
.app-shell .content dialog[open] .modal-head,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head{background:linear-gradient(115deg,#1e3a8a 0%,#4338ca 58%,#7c3aed 100%)!important;border-bottom:3px solid #facc15!important}
.app-shell .content dialog[open] .modal-head h2,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) h2{color:#0b192c!important}
.app-shell .content dialog[open] .modal-head p,.app-shell .content dialog[open] .modal-head small{color:#52657e!important}
.app-shell .content dialog[open] label,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) label{color:#334155!important}
.app-shell dialog[open] input:not([type=checkbox]):not([type=radio]),.app-shell dialog[open] select,.app-shell dialog[open] textarea,.app-shell dialog[open] .input,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:not([type=checkbox]):not([type=radio]),.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .input{background:#e3e5f1!important;color:#0b192c!important;border-color:#818cf8!important;color-scheme:light!important}
.app-shell .content dialog[open] input:not([type=checkbox]):not([type=radio]),.app-shell .content dialog[open] select,.app-shell .content dialog[open] textarea,.app-shell .content dialog[open] .input,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:not([type=checkbox]):not([type=radio]),.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .input{background:#e3e5f1!important;color:#0b192c!important;border-color:#818cf8!important;color-scheme:light!important}
.app-shell dialog[open] input:focus,.app-shell dialog[open] select:focus,.app-shell dialog[open] textarea:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea:focus{border-color:#1e3a8a!important;box-shadow:0 0 0 3px rgba(59,130,246,.2)!important;outline:none}
.app-shell .content dialog[open] .card,.app-shell .content dialog[open] .campaign-filters,.app-shell .content dialog[open] .campaign-builder,.app-shell .content dialog[open] .campaign-info-grid>div,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .card{background:#dce7f8!important;color:#0b192c!important;border-color:#b9c9ee!important}
.app-shell .content dialog[open] table th,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table th{background:#0b192c!important;color:#fff!important;border-color:#1e293b!important}
.app-shell .content dialog[open] table td,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table td{background:#e5e8f4!important;color:#172b45!important;border-color:#c8c9eb!important}
.app-shell .content dialog[open] table tbody tr:nth-child(even) td,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table tbody tr:nth-child(even) td{background:#dce5f5!important}
.app-shell .content dialog[open] .secondary-btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn{background:#fff!important;color:#1e3a8a!important;border-color:#93c5fd!important}
.app-shell .content dialog[open] .secondary-btn:hover,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn:hover{background:#dbeafe!important}
.app-shell .tracking-overlay,.app-shell .registry-info-overlay{background:rgba(11,25,44,.68)!important;backdrop-filter:blur(3px)}
.app-shell .content .modal-close{display:inline-grid;width:34px;height:34px;flex:none;place-items:center;border:1px solid #93c5fd!important;border-radius:8px;background:#dbeafe!important;color:#1e3a8a!important;font-size:22px;line-height:1;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease}
.app-shell .content .modal-close:hover{border-color:#1e3a8a!important;background:#1e3a8a!important;color:#fff!important}
.app-shell .content .modal-close:focus-visible{outline:3px solid #3b82f6!important;outline-offset:2px}
.app-shell dialog[open],.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal),.app-shell .content .campaign-history-modal{border-color:#6366f1!important;box-shadow:0 24px 70px rgba(30,58,138,.28),0 0 0 4px rgba(250,204,21,.14)!important}
.app-shell dialog[open],.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal),.app-shell .content .campaign-history-modal{background:linear-gradient(160deg,#e8e8f6 0,#e4edf8 220px)!important}
.app-shell dialog[open] .modal-head,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head,.app-shell .content .campaign-history-modal .modal-head{background:linear-gradient(115deg,#1e3a8a 0%,#4338ca 58%,#7c3aed 100%)!important;border-bottom:3px solid #facc15!important;color:#fff!important}
.app-shell dialog[open] .modal-head h2,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head h2,.app-shell .content .campaign-history-modal .modal-head h2{color:#fff!important}
.app-shell dialog[open] .modal-head p,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head p,.app-shell .content .campaign-history-modal .modal-head p{color:#e0e7ff!important}
.app-shell dialog[open] .modal-close,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-close{border-color:#fde68a!important;background:#facc15!important;color:#1e3a8a!important}
.app-shell dialog[open] .modal-close:hover,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-close:hover{border-color:#fff!important;background:#fff!important;color:#4338ca!important}
.app-shell .content dialog[open] .campaign-modal-body,.app-shell .content dialog[open] .automatic-response-form,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary{background:linear-gradient(135deg,#dce7f8,#e6def5)!important}
.app-shell .content dialog[open] .campaign-info-grid>div,.app-shell .content dialog[open] .registry-info-summary>span,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary>span{background:#dce2f4!important;border-color:#a5b4fc!important;color:#312e81!important}
.app-shell .content dialog[open] table th,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table th{background:linear-gradient(100deg,#1e3a8a,#4338ca)!important;border-color:#6366f1!important}
.app-shell .content dialog[open] .primary-btn,.app-shell .content dialog[open] .btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .primary-btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .btn{background:linear-gradient(105deg,#1e3a8a,#5b21b6)!important;border-color:#4338ca!important;color:#fff!important}
.app-shell .content dialog[open] .primary-btn:hover,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .primary-btn:hover{background:linear-gradient(105deg,#172554,#4338ca)!important}
.app-shell .content dialog[open] .secondary-btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn{background:#dce5f5!important;border-color:#818cf8!important;color:#3730a3!important}
.app-shell .content dialog[open] .secondary-btn:hover,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn:hover{background:#c7d2fe!important;border-color:#6366f1!important}
.app-shell .tracking-overlay,.app-shell .registry-info-overlay{background:rgba(30,27,75,.76)!important;backdrop-filter:blur(5px) saturate(1.2)}
.app-shell .content .welcome .dashboard-live,.app-shell .content .result-count{color:#1e3a8a}
.app-shell .content .operations-tabs .is-active,.app-shell .content .campaign-event-button.is-active{background:#facc15!important;color:#0b192c!important;border-color:#eab308!important}
.app-shell .content .operation-alert.success{background:#ecfdf5!important;color:#065f46!important;border-color:#6ee7b7!important}
.app-shell .content .operation-alert.error{background:#fef2f2!important;color:#991b1b!important;border-color:#fca5a5!important}
.app-shell .content .table-wrap,.app-shell .content .registry-table-wrap,.app-shell .content .campaign-message-table-wrap,.app-shell .content .update-log-table{overflow-x:auto}
.app-shell .content a:not(.nav-item){color:#1e3a8a}
.app-shell .content a:not(.nav-item):hover{color:#0b192c}
.app-shell .content :focus-visible{outline:2px solid #ca8a04;outline-offset:2px}
.dashboard-page .dashboard-stat,.dashboard-page .dashboard-panel{background:#fff!important;border:1px solid #fde68a!important;border-radius:14px!important}
.dashboard-page .dashboard-stat strong,.dashboard-page .dashboard-panel h2,.dashboard-page .service-row strong,.dashboard-page .dashboard-activity strong{color:#0b192c!important}
.dashboard-page .dashboard-stat>span,.dashboard-page .dashboard-stat small,.dashboard-page .dashboard-panel p,.dashboard-page .service-row small,.dashboard-page .dashboard-activity small{color:#52657e!important}
.dashboard-page .service-row,.dashboard-page .dashboard-activity{border-color:#fde68a}
.dashboard-page .dashboard-badge{background:#facc15;color:#0b192c}
@media(prefers-reduced-motion:reduce){.app-shell .content *{scroll-behavior:auto!important;transition:none!important}}
`;
const sidebarStyles = `
.sidebar{background:#0b192c!important;color:#fff!important;border-right:2px solid #1e293b!important;box-shadow:3px 0 18px rgba(11,25,44,.16);z-index:2}
.sidebar .brand{min-height:56px;gap:8px;color:#fff;border-bottom:1px solid #263858;font-size:18px;white-space:nowrap}
.sidebar .brand-mark{width:32px;height:32px;flex:none;background:#facc15!important;color:#0b192c!important;border:2px solid #eab308}
.sidebar .brand small{display:block;color:#facc15;font:700 9px 'DM Sans',sans-serif;letter-spacing:1.1px;margin-bottom:2px}
.sidebar-nav{display:flex;flex-direction:column;gap:4px;padding:8px 2px}
.sidebar .nav-group{margin:0;border-radius:10px}
.sidebar .nav-group > .nav-label{display:flex!important;align-items:center;gap:8px;margin:0;padding:9px 10px!important;border-radius:9px;color:#cbd5e1!important;font-size:10px;font-weight:800;letter-spacing:.45px;line-height:1.25;cursor:pointer;list-style:none;transition:background .15s ease,color .15s ease}
.sidebar .nav-group > .nav-label::-webkit-details-marker{display:none}
.sidebar .nav-group > .nav-label::after{content:'⌄';margin-left:auto;font-size:15px;line-height:1;transform:rotate(-90deg);transition:transform .15s ease}
.sidebar .nav-group[open] > .nav-label::after{transform:rotate(0)}
.sidebar .nav-group[open] > .nav-label,.sidebar .nav-group > .nav-label:hover{background:#16243a!important;color:#fff!important}
.sidebar .nav-item.active,.sidebar .nav-item:hover{background:#facc15!important;border-color:#eab308!important;color:#0b192c!important}
.sidebar .nav-item.active > span{color:#0b192c!important}
.sidebar .nav-group > .nav-label > span{width:20px;text-align:center;color:#facc15;font-size:15px}
.sidebar .nav-group > .nav-item{display:flex;align-items:center;gap:8px;margin:2px 4px;padding:8px 9px!important;border:1px solid transparent;border-radius:8px;color:#dbe5f0!important;font-size:10px;font-weight:600}
.sidebar .nav-group > .nav-item > span{width:20px;color:#facc15;font-size:15px}
.sidebar .nav-group > .nav-item:hover{background:#1e293b!important;color:#fff!important}
.sidebar .nav-group > .nav-item.active{background:#facc15!important;border-color:#eab308!important;box-shadow:inset 3px 0 #0b192c;color:#0b192c!important;font-weight:800}
.sidebar .nav-group > .nav-item.active > span{color:#0b192c}
.sidebar .sidebar-bottom{padding:12px 2px 0;border-color:#263858;background:#0b192c!important}
.sidebar .sidebar-bottom form .nav-item{width:100%;margin:0;padding:10px 12px!important;border:0;background:transparent;color:#dbe5f0!important;text-align:left}
.sidebar .sidebar-bottom form .nav-item:hover{background:#7f1d1d!important;color:#fff!important}
.sidebar .nav-group > .nav-label:focus-visible,.sidebar .nav-item:focus-visible{outline:2px solid #facc15;outline-offset:2px}
body.night-mode .sidebar{background:#071426!important;border-color:#1e293b!important}
body.night-mode .sidebar .brand{color:#f8fafc}
body.night-mode .sidebar .brand small{color:#facc15}
body.night-mode .sidebar .nav-group > .nav-label{color:#b7c4d8!important}
body.night-mode .sidebar .nav-group[open] > .nav-label,body.night-mode .sidebar .nav-group > .nav-label:hover{background:#16243a;color:#fff!important}
body.night-mode .sidebar .nav-group > .nav-label > span,body.night-mode .sidebar .nav-group > .nav-item > span{color:#facc15}
body.night-mode .sidebar .nav-group > .nav-item{color:#cbd5e1!important}
body.night-mode .sidebar .nav-group > .nav-item:hover{background:#1e293b!important;color:#fff!important}
body.night-mode .sidebar .nav-group > .nav-item.active{background:#facc15!important;color:#0b192c!important;border-color:#eab308!important}
body.night-mode .sidebar .nav-item.active,body.night-mode .sidebar .nav-item:hover{background:#facc15!important;color:#0b192c!important}
body.night-mode .sidebar .nav-item.active > span{color:#0b192c!important}
body.night-mode .sidebar .sidebar-bottom{border-color:#243149;background:#071426!important}
body.night-mode .sidebar .sidebar-bottom form .nav-item{color:#cbd5e1!important}
.dashboard-page{display:grid;gap:20px;padding:26px 28px 34px!important;background-image:radial-gradient(circle at 12% 2%,rgba(11,25,44,.2) 0 2px,transparent 3px),radial-gradient(circle at 64% 9%,rgba(11,25,44,.16) 0 2px,transparent 3px),radial-gradient(circle at 89% 31%,rgba(11,25,44,.2) 0 3px,transparent 4px),radial-gradient(circle at 52% 69%,rgba(11,25,44,.17) 0 2px,transparent 3px),radial-gradient(ellipse at 95% 0%,rgba(250,204,21,.11),transparent 38%)}
.dashboard-page>.welcome{min-height:74px;margin:0;padding:14px 20px;border:1px solid rgba(234,179,8,.75);border-left:4px solid #0b192c;border-radius:12px;background:rgba(255,251,235,.88);box-shadow:0 4px 14px rgba(11,25,44,.08)}
.dashboard-page>.welcome h1{font-size:clamp(24px,2.4vw,32px)!important}
.dashboard-page>.welcome p{font-size:14px!important}
.dashboard-page .dashboard-live{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #bbf7d0;border-radius:999px;background:#ecfdf5;color:#047857;font-size:12px}
.dashboard-page .dashboard-live i{width:8px;height:8px;margin:0;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15)}
.dashboard-page .dashboard-metrics{gap:14px}
.dashboard-page .dashboard-stat{min-height:174px;padding:18px 20px;border-color:#eab308!important;border-radius:16px!important;box-shadow:0 6px 18px rgba(11,25,44,.12)!important;transition:transform .18s ease,box-shadow .18s ease}
.dashboard-page .dashboard-stat:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(11,25,44,.16)!important}
.dashboard-page .dashboard-stat-icon,.dashboard-page .service-symbol{width:38px;height:38px;border:1px solid rgba(11,25,44,.04);border-radius:11px}
.dashboard-page .dashboard-stat-icon{margin-bottom:11px}
.dashboard-page .dashboard-stat>span{font-size:13px}
.dashboard-page .dashboard-stat strong{margin:8px 0 3px;font-size:34px;font-variant-numeric:tabular-nums}
.dashboard-page .dashboard-stat small,.dashboard-page .dashboard-panel p{font-size:12px}
.dashboard-page .dashboard-columns{gap:18px;margin-top:0;align-items:stretch}
.dashboard-page .dashboard-panel{min-width:0;padding:22px;border-color:#eab308!important;border-radius:16px!important;box-shadow:0 6px 18px rgba(11,25,44,.12)!important}
.dashboard-page .dashboard-panel-head{align-items:center;margin-bottom:10px;padding-bottom:14px;border-bottom:1px solid #fde68a}
.dashboard-page .dashboard-panel h2{font-size:19px}
.dashboard-page .service-row,.dashboard-page .dashboard-activity{border-color:#e8edf3}
.dashboard-page .service-row{min-height:82px;padding:15px 0}
.dashboard-page .service-row strong,.dashboard-page .dashboard-activity strong{font-size:14px}
.dashboard-page .service-row small,.dashboard-page .dashboard-activity small{font-size:12px}
.dashboard-page .service-value{padding:6px 10px;border-radius:999px;background:#ecfdf5;font-size:12px;font-weight:800}
.dashboard-page .service-value.success{color:#047857}
.dashboard-page .service-value.danger{background:#fef2f2;color:#b91c1c}
.dashboard-page .dashboard-activity{min-height:61px;padding:8px 0}
.dashboard-page .activity-dot{flex:none;background:#eab308;box-shadow:0 0 0 4px #fef3c7}
.dashboard-page .dashboard-badge{letter-spacing:.5px;background:#facc15;color:#0b192c}
body.night-mode .dashboard-page>.welcome{background:rgba(17,31,55,.94);border-color:#334766;border-left-color:#facc15}
body.night-mode .dashboard-page>.welcome h1{color:#f8fafc!important}
body.night-mode .dashboard-page>.welcome p{color:#b7c4d8!important}
body.night-mode .dashboard-page .dashboard-live{background:#10291e;border-color:#276749;color:#bbf7d0}
body.night-mode .dashboard-page .dashboard-panel-head{border-color:#334766}
body.night-mode .dashboard-page .service-value.success{background:#10291e;color:#bbf7d0}
body.night-mode .dashboard-page .service-value.danger{background:#301919;color:#fecaca}
@media(min-width:901px){.sidebar{width:220px!important;flex:0 0 220px!important}}
@media(max-width:900px){
  .sidebar .brand{justify-content:center;padding:0!important}
  .sidebar .brand > span:last-child,.sidebar .nav-group > .nav-item em,.sidebar .sidebar-bottom form .nav-item em{display:none!important}
  .sidebar .nav-group > .nav-label{justify-content:center;padding:11px 5px!important;font-size:0!important}
  .sidebar .nav-group > .nav-label > span,.sidebar .nav-group > .nav-item > span{font-size:16px}
  .sidebar .nav-group > .nav-label::after{display:none}
  .sidebar .nav-group > .nav-item,.sidebar .sidebar-bottom form .nav-item{justify-content:center;margin:2px 0;padding:10px 4px!important}
}
`;

const correosBoliviaTheme = `
:root{color-scheme:dark;--cb-app:#1e2832;--cb-header:#19222b;--cb-panel:#283542;--cb-card:#334352;--cb-card-hover:#3c4e5e;--cb-selected:#425465;--cb-input:#1f2a34;--cb-border:#344555;--cb-border-soft:#2d3d4b;--cb-text:#e2e8f0;--cb-muted:#aab7c4;--cb-gold:#dfaa43;--cb-gold-hover:#c99738}
body:has(.app-shell){background:var(--cb-app)!important;color:var(--cb-text)!important}
.app-shell{background:var(--cb-app)!important;color:var(--cb-text)!important}
.app-shell .sidebar{background:var(--cb-header)!important;border-color:var(--cb-border)!important;color:var(--cb-text)!important;box-shadow:none!important}
.app-shell .sidebar .brand{color:#f8fafc!important;border-color:var(--cb-border)!important}
.app-shell .sidebar .brand-mark{background:var(--cb-gold)!important;border-color:var(--cb-gold)!important;color:var(--cb-header)!important}
.app-shell .sidebar .brand small,.app-shell .sidebar .nav-group>summary>span,.app-shell .sidebar .nav-group>.nav-item>span{color:var(--cb-gold)!important}
.app-shell .sidebar .nav-group>.nav-label{color:#b8c4ce!important}
.app-shell .sidebar .nav-group[open]>.nav-label,.app-shell .sidebar .nav-group>.nav-label:hover{background:#26333e!important;color:#fff!important}
.app-shell .sidebar .nav-group>.nav-item{color:#c4ced7!important}
.app-shell .sidebar .nav-group>.nav-item:hover{background:#2c3c4a!important;color:#fff!important}
.app-shell .sidebar .nav-group>.nav-item.active,.app-shell .sidebar .nav-item.active,.app-shell .sidebar .nav-item:hover{background:var(--cb-gold)!important;border-color:var(--cb-gold)!important;color:#19222b!important}
.app-shell .sidebar .nav-group>.nav-item.active>span,.app-shell .sidebar .nav-item.active>span{color:#19222b!important}
.app-shell .sidebar .sidebar-bottom{background:var(--cb-header)!important;border-color:var(--cb-border)!important}
.app-shell .sidebar .sidebar-bottom form .nav-item{color:#c4ced7!important}
.app-shell .sidebar .sidebar-bottom form .nav-item:hover{background:#6b3535!important;color:#fff!important}
.app-shell .main{background:var(--cb-app)!important;color:var(--cb-text)!important}
.app-shell .topbar{background:var(--cb-header)!important;border-color:var(--cb-border-soft)!important;color:var(--cb-text)!important}
.app-shell .topbar .search{background:#2a3845!important;border-color:#455767!important;color:#e2e8f0!important}
.app-shell .topbar .profile{border-color:#455767!important}
.app-shell .topbar .profile strong{color:#f8fafc!important}
.app-shell .topbar .profile small{color:#aab7c4!important}
.app-shell .content{background:var(--cb-app)!important;color:var(--cb-text)!important}
.app-shell .content h1,.app-shell .content h2,.app-shell .content h3,.app-shell .content h4{color:#f1f5f9!important}
.app-shell .content .welcome h1,.app-shell .content .card-title h2,.app-shell .content .card-title h3{color:#f1f5f9!important}
.app-shell .content p,.app-shell .content small,.app-shell .content .filter-help,.app-shell .content .welcome p{color:var(--cb-muted)!important}
.app-shell .content .welcome p{color:var(--cb-muted)!important}
.app-shell .content .welcome{background:#202c36!important;border:1px solid var(--cb-border)!important;border-left:3px solid var(--cb-gold)!important;border-radius:14px!important;color:var(--cb-text)!important;box-shadow:none!important}
.app-shell .content .card,.app-shell .content .settings-card,.app-shell .content .prompt-manager-card,.app-shell .content .automatic-responses-card,.app-shell .content .response-card,.app-shell .content .template-editor-card,.app-shell .content .template-preview-card,.app-shell .content .package-table-card,.app-shell .content .audit-module,.app-shell .content .dashboard-stat,.app-shell .content .dashboard-panel,.app-shell .content .registry-card,.app-shell .content .registry-summary-card,.app-shell .content .registry-table-card,.app-shell .content .registry-filters-panel{background:var(--cb-panel)!important;border:1px solid var(--cb-border)!important;border-radius:14px!important;color:var(--cb-text)!important;box-shadow:0 8px 22px rgba(0,0,0,.16)!important}
.app-shell .content .card-title h2,.app-shell .content .card-title h3,.app-shell .content .settings-heading h2,.app-shell .content .dashboard-panel h2{color:#f1f5f9!important}
.app-shell .content .card-title p,.app-shell .content .card-title small,.app-shell .content .settings-heading p,.app-shell .content .dashboard-panel p{color:var(--cb-muted)!important}
.app-shell .content label,.app-shell .content legend,.app-shell .content .registry-filter-main>label,.app-shell .content .registry-filter-range{color:#cbd5df!important}
.app-shell .content input:not([type=checkbox]):not([type=radio]),.app-shell .content select,.app-shell .content textarea,.app-shell .content .input,.app-shell .content select.input,.app-shell .content textarea.input{background:var(--cb-input)!important;color:#f1f5f9!important;border:1px solid #455767!important;border-radius:9px!important;color-scheme:dark!important}
.app-shell .content input::placeholder,.app-shell .content textarea::placeholder{color:#93a2b1!important}
.app-shell .content input:focus,.app-shell .content select:focus,.app-shell .content textarea:focus,.app-shell .content .input:focus{border-color:var(--cb-gold)!important;box-shadow:0 0 0 3px rgba(223,170,67,.18)!important;outline:none}
.app-shell .content select option{background:#273440!important;color:#e2e8f0!important}
.app-shell .content .primary-btn,.app-shell .content .btn{background:var(--cb-gold)!important;border:1px solid var(--cb-gold)!important;border-radius:10px!important;color:#19222b!important;font-weight:800!important;box-shadow:none!important}
.app-shell .content .primary-btn:hover,.app-shell .content .btn:hover{background:var(--cb-gold-hover)!important;border-color:var(--cb-gold-hover)!important;color:#19222b!important}
.app-shell .content .secondary-btn,.app-shell .content .action-btn{background:#384a5b!important;border:1px solid #526373!important;border-radius:9px!important;color:#e2e8f0!important}
.app-shell .content .secondary-btn:hover,.app-shell .content .action-btn:hover{background:#43576a!important;border-color:#647789!important;color:#fff!important}
.app-shell .content a:not(.nav-item){color:#e6bc6d}
.app-shell .content a:not(.nav-item):hover{color:#f3d38c}
.app-shell .content :focus-visible{outline:2px solid var(--cb-gold)!important;outline-offset:2px}
.app-shell .content .result-count,.app-shell .content .dashboard-badge{background:#384956!important;border:1px solid #526373!important;color:#f3d38c!important}
.app-shell .content .empty-row,.app-shell .content .empty-log,.app-shell .content .dashboard-empty{color:#aab7c4!important}
.app-shell .content table{color:#dbe3eb!important}
.app-shell .content table thead th,.app-shell .content .registry-table th,.app-shell .content .operations-table th,.app-shell .content .update-log-table th,.app-shell .content .audit-table th{background:var(--cb-header)!important;color:#e2e8f0!important;border-color:var(--cb-border)!important}
.app-shell .content table tbody td,.app-shell .content .registry-table td,.app-shell .content .operations-table td,.app-shell .content .update-log-table td,.app-shell .content .audit-table td{background:var(--cb-panel)!important;color:#dbe3eb!important;border-color:#3a4a58!important}
.app-shell .content table tbody tr:nth-child(even) td,.app-shell .content .registry-table tbody tr:nth-child(even) td{background:#2d3d4b!important}
.app-shell .content table tbody tr:hover td,.app-shell .content .registry-table tbody tr:hover td{background:#3c4e5e!important}
.app-shell .content .registry-table-footer{background:var(--cb-header)!important;color:#dbe3eb!important}
.app-shell .content .registry-progress{background:#455767!important;border-color:#526373!important}
.app-shell .content .registry-progress i{background:var(--cb-gold)!important}
.app-shell .content .campaign-filters,.app-shell .content .campaign-builder,.app-shell .content .summary-item,.app-shell .content .registry-summary-card,.app-shell .content .registry-filters-panel,.app-shell .content .tracking-detail-summary>span{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
.app-shell .content .campaign-filters h3,.app-shell .content .campaign-builder h3,.app-shell .content .summary-item strong{color:#f1f5f9!important}
.app-shell .content .campaign-filters small,.app-shell .content .campaign-builder small,.app-shell .content .summary-item small{color:#aab7c4!important}
.app-shell .content .campaign-event-button,.app-shell .content .operations-tabs button{background:#384a5b!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .content .campaign-event-button:hover,.app-shell .content .operations-tabs button:hover{background:#43576a!important}
.app-shell .content .campaign-event-button.is-active,.app-shell .content .operations-tabs .is-active{background:var(--cb-gold)!important;border-color:var(--cb-gold)!important;color:#19222b!important}
.app-shell .content .status-sent,.app-shell .content .success,.app-shell .content .operation-alert.success{color:#a7f3d0}
.app-shell .content .operation-alert.success{background:#183c36!important;border-color:#286b5b!important}
.app-shell .content .operation-alert.error{background:#4b282c!important;border-color:#9c4a52!important;color:#fecaca!important}
.app-shell .content .campaign-history-modal,.app-shell .content dialog[open],.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal){background:linear-gradient(145deg,#273440,#2d3d4b 65%,#334352)!important;border:1px solid #526373!important;color:#e2e8f0!important;box-shadow:0 24px 70px rgba(0,0,0,.55)!important;color-scheme:dark!important}
.app-shell .content dialog[open]::backdrop{background:rgba(10,15,20,.78)!important;backdrop-filter:blur(4px)}
.app-shell .content .modal-head{background:#192630!important;border-color:#455767!important}
.app-shell .content .modal-head h2,.app-shell .content dialog[open] .modal-head h2{color:#f8fafc!important}
.app-shell .content .modal-head p,.app-shell .content dialog[open] .modal-head p{color:#bac6d0!important}
.app-shell .content .modal-close{background:#384a5b!important;border:1px solid #526373!important;color:#e2e8f0!important}
.app-shell .content .modal-close:hover{background:var(--cb-gold)!important;border-color:var(--cb-gold)!important;color:#19222b!important}
.app-shell .content dialog[open] .campaign-modal-body,.app-shell .content dialog[open] .automatic-response-form,.app-shell .content dialog[open] .campaign-filters,.app-shell .content dialog[open] .campaign-builder,.app-shell .content dialog[open] .card,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
.app-shell .content dialog[open] .campaign-info-grid>div,.app-shell .content dialog[open] .registry-info-summary>span,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary>span{background:#384956!important;border-color:#526373!important;color:#f1f5f9!important}
.app-shell .content dialog[open] input:not([type=checkbox]):not([type=radio]),.app-shell .content dialog[open] select,.app-shell .content dialog[open] textarea,.app-shell .content dialog[open] .input,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:not([type=checkbox]):not([type=radio]),.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .input{background:#1f2a34!important;color:#f1f5f9!important;border-color:#526373!important;color-scheme:dark!important}
.app-shell .content dialog[open] table th,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table th{background:#19222b!important;color:#f1f5f9!important;border-color:#455767!important}
.app-shell .content dialog[open] table td,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table td{background:#334352!important;color:#e2e8f0!important;border-color:#455767!important}
.app-shell .content dialog[open] table tbody tr:nth-child(even) td,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table tbody tr:nth-child(even) td{background:#384956!important}
.app-shell .content dialog[open] .secondary-btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn{background:#384a5b!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .content dialog[open] .primary-btn,.app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .primary-btn{background:var(--cb-gold)!important;border-color:var(--cb-gold)!important;color:#19222b!important}
.app-shell .content .tracking-overlay,.app-shell .content .registry-info-overlay{background:rgba(10,15,20,.78)!important;backdrop-filter:blur(4px)}
.app-shell .content .dashboard-page>.welcome,.app-shell .content .dashboard-page .dashboard-stat,.app-shell .content .dashboard-page .dashboard-panel{background:#273440!important;border-color:#455767!important}
.app-shell .content .dashboard-page .dashboard-stat strong,.app-shell .content .dashboard-page .dashboard-panel h2,.app-shell .content .dashboard-page .service-row strong,.app-shell .content .dashboard-page .dashboard-activity strong{color:#f1f5f9!important}
.app-shell .content .dashboard-page .dashboard-stat-icon{background:#dfaa4333!important;color:#dfaa43!important;border-color:#dfaa4355!important}
.app-shell .content .dashboard-page .dashboard-badge{background:#dfaa4333!important;color:#f3d38c!important;border-color:#dfaa4355!important}
.app-shell .content .dashboard-page .service-row,.app-shell .content .dashboard-page .dashboard-activity,.app-shell .content .dashboard-page .dashboard-panel-head{border-color:#455767!important}
.app-shell .content .dashboard-page .activity-dot{background:#dfaa43!important;box-shadow:0 0 0 4px #dfaa4333!important}
.app-shell .chat-content{background:#1e2832!important;color:#e2e8f0!important}
.app-shell .chat-content .chat-list,.app-shell .chat-content .conversation,.app-shell .chat-content .contact-profile{background:#283542!important;border-color:#344555!important;color:#e2e8f0!important}
.app-shell .chat-content .chat-list-head,.app-shell .chat-content .conversation-head,.app-shell .chat-content .conversation-mode-bar,.app-shell .chat-content .contact-profile-head,.app-shell .chat-content .contact-profile-identity,.app-shell .chat-content .chat-composer{background:#19222b!important;border-color:#344555!important;color:#e2e8f0!important}
.app-shell .chat-content .chat-list-head h2,.app-shell .chat-content .conversation-head strong,.app-shell .chat-content .contact-profile-title,.app-shell .chat-content .contact-profile-identity strong{color:#f8fafc!important}
.app-shell .chat-content .chat-list-head p,.app-shell .chat-content .conversation-head small,.app-shell .chat-content .contact-profile-identity span,.app-shell .chat-content .profile-open-icon,.app-shell .chat-content .chat-group-title{color:#aab7c4!important}
.app-shell .chat-content .thread{background:#334352!important;border:1px solid #455767!important;border-color:#455767!important;color:#e2e8f0!important;box-shadow:none!important}
.app-shell .chat-content .thread:hover,.app-shell .chat-content .thread.selected{background:#485b6c!important;border-color:#dfaa43!important;border-left:3px solid #dfaa43!important;color:#fff!important;box-shadow:0 4px 12px rgba(0,0,0,.2)!important}
.app-shell .chat-content .thread-top{color:#f1f5f9!important}
.app-shell .chat-content .thread-top small,.app-shell .chat-content .thread-preview{color:#bac6d0!important}
.app-shell .chat-content .thread-avatar{background:#455767!important;border-color:#526373!important;color:#f3d38c!important;box-shadow:none!important}
.app-shell .chat-content .thread.selected .thread-avatar{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell .chat-content .thread-status{background:#183c36!important;border-color:#286b5b!important;color:#a7f3d0!important}
.app-shell .chat-content .thread-unread-count{background:#dfaa43!important;border-color:#273440!important;color:#19222b!important}
.app-shell .chat-content .chat-tab,.app-shell .chat-content .conversation-mode{background:#384a5b!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .chat-content .chat-tab.active,.app-shell .chat-content .conversation-mode.active{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell .chat-content .contact-avatar{background:#455767!important;color:#f3d38c!important}
.app-shell .chat-content .contact-profile{background:#273440!important;border-color:#455767!important;border-left:1px solid #455767!important;color:#e2e8f0!important}
.app-shell .chat-content .contact-profile-head{position:sticky;top:0;z-index:2;min-height:54px;background:#1f2c37!important;border-color:#455767!important}
.app-shell .chat-content .contact-profile-head button{width:28px;height:28px;border:1px solid #526373!important;border-radius:8px;background:#334352!important;color:#e2e8f0!important;font-size:19px!important;line-height:1}
.app-shell .chat-content .contact-profile-head button:hover{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell .chat-content .contact-profile-title{font-size:15px!important;color:#f1f5f9!important}
.app-shell .chat-content .contact-profile-identity{gap:7px;padding:20px 16px 18px;background:linear-gradient(145deg,#334858,#293a48)!important;border-bottom:1px solid #526373!important}
.app-shell .chat-content .contact-profile-identity .contact-avatar{width:68px;height:68px;margin-bottom:3px;border:2px solid #dfaa43!important;background:#273440!important;color:#f3d38c!important;box-shadow:0 0 0 4px #dfaa4320!important;font-size:25px!important}
.app-shell .chat-content .contact-profile-identity strong{font-size:17px!important;color:#f8fafc!important}
.app-shell .chat-content .contact-profile-identity>span:last-child{color:#dfaa43!important;font-size:13px!important;font-weight:700}
.app-shell .chat-content .contact-profile-section{border-color:#344555!important}
.app-shell .chat-content .contact-profile-section{padding:14px 14px!important}
.app-shell .chat-content .contact-profile-section h3{margin-bottom:10px!important;color:#dfaa43!important;font-size:10px!important;letter-spacing:.07em;text-transform:uppercase}
.app-shell .chat-content .contact-profile-detail,.app-shell .chat-content .contact-media-item{color:#e2e8f0!important}
.app-shell .chat-content .contact-profile-detail span,.app-shell .chat-content .contact-media-item small{color:#aab7c4!important}
.app-shell .chat-content .contact-profile-account .contact-profile-detail,.app-shell .chat-content .contact-profile-activity .contact-profile-detail{min-height:38px;margin:6px 0;padding:9px 11px!important;border:1px solid #455767!important;border-radius:10px;background:#334352!important}
.app-shell .chat-content .contact-profile-detail strong{color:#f3d38c!important;text-align:right;overflow-wrap:anywhere}
.app-shell .chat-content .contact-profile-action{background:#334352!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .chat-content .contact-profile-action:hover{background:#405464!important;border-color:#dfaa43!important}
.app-shell .chat-content .contact-media-item{align-items:center!important;min-height:76px;padding:8px!important;border:1px solid #455767!important;border-radius:11px;background:#334352!important;color:#e2e8f0!important}
.app-shell .chat-content .contact-media-item>span:last-child{min-width:0}
.app-shell .chat-content .contact-media-item strong{display:block;color:#f1f5f9!important}
.app-shell .chat-content .contact-media-item small{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;color:#bac6d0!important;line-height:1.35}
.app-shell .chat-content .contact-media-item .contact-media-thumbnail{width:58px;height:58px;border:1px solid #526373;background:#273440!important}
.app-shell .chat-content .contact-media-item[data-media-type="sticker"] .contact-media-thumbnail{padding:4px;object-fit:contain!important;background:#202d38!important;border:1px dashed #647789!important}
.app-shell .chat-content .contact-media-item[data-media-type="sticker"] .image-open-button{flex:0 0 58px;border-radius:9px;background:#202d38}
.app-shell .chat-content .contact-media-icon{background:#455767!important;color:#f3d38c!important}
.app-shell .chat-content .messages{background:#283542!important}
.app-shell .chat-content .message.in{background:#536778!important;border-color:#687e90!important;color:#f1f5f9!important}
.app-shell .chat-content .message.out{background:#384856!important;border:1px solid #dfaa4366!important;color:#f1f5f9!important}
.app-shell .chat-content .message-meta{color:#cbd5df!important}
.app-shell .chat-content .chat-composer{border-color:#344555!important}
.app-shell .chat-content .chat-composer .input{background:#1f2a34!important;border-color:#455767!important;color:#f1f5f9!important}
.app-shell .chat-content .chat-attach{background:#384a5b!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .chat-content .chat-send,.app-shell .chat-content .chat-composer button[type=submit]{background:#dfaa43!important;color:#19222b!important}
.app-shell .chat-content .image-preview-modal{background:#101820ed!important}
.app-shell .chat-content .contact-profile{border-left:1px solid #344555!important}
.app-shell .content .registry-create-modal,.app-shell .content .automatic-response-modal{background:#273440!important;color:#e2e8f0!important}
.app-shell .content .registry-create-modal .modal-head,.app-shell .content .automatic-response-modal .modal-head{background:#19222b!important}
.app-shell .content .registry-create-modal .campaign-filters,.app-shell .content .registry-create-modal .campaign-builder{background:#334352!important;color:#e2e8f0!important}
.app-shell .content .registry-create-modal .campaign-event-button.is-active{background:#dfaa43!important;color:#19222b!important}
.app-shell .content .campaign-loading-overlay{background:rgba(10,15,20,.78)!important}
.app-shell .content .campaign-loading-card{background:#273440!important;color:#e2e8f0!important;border-color:#455767!important}
.app-shell .content .campaign-loading-spinner{border-color:#455767;border-top-color:#dfaa43}
body:not(.night-mode) .app-shell .content.settings-page .settings-card,
body:not(.night-mode) .app-shell .content.settings-page .update-log-card,
body:not(.night-mode) .app-shell .content.settings-page .sync-now-card,
body:not(.night-mode) .app-shell .content.settings-page .custom-sync-card{background:#283542!important;border:1px solid #455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.settings-page .settings-heading,
body:not(.night-mode) .app-shell .content.settings-page .update-log-table th,
body:not(.night-mode) .app-shell .content.settings-page .update-log-table td{border-color:#455767!important}
body:not(.night-mode) .app-shell .content.settings-page .setting-row,
body:not(.night-mode) .app-shell .content.settings-page .custom-sync-grid form,
body:not(.night-mode) .app-shell .content.settings-page .service-info,
body:not(.night-mode) .app-shell .content.settings-page .sync-monitor-item{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.settings-page .setting-icon{background:#455767!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content.settings-page .settings-heading h2,
body:not(.night-mode) .app-shell .content.settings-page .update-log-card h2,
body:not(.night-mode) .app-shell .content.settings-page .custom-sync-grid h3,
body:not(.night-mode) .app-shell .content.settings-page .setting-copy strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content.settings-page .settings-heading p,
body:not(.night-mode) .app-shell .content.settings-page .setting-copy small,
body:not(.night-mode) .app-shell .content.settings-page .custom-sync-grid label,
body:not(.night-mode) .app-shell .content.settings-page .sync-monitor-item small{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content.settings-page .update-log-table th{background:#19222b!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.settings-page .update-log-table td,
body:not(.night-mode) .app-shell .content.settings-page .update-log-table tr:nth-child(even) td{background:#334352!important;color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content.settings-page .custom-sync-grid input[type=date]{background:#1f2a34!important;border-color:#526373!important;color:#f1f5f9!important;color-scheme:dark!important}
body:not(.night-mode) .app-shell .content.settings-page .secondary-btn{background:#384a5b!important;border-color:#526373!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content .automatic-response-filters .input,
body:not(.night-mode) .app-shell .content .automatic-response-form .input{background:#1f2a34!important;border-color:#455767!important;color:#f1f5f9!important;color-scheme:dark!important}
body:not(.night-mode) .app-shell .content .response-card{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content .response-card h3,
body:not(.night-mode) .app-shell .content .response-match-context>strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .response-meta,
body:not(.night-mode) .app-shell .content .response-copy{color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content .response-meta code{background:#273440!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .response-match-context{background:#273440!important;border-color:#526373!important;color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content .response-match-context p{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .response-match-examples>span{background:#384956!important;border-color:#526373!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .response-status.is-active{background:#183c36!important;color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content .response-status.is-inactive{background:#384956!important;color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal{background:linear-gradient(145deg,#273440,#2d3d4b 65%,#334352)!important;border-color:#526373!important;color:#e2e8f0!important;color-scheme:dark!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .modal-head{background:#19222b!important;border-color:#dfaa43!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .modal-head h2{color:#f8fafc!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .modal-head p{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .modal-close{background:#384a5b!important;color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .automatic-response-form label{color:#cbd5df!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .automatic-response-form label>small:not(.form-error){color:#aab7c4!important}
body:not(.night-mode) .app-shell .content dialog.automatic-response-modal .automatic-response-form .input{background:#1f2a34!important;border-color:#526373!important;color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content dialog[open],
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal){background:linear-gradient(145deg,#273440,#2d3d4b 65%,#334352)!important;border-color:#526373!important;color:#e2e8f0!important;color-scheme:dark!important;box-shadow:0 24px 70px rgba(0,0,0,.55)!important}
body:not(.night-mode) .app-shell .content dialog[open] .modal-head,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head{background:#19222b!important;border-color:#dfaa43!important}
body:not(.night-mode) .app-shell .content dialog[open] .modal-head h2,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head h2{color:#f8fafc!important}
body:not(.night-mode) .app-shell .content dialog[open] .modal-head p,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head p{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content dialog[open] .campaign-filters,
body:not(.night-mode) .app-shell .content dialog[open] .campaign-builder,
body:not(.night-mode) .app-shell .content dialog[open] .campaign-modal-body,
body:not(.night-mode) .app-shell .content dialog[open] .automatic-response-form{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content dialog[open] label,
body:not(.night-mode) .app-shell .content dialog[open] small{color:#cbd5df!important}
body:not(.night-mode) .app-shell .content dialog[open] input:not([type=checkbox]):not([type=radio]),
body:not(.night-mode) .app-shell .content dialog[open] select,
body:not(.night-mode) .app-shell .content dialog[open] textarea,
body:not(.night-mode) .app-shell .content dialog[open] .input{background:#1f2a34!important;border-color:#526373!important;color:#f1f5f9!important;color-scheme:dark!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-card{background:#283542!important;border-color:#455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-table th{background:#19222b!important;border-color:#455767!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-table td{background:#334352!important;border-color:#455767!important;color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-table tbody tr:nth-child(even) td{background:#2d3d4b!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-table tbody tr:hover td{background:#3c4e5e!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .card-title h2{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-summary-card>strong,
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-campaign-name strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-filters{background:#334352!important;border-color:#455767!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-summary-label,
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-filters-heading h2{color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat>span,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat small,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-panel p,
body:not(.night-mode) .app-shell .content.dashboard-page .service-row small,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-activity small{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat strong,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-panel h2,
body:not(.night-mode) .app-shell .content.dashboard-page .service-row strong,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-activity strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content.dashboard-page .service-row,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-activity{border-color:#455767!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat-icon.warning,
body:not(.night-mode) .app-shell .content.dashboard-page .service-symbol.warning,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat-icon.blue,
body:not(.night-mode) .app-shell .content.dashboard-page .service-symbol.blue,
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat-icon.purple,
body:not(.night-mode) .app-shell .content.dashboard-page .service-symbol.purple{background:#384956!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat-icon.green{background:#183c36!important;color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-badge{background:#183c36!important;color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content.dashboard-page .dashboard-stat-trigger:hover{border-color:#dfaa43!important;box-shadow:0 12px 28px rgba(0,0,0,.2)!important}
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-content,
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-note{background:#334352!important;border-color:#526373!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-value,
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-description,
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-value span{color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content dialog.dashboard-metric-dialog .dashboard-metric-note>span{background:#dfaa43!important;color:#19222b!important}
body:not(.night-mode) .app-shell .content .postal-template-module{--template-panel:#283542!important;--template-border:#455767!important;--template-text:#e2e8f0!important;--template-muted:#bac6d0!important;--template-input:#1f2a34!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content .template-page-header h1{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .template-page-header p{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .template-category-tabs{background:#273440!important;border-color:#455767!important}
body:not(.night-mode) .app-shell .content .template-category-tab{color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content .template-category-tab:hover{background:#384956!important}
body:not(.night-mode) .app-shell .content .template-category-tab.is-active{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
body:not(.night-mode) .app-shell .content .template-category-badge{background:#384956!important;border-color:#526373!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .template-chat-preview{background:#1f2a34!important;border-color:#455767!important;background-image:none!important}
body:not(.night-mode) .app-shell .content .template-chat-message{background:#334352!important;border-color:#526373!important;color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .template-chat-message>small{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .template-quick-tags button{background:#384956!important;border-color:#526373!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .template-live-badge{background:#183c36!important;border-color:#286b5b!important;color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content .ai-chat-header,
body:not(.night-mode) .app-shell .content .ai-composer{background:#19222b!important;border-color:#455767!important}
body:not(.night-mode) .app-shell .content .ai-chat-header h3,
body:not(.night-mode) .app-shell .content .ai-empty strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .ai-chat-header small,
body:not(.night-mode) .app-shell .content .ai-empty,
body:not(.night-mode) .app-shell .content .ai-message small{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .ai-chat-messages{background:#1f2a34!important}
body:not(.night-mode) .app-shell .content .ai-agent{background:#334352!important;border-color:#526373!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content .ai-user{background:#dfaa43!important;color:#19222b!important}
body:not(.night-mode) .app-shell .content .ai-error{color:#fca5a5!important}
body:not(.night-mode) .app-shell .content .ai-online{color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content .test-number-heading h2,
body:not(.night-mode) .app-shell .content .test-number-details strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .test-number-field{color:#cbd5df!important}
body:not(.night-mode) .app-shell .content .test-number-hint,
body:not(.night-mode) .app-shell .content .test-number-heading p,
body:not(.night-mode) .app-shell .content .test-number-details span{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .test-number-row,
body:not(.night-mode) .app-shell .content .test-number-empty{background:#334352!important;border-color:#455767!important;color:#dbe3eb!important}
body:not(.night-mode) .app-shell .content .test-number-avatar{background:#455767!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .test-number-count,
body:not(.night-mode) .app-shell .content .test-number-badge{background:#384956!important;border-color:#526373!important;color:#f3d38c!important}
body:not(.night-mode) .app-shell .content .test-number-edit{color:#e6bc6d!important}
body:not(.night-mode) .app-shell .content .test-number-cancel{color:#bac6d0!important}
body:not(.night-mode) .app-shell .content .test-number-empty strong{color:#f1f5f9!important}
body:not(.night-mode) .app-shell .content .test-number-feedback{background:#183c36!important;border-color:#286b5b!important;color:#a7f3d0!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-action-group{gap:8px!important;flex-wrap:nowrap!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-action-group button{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;min-height:30px!important;margin:0!important;padding:6px 12px!important;border-radius:999px!important;font-size:12px!important;font-weight:700!important;line-height:1!important;white-space:nowrap!important;box-shadow:none!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-send-btn{background:#dfaa43!important;border:1px solid #dfaa43!important;color:#19222b!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-action-group .registry-send-btn{padding-right:16px!important;padding-left:16px!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-send-btn:hover{background:#c99738!important;border-color:#c99738!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-cancel-btn{background:#1f2c37!important;border:1px solid #526373!important;color:#e2e8f0!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-actions .registry-cancel-btn:hover{background:#344555!important;border-color:#718396!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-status-badge{padding:4px 12px!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-status-badge.status-prepared{background:#2a3947!important;border-color:#a67c23!important;color:#f0b547!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-status-badge.status-prepared i{background:#f0b547!important}
body:not(.night-mode) .app-shell .content.postal-registry-page .registry-state-counts{font-size:10px!important;color:#9bb0c4!important}
@media(max-width:600px){.app-shell .content:not(.dashboard-page) .welcome{align-items:flex-start;flex-direction:column;gap:10px;padding:12px 14px!important}.app-shell .content:not(.dashboard-page) .welcome>.primary-btn{margin:0!important}.app-shell .content.dashboard-page>.welcome{align-items:flex-start;gap:12px;padding:14px 20px!important}}
.app-shell ::-webkit-scrollbar{width:7px;height:7px}
.app-shell ::-webkit-scrollbar-track{background:rgba(255,255,255,.04);border-radius:4px}
.app-shell ::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px}
.app-shell ::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.32)}
@media(max-width:650px){.app-shell .content{padding:18px 12px!important}.app-shell .chat-content{padding:10px!important}}
`;

window.addEventListener('system-notification', (event) => { const notice = document.createElement('div'); notice.className = 'system-notification'; notice.textContent = event.detail?.message || 'No se pudo completar la operación.'; document.body.appendChild(notice); setTimeout(() => notice.remove(), 8000); });

if (document.querySelector('.sidebar')) {
    document.body.classList.remove('night-mode');
    document.documentElement.style.colorScheme = 'dark';
    localStorage.removeItem('crm-theme');

    if (location.pathname.startsWith('/campanas-postales')) {
        const campaignStyle = document.createElement('style'); campaignStyle.textContent = '.content label:has(input[readonly]){display:none!important}'; document.head.appendChild(campaignStyle);
    }
    document.querySelectorAll('.sidebar .nav-item[href]').forEach((item) => {
        const label = item.querySelector('em')?.textContent.trim() || item.textContent.trim();
        item.title = label;
        item.setAttribute('aria-label', label);
    });
    document.querySelectorAll('.sidebar .nav-group > summary').forEach((summary) => {
        const label = summary.textContent.trim().replace(/^[^\s]+\s*/, '');
        summary.title = label;
        summary.setAttribute('aria-label', label);
    });
    const logoutButton = document.querySelector('.sidebar-bottom button.nav-item');
    if (logoutButton) {
        logoutButton.title = 'Cerrar sesión';
        logoutButton.setAttribute('aria-label', 'Cerrar sesión');
    }
    const style = document.createElement('style');
      style.textContent = dashboardStyles + sidebarStyles + correosBoliviaTheme;
      document.body.appendChild(style);
      const responsiveStyle = document.createElement('style');
      responsiveStyle.textContent = '.content{width:100%!important;max-width:none!important;min-width:0}.sidebar{width:190px!important;padding:14px 8px!important}.sidebar .brand{margin-bottom:16px!important}.main{width:calc(100% - 190px)}@media(max-width:900px){.sidebar{width:70px!important;padding:16px 8px!important}.main{width:calc(100% - 70px)}}@media(max-width:560px){.sidebar{width:58px!important}.main{width:calc(100% - 58px)}}';
      document.body.appendChild(responsiveStyle);

    const darkStyles = `
body.night-mode{background:#071426}
    body.night-mode .app-shell .main{background-color:#071426!important;background-image:linear-gradient(rgba(250,204,21,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(250,204,21,.035) 1px,transparent 1px),radial-gradient(ellipse at 85% 15%,rgba(234,179,8,.12),transparent 38%)!important;background-size:40px 40px,40px 40px,auto!important}
body.night-mode .app-shell .content{color:#f8fafc!important;background:radial-gradient(ellipse at 95% 0%,rgba(234,179,8,.08),transparent 38%)}
body.night-mode .app-shell .content .welcome h1{color:#f8fafc!important}
body.night-mode .app-shell .content .welcome p{color:#b7c4d8!important}
body.night-mode .app-shell .content .card,body.night-mode .app-shell .content.postal-registry-page .registry-card{background:#111f37!important;border-color:#334766!important;color:#f8fafc!important;box-shadow:0 4px 14px rgba(0,0,0,.2)!important}
    body.night-mode .app-shell .content .settings-card,body.night-mode .app-shell .content .prompt-manager-card,body.night-mode .app-shell .content .automatic-responses-card,body.night-mode .app-shell .content .response-card,body.night-mode .app-shell .content .template-editor-card,body.night-mode .app-shell .content .template-preview-card,body.night-mode .app-shell .content .package-table-card,body.night-mode .app-shell .content .audit-module,body.night-mode .app-shell .content .dashboard-stat,body.night-mode .app-shell .content .dashboard-panel{background:#111f37!important;border-color:#334766!important;color:#f8fafc!important}
    body.night-mode .app-shell .content .registry-summary-card,body.night-mode .app-shell .content .registry-table-card,body.night-mode .app-shell .content .registry-filters-panel{background:#111f37!important;border-color:#334766!important;color:#f8fafc!important}
    body.night-mode .app-shell .content .registry-summary-label,body.night-mode .app-shell .content .registry-summary-card>small,body.night-mode .app-shell .content .registry-filters-heading h2{color:#e2e8f0!important}
    body.night-mode .app-shell .content .registry-summary-card>strong{color:#f8fafc!important}
    body.night-mode .app-shell .content .registry-table-footer{background:#0b192c!important;color:#e2e8f0!important}
    body.night-mode .app-shell .content .registry-pagination .secondary-btn:not(:disabled){background:#16243a!important;border-color:#405472!important;color:#facc15!important}
    body.night-mode .app-shell .content h1,body.night-mode .app-shell .content .card-title h2,body.night-mode .app-shell .content .card-title h3,body.night-mode .app-shell .content .settings-heading h2{color:#f8fafc!important}
    body.night-mode .app-shell .content label,body.night-mode .app-shell .content legend,body.night-mode .app-shell .content .empty-row,body.night-mode .app-shell .content .empty-log,body.night-mode .app-shell .content .dashboard-empty{color:#cbd5e1}
    body.night-mode .app-shell .content .result-count{background:#16243a;border-color:#405472;color:#facc15!important}
    body.night-mode .app-shell .content .custom-sync-grid form,body.night-mode .app-shell .content .sync-monitor-item{border-color:#334766;background:#0b192c;color:#e2e8f0}
    body.night-mode .app-shell .content .custom-sync-grid h3{color:#f8fafc}
    body.night-mode .app-shell .content .custom-sync-grid label{color:#cbd5e1}
    body.night-mode .app-shell .content nav[role="navigation"] a,body.night-mode .app-shell .content nav[role="navigation"] button{border-color:#405472!important;background:#16243a;color:#e2e8f0!important}
    body.night-mode .app-shell .content nav[role="navigation"] a[aria-current="page"],body.night-mode .app-shell .content nav[role="navigation"] button[aria-current="page"]{background:#facc15!important;color:#0b192c!important}
    body.night-mode .app-shell .chat-list,body.night-mode .app-shell .contact-profile{background:#111f37!important;border-color:#334766!important;color:#e2e8f0}
    body.night-mode .app-shell .conversation{background:#0b1426!important;border-color:#334766!important}
    body.night-mode .app-shell .chat-list-head,body.night-mode .app-shell .conversation-head,body.night-mode .app-shell .contact-profile-head,body.night-mode .app-shell .contact-profile-identity{background:#111f37!important;border-color:#334766!important}
    body.night-mode .app-shell .chat-list-head h2,body.night-mode .app-shell .conversation-head strong,body.night-mode .app-shell .contact-profile-title,body.night-mode .app-shell .contact-profile-identity strong{color:#f8fafc!important}
    body.night-mode .app-shell .chat-tabs .chat-tab.active{background:#facc15!important;border-color:#eab308!important;color:#0b192c!important}
    body.night-mode .app-shell .chat-tabs .chat-tab:not(.active),body.night-mode .app-shell .conversation-mode{background:#16243a!important;border-color:#405472!important;color:#e2e8f0!important}
    body.night-mode .app-shell .conversation-mode.active,body.night-mode .app-shell .conversation-mode:hover,body.night-mode .app-shell .thread:hover,body.night-mode .app-shell .thread.selected{background:#3b2d12!important;border-color:#ca8a04!important;color:#fef3c7!important}
    body.night-mode .app-shell .thread{border-bottom-color:#334766!important;color:#e2e8f0}
    body.night-mode .app-shell .contact-avatar,body.night-mode .app-shell .contact-media-icon{background:#1e3a8a!important;color:#facc15!important}
    body.night-mode .app-shell .messages,body.night-mode .app-shell .ai-chat-messages{background:#0b1426!important}
    body.night-mode .app-shell .message.out,body.night-mode .app-shell .ai-message.ai-user{background:#facc15!important;color:#0b192c!important}
    body.night-mode .app-shell .message.in,body.night-mode .app-shell .ai-message.ai-agent{background:#172640!important;border-color:#334766!important;color:#e2e8f0!important}
    body.night-mode .app-shell .chat-composer,body.night-mode .app-shell .ai-composer{background:#111f37!important;border-color:#334766!important}
    body.night-mode .app-shell .chat-attach{background:#16243a!important;border-color:#405472!important;color:#facc15!important}
    body.night-mode .app-shell .ai-empty strong{color:#f8fafc!important}
body.night-mode .app-shell .content .card-title h2,body.night-mode .app-shell .content .card-title h3,body.night-mode .app-shell .content .settings-heading h2{color:#f8fafc!important}
body.night-mode .app-shell .content .card-title p,body.night-mode .app-shell .content .card-title small,body.night-mode .app-shell .content .filter-help{color:#b7c4d8!important}
body.night-mode .app-shell .content input:not([type=checkbox]):not([type=radio]),body.night-mode .app-shell .content select,body.night-mode .app-shell .content textarea,body.night-mode .app-shell .content .input{background:#0b192c!important;color:#f8fafc!important;border-color:#526987!important}
body.night-mode .app-shell .content table thead th,body.night-mode .app-shell .content.postal-registry-page .registry-table th,body.night-mode .app-shell .content.operations-page .operations-table th,body.night-mode .app-shell .content.settings-page .update-log-table th,body.night-mode .app-shell .content.audit-page .audit-table th{background:#facc15!important;color:#0b192c!important;border-color:#eab308!important}
body.night-mode .app-shell .content table tbody td,body.night-mode .app-shell .content.postal-registry-page .registry-table td,body.night-mode .app-shell .content.operations-page .operations-table td,body.night-mode .app-shell .content.settings-page .update-log-table td,body.night-mode .app-shell .content.audit-page .audit-table td{background:#111f37!important;color:#e2e8f0!important;border-color:#334766!important}
body.night-mode .app-shell .content table tbody tr:nth-child(even) td,body.night-mode .app-shell .content.postal-registry-page .registry-table tbody tr:nth-child(even) td,body.night-mode .app-shell .content.operations-page .operations-table tbody tr:nth-child(even) td,body.night-mode .app-shell .content.settings-page .update-log-table tbody tr:nth-child(even) td,body.night-mode .app-shell .content.audit-page .audit-table tbody tr:nth-child(even) td{background:#16243a!important}
body.night-mode .app-shell .content table tbody tr:hover td,body.night-mode .app-shell .content.postal-registry-page .registry-table tbody tr:hover td,body.night-mode .app-shell .content.operations-page .operations-table tbody tr:hover td,body.night-mode .app-shell .content.settings-page .update-log-table tbody tr:hover td,body.night-mode .app-shell .content.audit-page .audit-table tbody tr:hover td{background:#223651!important}
body.night-mode .app-shell .content .campaign-history-modal,body.night-mode .app-shell .content dialog{background:#0b192c!important;color:#f8fafc!important;border-color:#eab308!important}
body.night-mode .app-shell .content .campaign-history-modal h2,body.night-mode .app-shell .content .campaign-history-modal strong{color:#f8fafc!important}
body.night-mode .app-shell .content .campaign-history-modal p,body.night-mode .app-shell .content .campaign-history-modal small{color:#b7c4d8!important}
body.night-mode .app-shell dialog[open],body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal),body.night-mode .app-shell .content .campaign-history-modal{background:linear-gradient(160deg,#0b192c 0,#102039 230px)!important;border-color:#31527a!important;box-shadow:0 24px 70px rgba(0,0,0,.55),0 0 0 4px rgba(250,204,21,.12)!important}
body.night-mode .app-shell dialog[open] .modal-head,body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head,body.night-mode .app-shell .content .campaign-history-modal .modal-head{background:linear-gradient(110deg,#08172d 0%,#102a4c 70%,#1e3a5f 100%)!important;border-bottom-color:#eab308!important}
body.night-mode .app-shell dialog[open] .modal-head h2,body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head h2,body.night-mode .app-shell .content .campaign-history-modal .modal-head h2{color:#fff!important}
body.night-mode .app-shell dialog[open] .modal-head p,body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head p,body.night-mode .app-shell .content .campaign-history-modal .modal-head p{color:#e0e7ff!important}
body.night-mode .app-shell .content dialog[open] .campaign-modal-body,body.night-mode .app-shell .content dialog[open] .automatic-response-form,body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary{background:linear-gradient(135deg,#102039,#142943)!important;color:#e2e8f0!important}
body.night-mode .app-shell .content dialog[open] .campaign-info-grid>div,body.night-mode .app-shell .content dialog[open] .registry-info-summary>span,body.night-mode .app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary>span{background:#1b3150!important;border-color:#405b7a!important;color:#dbeafe!important}
body.night-mode .app-shell .content .modal-close{border-color:#405472!important;background:#16243a!important;color:#f8fafc!important}
body.night-mode .app-shell .content .modal-close:hover{border-color:#eab308!important;background:#26344a!important;color:#facc15!important}
body.night-mode .app-shell .content .modal-close:focus-visible{outline-color:#facc15!important}
body.night-mode .app-shell .content a:not(.nav-item){color:#facc15}
body.night-mode .app-shell .content .operations-tabs .is-active,body.night-mode .app-shell .content .campaign-event-button.is-active{background:#facc15!important;color:#0b192c!important;border-color:#eab308!important}
body.night-mode .app-shell .content.dashboard-page .dashboard-stat strong,body.night-mode .app-shell .content.dashboard-page .dashboard-panel h2,body.night-mode .app-shell .content.dashboard-page .service-row strong,body.night-mode .app-shell .content.dashboard-page .dashboard-activity strong{color:#f8fafc!important}
body.night-mode .app-shell .content.dashboard-page .dashboard-stat>span,body.night-mode .app-shell .content.dashboard-page .dashboard-stat small,body.night-mode .app-shell .content.dashboard-page .dashboard-panel p,body.night-mode .app-shell .content.dashboard-page .service-row small,body.night-mode .app-shell .content.dashboard-page .dashboard-activity small{color:#b7c4d8!important}
body.night-mode .app-shell .content.dashboard-page .service-row,body.night-mode .app-shell .content.dashboard-page .dashboard-activity{border-color:#334766}
body:not(.night-mode) .app-shell .content dialog[open],
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal),
body.night-mode .app-shell .content dialog[open],
body.night-mode .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal){
    background:#283542!important;
    background-image:none!important;
    border-color:#526373!important;
    color:#e2e8f0!important;
    color-scheme:dark!important
}
body:not(.night-mode) .app-shell .content dialog[open] .modal-head,
body:not(.night-mode) .app-shell .content dialog[open] .ems-quote-modal-head,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .ems-quote-modal-head,
body.night-mode .app-shell .content dialog[open] .modal-head,
body.night-mode .app-shell .content dialog[open] .ems-quote-modal-head,
body.night-mode .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head,
body.night-mode .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .ems-quote-modal-head{
    background:#19222b!important;
    background-image:none!important;
    border-color:#526373!important;
    color:#f8fafc!important
}
body:not(.night-mode) .app-shell .content dialog[open] .campaign-filters,
body:not(.night-mode) .app-shell .content dialog[open] .campaign-builder,
body:not(.night-mode) .app-shell .content dialog[open] .campaign-modal-body,
body:not(.night-mode) .app-shell .content dialog[open] .automatic-response-form,
body:not(.night-mode) .app-shell .content dialog[open] .tracking-detail-summary,
body:not(.night-mode) .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .ems-quote-modal-body,
body.night-mode .app-shell .content dialog[open] .campaign-filters,
body.night-mode .app-shell .content dialog[open] .campaign-builder,
body.night-mode .app-shell .content dialog[open] .campaign-modal-body,
body.night-mode .app-shell .content dialog[open] .automatic-response-form,
body.night-mode .app-shell .content dialog[open] .tracking-detail-summary,
body.night-mode .app-shell .content [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .ems-quote-modal-body{
    background:#334352!important;
    background-image:none!important;
    border-color:#526373!important;
    color:#e2e8f0!important
}
body.night-mode .app-shell .topbar{background:#0b192c!important;border-bottom-color:#334766!important}
body.night-mode .app-shell .topbar .search{background:#16243a!important;border-color:#405472!important;color:#dbe5f0!important}
body.night-mode .app-shell .topbar .profile strong{color:#f8fafc}
body.night-mode .app-shell .topbar .profile small{color:#b7c4d8}
body.night-mode .app-shell .topbar .profile{border-color:#334766}
.app-shell dialog[open],.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal){color-scheme:dark!important;background:#273440!important;border:1px solid #526373!important;color:#e2e8f0!important}
.app-shell dialog[open] .modal-head,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head{background:#19222b!important;border-color:#526373!important;color:#f8fafc!important}
.app-shell dialog[open] .modal-head h2,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head h2{color:#f8fafc!important}
.app-shell dialog[open] .modal-head p,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-head p{color:#cbd5df!important}
.app-shell dialog[open] h2,.app-shell dialog[open] h3,.app-shell dialog[open] strong,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) h2,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) h3,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) strong{color:#f1f5f9!important}
.app-shell dialog[open] label,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) label{color:#dbe3eb!important}
.app-shell dialog[open] p,.app-shell dialog[open] small,.app-shell dialog[open] .filter-help,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) p,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) small{color:#bac6d0!important}
.app-shell dialog[open] .form-error,.app-shell dialog[open] .error,.app-shell dialog[open] .registry-message-error,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .form-error,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .error,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .registry-message-error{color:#fca5a5!important}
.app-shell dialog[open] .campaign-filters,.app-shell dialog[open] .campaign-builder,.app-shell dialog[open] .campaign-modal-body,.app-shell dialog[open] .automatic-response-form,.app-shell dialog[open] .card,.app-shell dialog[open] .campaign-info-grid>div,.app-shell dialog[open] .registry-info-summary>span,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .card,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .tracking-detail-summary>span{background:#334352!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell dialog[open] input:not([type=checkbox]):not([type=radio]),.app-shell dialog[open] select,.app-shell dialog[open] textarea,.app-shell dialog[open] .input,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:not([type=checkbox]):not([type=radio]),.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .input{background:#1f2a34!important;border-color:#647789!important;color:#f8fafc!important;color-scheme:dark!important}
.app-shell dialog[open] input::placeholder,.app-shell dialog[open] textarea::placeholder,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input::placeholder,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea::placeholder{color:#aab7c4!important;opacity:1}
.app-shell dialog[open] select option,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select option{background:#1f2a34!important;color:#f8fafc!important}
.app-shell dialog[open] input:focus,.app-shell dialog[open] select:focus,.app-shell dialog[open] textarea:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) input:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) select:focus,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) textarea:focus{border-color:#dfaa43!important;box-shadow:0 0 0 3px #dfaa4333!important;outline:none}
.app-shell dialog[open] table th,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table th{background:#19222b!important;border-color:#526373!important;color:#f1f5f9!important}
.app-shell dialog[open] table td,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table td{background:#334352!important;border-color:#455767!important;color:#e2e8f0!important}
.app-shell dialog[open] table tbody tr:nth-child(even) td,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) table tbody tr:nth-child(even) td{background:#2d3d4b!important}
.app-shell dialog[open] .secondary-btn,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn{background:#384a5b!important;border-color:#647789!important;color:#f1f5f9!important}
.app-shell dialog[open] .secondary-btn:hover,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .secondary-btn:hover{background:#45596b!important;border-color:#8496a7!important}
.app-shell dialog[open] .primary-btn,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .primary-btn{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell dialog[open] .modal-close,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-close{background:#384a5b!important;border-color:#647789!important;color:#f1f5f9!important}
.app-shell dialog[open] .modal-close:hover,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .modal-close:hover{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell dialog[open] .campaign-event-button.is-active,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .campaign-event-button.is-active{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important}
.app-shell dialog[open] .success,.app-shell dialog[open] .registry-replied,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .success,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .registry-replied{color:#86efac!important}
.app-shell dialog[open] .danger,.app-shell dialog[open] .registry-no-reply,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .danger,.app-shell [role="dialog"][aria-modal="true"]:not(.image-preview-modal) .registry-no-reply{color:#fca5a5!important}
.app-shell .content [role="dialog"].ems-quote-modal{background:#334352!important;background-image:none!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-modal-head,.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-modal-body{background:#334352!important;background-image:none!important;border-color:#526373!important;color:#e2e8f0!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-modal-head h2{color:#f1f5f9!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-modal-head p{color:#cbd5df!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-result{background:#3b4b59!important;border-color:#657687!important;color:#e2e8f0!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-result strong{color:#f3c969!important}
.app-shell .content [role="dialog"].ems-quote-modal .ems-quote-result span{color:#e2e8f0!important}
`;

}

if (document.querySelector('.dashboard-grid') && localStorage.getItem('crm-aiMode') !== 'false') {
    const assistant = document.createElement('button');
    assistant.textContent = '  Asistente IA';
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
