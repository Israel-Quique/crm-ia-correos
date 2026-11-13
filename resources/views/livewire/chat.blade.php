<div wire:poll.5s="refreshChat" class="chat-content" x-data="{ previewImage: null }" @keydown.escape.window="previewImage = null">
    <style>
        .chat-content{padding:20px;height:calc(100vh - 76px);display:flex;min-height:0}
        .chat-list,.conversation{background:#f7f8fa;border:1px solid #d5deea;overflow:hidden}
        .chat-list{width:clamp(280px,27vw,320px);flex-shrink:0;border-radius:14px 0 0 14px;overflow:hidden;display:flex;flex-direction:column}
        .chat-list-head{padding:16px;border-bottom:1px solid #d5deea}
        .chat-list-head h2{font-size:16px;margin:0}
        .chat-list-head p{color:#64748b;font-size:12px}
        .chat-tabs{display:flex;gap:6px;margin-top:12px}
        .chat-tab{border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:7px 9px;font-size:11px;cursor:pointer}
        .chat-tab.active{background:#2875ed;color:#fff}
        .chat-group-title{padding:10px 14px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase}
        .chat-thread-list{flex:1;min-height:0;overflow:auto;scrollbar-width:thin;scrollbar-color:#d5deea transparent}
        .chat-thread-list::-webkit-scrollbar{width:7px}
        .chat-thread-list::-webkit-scrollbar-thumb{border-radius:999px;background:#d5deea}
        .thread{display:block;width:100%;padding:13px 14px;border:0;border-bottom:1px solid #e2e8f0;text-align:left;background:transparent;cursor:pointer}
        .thread:hover,.thread.selected{background:#fff8e8}
        .thread-top{display:flex;justify-content:space-between;font-size:12px;font-weight:700}
        .thread-top small{color:#94a3b8;font-weight:400}
        .thread-preview{color:#64748b;font-size:11px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .thread-status{display:inline-block;font-size:10px;background:#d1fae5;color:#047857;padding:3px 8px;border-radius:20px;margin-top:7px}
        .thread-unread-count{display:inline-grid;place-items:center;min-width:20px;height:20px;margin-left:6px;padding:0 6px;border-radius:999px;background:#16a34a;color:#fff;font-size:11px;font-weight:700}
        .conversation{flex:1;min-width:0;display:flex;flex-direction:column;background:#eef2f7;border-radius:0 14px 14px 0}
        .conversation-head{display:flex;align-items:center;gap:12px;width:100%;border:0;background:#fff;padding:12px 20px;border-bottom:1px solid #d5deea;text-align:left;font:inherit;cursor:pointer}
        .conversation-head:hover{background:#f8fafc}
        .conversation-mode-bar{display:flex;align-items:center;gap:7px;padding:7px 20px;background:#f8fafc;border-bottom:1px solid #d5deea;color:#64748b;font-size:11px}.conversation-mode{border:1px solid #cbd5e1;border-radius:999px;padding:5px 10px;background:#fff;color:#475569;font-size:11px;cursor:pointer}.conversation-mode:hover,.conversation-mode.active{background:#fff8e8;border-color:#f59e0b;color:#b45309;font-weight:700}
        .conversation-head-copy{min-width:0;flex:1}
        .conversation-head strong,.conversation-head small{display:block}
        .conversation-head small{color:#64748b;margin-top:4px}
        .contact-avatar{display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-size:17px;font-weight:800}
        .profile-open-icon{color:#64748b;font-size:20px}
        .contact-profile{width:320px;flex-shrink:0;overflow:auto;background:#f7f8fa;border:1px solid #d5deea;border-left:0;border-radius:0 14px 14px 0}
        .contact-profile-head{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #d5deea;background:#fff}
        .contact-profile-head button{border:0;background:transparent;color:#64748b;font-size:22px;cursor:pointer}
        .contact-profile-title{font-weight:700;color:#172033}
        .contact-profile-identity{display:grid;justify-items:center;gap:10px;padding:28px 16px 22px;background:#fff;border-bottom:8px solid #eef2f7}
        .contact-profile-identity .contact-avatar{width:92px;height:92px;font-size:34px}
        .contact-profile-identity strong{font-size:18px;color:#172033;text-align:center;overflow-wrap:anywhere}
        .contact-profile-identity span{color:#64748b;font-size:14px}
        .contact-profile-section{padding:18px 16px;border-bottom:1px solid #d5deea}
        .contact-profile-section h3{margin:0 0 14px;font-size:13px;color:#64748b}
        .contact-profile-detail{display:flex;justify-content:space-between;gap:12px;padding:9px 0;color:#334155;font-size:13px}
        .contact-profile-detail span{color:#64748b}
        .contact-profile-action{display:block;width:100%;margin-top:8px;padding:10px 12px;border:1px solid #d5deea;border-radius:8px;background:#fff;color:#334155;text-align:left;font:inherit;font-size:13px;cursor:pointer}
        .contact-profile-action:hover{background:#f8fafc}
        .contact-profile-action.danger{color:#dc2626}
        .contact-media-list{display:grid;gap:10px}
        .contact-media-item{display:flex;align-items:flex-start;gap:10px;color:#334155;font-size:13px}
        .contact-media-icon{display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:8px;background:#e0eaff;color:#1d4ed8;font-size:16px}
        .contact-media-item strong,.contact-media-item small{display:block}
        .contact-media-item small{margin-top:4px;color:#64748b}
        .contact-media-thumbnail{display:block;width:58px;height:58px;flex:0 0 58px;object-fit:cover;border-radius:8px;background:#e2e8f0}
        .message-image{display:block;max-width:min(360px,100%);max-height:360px;object-fit:contain;border-radius:10px;margin-bottom:6px}.message-sticker{width:150px;height:150px;max-width:150px;max-height:150px;background:transparent}
        .image-open-button{display:block;padding:0;border:0;background:transparent;cursor:zoom-in}
        .image-preview-modal{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:56px 24px 24px;background:#020617e8}
        .image-preview-modal[x-cloak]{display:none}
        .image-preview-content{position:relative;display:grid;place-items:center;width:min(1100px,100%);height:100%;margin:auto}
        .image-preview-content img{display:block;max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;box-shadow:0 12px 50px #0008}
        .image-preview-close{position:absolute;top:-42px;right:0;width:38px;height:38px;border:1px solid #ffffff55;border-radius:50%;background:#172033;color:#fff;font-size:26px;line-height:1;cursor:pointer}
        .image-preview-hint{position:absolute;top:-34px;left:0;color:#e2e8f0;font-size:13px}
        .contact-profile-empty{color:#64748b;font-size:13px}
        .contact-link{display:block;margin-top:8px;color:#2563eb;font-size:12px;overflow-wrap:anywhere}
        .messages{padding:22px;flex:1;min-height:0;overflow:auto;display:flex;flex-direction:column;gap:10px}
        .message{max-width:min(78%,720px);padding:11px 14px;border-radius:16px;font-size:14px;line-height:1.5;overflow-wrap:anywhere;box-shadow:0 1px 2px #0f172a12}
        .message.out{background:#2875ed;color:#fff;margin-left:auto;border-bottom-right-radius:5px}
        .message.in{background:#fff;color:#172033;margin-right:auto;border:1px solid #d5deea;border-bottom-left-radius:5px}
        .message.campaign-message-focused{outline:2px solid #f59e0b;outline-offset:3px;scroll-margin-block:40px}
        .message-body{white-space:pre-wrap}
        .message-source{font-size:10px;font-weight:700;opacity:.8;margin-bottom:5px}
        .message-meta{display:flex;justify-content:flex-end;align-items:center;gap:6px;font-size:10px;line-height:1.2;opacity:.82;margin-top:7px}
        .message-status-icon{font-size:13px;letter-spacing:-3px;padding-right:3px;font-weight:700}
        .message-status-icon.read{color:#a7f3d0;opacity:1}
        .empty-chat,.personal-empty{padding:30px;color:#64748b;text-align:center}
        .chat-composer{display:flex;align-items:center;gap:8px;background:#fff;padding:12px;border-top:1px solid #d5deea}.chat-composer .input{flex:1;min-width:0}.chat-attach{position:relative;display:grid;place-items:center;width:42px;height:42px;flex:0 0 42px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#334155;cursor:pointer;font-size:21px}.chat-attach:hover{background:#e2e8f0}.chat-attach input{position:absolute;width:1px;height:1px;opacity:0}.chat-file-name{max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;font-size:11px}.chat-send{display:grid;place-items:center;width:44px;height:42px;flex:0 0 44px;padding:0!important;border-radius:10px!important;font-size:20px!important}.chat-send-file{display:none}
        .chat-error{color:#dc2626;padding:0 12px 10px;background:#fff}
        .app-shell .chat-content{padding:16px;height:calc(100dvh - 74px);gap:0}
        .app-shell .chat-content .chat-list{width:clamp(280px,24vw,320px);background:#fff;border-right-color:#eab308}
        .app-shell .chat-content .chat-list-head{padding:14px 14px 11px}
        .app-shell .chat-content .chat-list-head h2{font-size:17px;line-height:1.2;letter-spacing:-.2px}
        .app-shell .chat-content .chat-list-head p{margin:5px 0 0;overflow:hidden;font-size:10px;line-height:1.45;text-overflow:ellipsis;white-space:nowrap}
        .app-shell .chat-content .chat-list-head{position:relative;background:linear-gradient(145deg,#fff 0%,#fffcf1 100%);border-bottom-color:#f1dfaa}
        .app-shell .chat-content .chat-list-head h2{font-size:17px;letter-spacing:-.2px}
        .app-shell .chat-content .chat-phone-search{display:flex;align-items:center;gap:8px;height:40px;margin-top:12px;padding:0 11px;border:1px solid #526373;border-radius:10px;background:#2d3d4b;color:#bac6d0}
        .app-shell .chat-content .chat-phone-search>span{flex:none;font-size:20px;line-height:1}
        .app-shell .chat-content .chat-phone-search input{width:100%;min-width:0;height:100%;padding:0!important;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;color:#f1f5f9!important;font-size:12px!important}
        .app-shell .chat-content .chat-phone-search input::placeholder{color:#bac6d0!important;opacity:1}
        .app-shell .chat-content .chat-phone-search:focus-within{border-color:#dfaa43;box-shadow:0 0 0 3px #dfaa4326}
        .app-shell .chat-content .chat-tabs{margin-top:10px;padding:4px;border:1px solid #526373;border-radius:12px;background:#202c36}
        .app-shell .chat-content .chat-tabs .chat-tab{flex:1;min-width:0;min-height:42px;border:1px solid #526373!important;border-radius:9px;padding:8px 5px!important;background:#2d3d4b!important;color:#e2e8f0!important;font-size:11px!important;font-weight:700;line-height:1.2;white-space:nowrap;box-shadow:none!important;transition:background .18s ease,border-color .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}
        .app-shell .chat-content .chat-tabs .chat-tab:not(.active){background:#2d3d4b!important;border-color:#526373!important;color:#e2e8f0!important}
        .app-shell .chat-content .chat-tab:hover{transform:translateY(-1px);border-color:#e9cf75!important}
        .app-shell .chat-content .chat-tabs .chat-tab:not(.active):hover{background:#384a5b!important;border-color:#718396!important;color:#f1f5f9!important}
        .app-shell .chat-content .chat-tabs .chat-tab.active{background:#dfaa43!important;border-color:#dfaa43!important;color:#19222b!important;box-shadow:0 3px 8px #0003!important}
        .app-shell .chat-content .chat-group-title{padding:13px 17px 9px;color:#718096;font-size:10px;letter-spacing:.08em}
        .app-shell .chat-content .chat-thread-list{padding:2px 7px 10px;scroll-padding-block:8px}
        .app-shell .chat-content .chat-thread-list::-webkit-scrollbar-thumb{border:2px solid #fff;background:#d9e1eb}
        .app-shell .chat-content .thread{position:relative;display:grid;grid-template-columns:38px minmax(0,1fr);align-items:start;gap:10px;width:100%;margin:3px 0;padding:11px 10px;border:1px solid transparent;border-radius:12px;background:#fff;box-shadow:0 1px 2px #0b192c08;transition:background .18s ease,border-color .18s ease,box-shadow .18s ease,transform .18s ease}
        .app-shell .chat-content .thread:hover{transform:translateX(2px);border-color:#f0dfab;background:#fffdf6;box-shadow:0 4px 12px #0b192c0d}
        .app-shell .chat-content .thread.selected{border-color:#e8c85b;background:linear-gradient(110deg,#fff8df 0%,#fffdf5 100%);box-shadow:0 4px 12px #b58b1718,inset 3px 0 #eab308}
        .app-shell .chat-content .thread-avatar{display:grid;width:38px;height:38px;place-items:center;border:1px solid #f1e2b4;border-radius:12px;background:linear-gradient(145deg,#fff7d6,#ffedaa);color:#674d00;font-size:13px;font-weight:800;box-shadow:inset 0 1px #fff}
        .app-shell .chat-content .thread.selected .thread-avatar{border-color:#0b192c;background:#0b192c;color:#facc15}
        .app-shell .chat-content .thread-copy{display:grid;min-width:0;gap:4px}
        .app-shell .chat-content .thread-top{align-items:center;gap:8px;min-width:0;color:#14243a;font-size:12px}
        .app-shell .chat-content .thread-top>span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .app-shell .chat-content .thread-top small{flex:none;color:#8492a6;font-size:10px}
        .app-shell .chat-content .thread-preview{margin:0;color:#66758a;font-size:11px;line-height:1.4}
        .app-shell .chat-content .thread-footer{display:flex;align-items:center;gap:6px;min-height:20px;margin-top:2px}
        .app-shell .chat-content .thread-status{margin:0;padding:4px 8px;border:1px solid #c8f2df;background:#e2fbef;color:#08734e;font-size:10px;font-weight:700;line-height:1}
        .app-shell .chat-content .thread-unread-count{min-width:20px;height:20px;margin:0;padding:0 6px;border:2px solid #fff;border-radius:999px;background:#0b9a67;box-shadow:0 1px 4px #0b192c20;font-size:10px}
        .app-shell .chat-content .thread:focus-visible,.app-shell .chat-content .chat-tab:focus-visible{z-index:1;outline:3px solid #ca8a04;outline-offset:2px}
        .app-shell .chat-content .conversation-head{min-height:62px;padding:11px 18px}
        .app-shell .chat-content .conversation-mode-bar{min-height:40px;padding:6px 18px;flex-wrap:wrap}
        .app-shell .chat-content .conversation-mode{transition:background .15s ease,border-color .15s ease,color .15s ease}
        .app-shell .chat-content .messages{padding:20px 24px;gap:12px}
        .app-shell .chat-content .message{border-radius:15px}
        .app-shell .chat-content .message.out{border-bottom-right-radius:5px}
        .app-shell .chat-content .message.in{border-bottom-left-radius:5px}
        .app-shell .chat-content .chat-composer{min-height:68px;padding:10px 12px}
        .app-shell .chat-content .chat-composer .input{min-height:42px;border-radius:10px}
        .app-shell .chat-content .chat-composer .input:focus{border-color:#ca8a04;box-shadow:0 0 0 3px #facc1533}
        .app-shell .chat-content .chat-attach,.app-shell .chat-content .chat-send{transition:transform .15s ease,background .15s ease,border-color .15s ease}
        .app-shell .chat-content .chat-attach:hover,.app-shell .chat-content .chat-send:hover{transform:translateY(-1px)}
        .app-shell .chat-content :is(button,a,input):focus-visible{outline:2px solid #ca8a04;outline-offset:2px}
        .app-shell .chat-content:has(.contact-profile) .conversation{border-radius:0}
        .app-shell .chat-content .contact-profile{width:clamp(270px,25vw,320px)}
        @media(max-width:900px){.app-shell .chat-content .chat-list{width:clamp(260px,32vw,300px)}.app-shell .chat-content .contact-profile{width:280px}}
        @media(max-width:420px){.app-shell .chat-content .thread{grid-template-columns:34px minmax(0,1fr);gap:8px;padding:10px 8px}.app-shell .chat-content .thread-avatar{width:34px;height:34px;border-radius:10px}.app-shell .chat-content .chat-list-head{padding-inline:13px}}
        @media(max-width:650px){.app-shell .chat-content{padding:10px;height:calc(100dvh - 58px)}.app-shell .chat-content .conversation{border-radius:14px}.app-shell .chat-content .contact-profile{width:min(330px,calc(100% - 20px))}.app-shell .chat-content .messages{padding:14px}.app-shell .chat-content .message{max-width:90%}}
        @media(prefers-reduced-motion:reduce){.app-shell .chat-content *{scroll-behavior:auto!important;transition:none!important}}
        @media(max-width:900px){.contact-profile{width:280px}}
        @media(max-width:650px){.chat-content{padding:10px;height:calc(100vh - 60px);position:relative}.chat-list{display:none}.conversation{width:100%;border-radius:14px}.contact-profile{position:absolute;z-index:5;inset:10px 10px 10px auto;width:min(330px,calc(100% - 20px));border-left:1px solid #d5deea;border-radius:14px;box-shadow:0 12px 35px #0f172a40}.messages{padding:14px}.message{max-width:90%}}
        body.night-mode .chat-list,body.night-mode .conversation{background:#0d172b;border-color:#263858;color:#e2e8f0}
        body.night-mode .chat-list-head,body.night-mode .conversation-head,body.night-mode .contact-profile-head,body.night-mode .contact-profile-identity{background:#111c31;border-color:#263858}
        body.night-mode .conversation-head:hover{background:#172640}
        body.night-mode .conversation-mode-bar{background:#111c31;border-color:#263858;color:#cbd5e1}body.night-mode .conversation-mode{background:#172640;border-color:#405472;color:#dbe5f0}body.night-mode .conversation-mode:hover,body.night-mode .conversation-mode.active{background:#3b2d12;border-color:#f59e0b;color:#fbbf24}
        body.night-mode .chat-list-head h2,body.night-mode .conversation-head strong,body.night-mode .contact-profile-title,body.night-mode .contact-profile-identity strong{color:#f8fafc}
        body.night-mode .chat-list-head p,body.night-mode .conversation-head small,body.night-mode .contact-profile-identity span,body.night-mode .profile-open-icon{color:#cbd5e1}
        body.night-mode .chat-tab{background:#172640;border-color:#405472;color:#dbe5f0}
        body.night-mode .chat-tab.active{background:#2875ed;color:#fff}
        body.night-mode .chat-group-title{color:#cbd5e1}
        body.night-mode .thread{border-bottom-color:#263858;color:#e2e8f0}
        body.night-mode .thread:hover,body.night-mode .thread.selected{background:#24344f}
        body.night-mode .thread-top small,body.night-mode .thread-preview{color:#aabbd1}
        body.night-mode .conversation,body.night-mode .contact-profile{background:#0b1426;border-color:#263858}
        body.night-mode .contact-profile-section{border-color:#263858}
        body.night-mode .contact-profile-section h3,body.night-mode .contact-profile-detail span{color:#aabbd1}
        body.night-mode .contact-profile-detail{color:#e2e8f0}
        body.night-mode .contact-profile-action{background:#172640;border-color:#405472;color:#e2e8f0}
        body.night-mode .contact-profile-action:hover{background:#24344f}
        body.night-mode .contact-profile-action.danger{color:#fca5a5}
        body.night-mode .contact-media-icon{background:#1e3a8a;color:#bfdbfe}
        body.night-mode .contact-media-item{color:#e2e8f0}
        body.night-mode .contact-media-item small,body.night-mode .contact-profile-empty{color:#aabbd1}
        body.night-mode .contact-link{color:#93c5fd}
        body.night-mode .contact-profile-identity{border-bottom-color:#0b1426}
        body.night-mode .message.in{background:#172640;border-color:#334766;color:#e2e8f0}
        body.night-mode .empty-chat,body.night-mode .personal-empty{color:#cbd5e1}
        body.night-mode .chat-composer{background:#111c31;border-color:#263858}
        body.night-mode .chat-composer .input{background:#0d172b;color:#f8fafc;border-color:#405472}
        body.night-mode .chat-error{background:#111c31}
        .app-shell .chat-content .contact-profile{background:#0b192c!important;color:#e2e8f0!important;border-color:#263858!important;border-left:3px solid #eab308!important}
        .app-shell .chat-content .contact-profile-head,.app-shell .chat-content .contact-profile-identity{background:linear-gradient(135deg,#0b192c,#102039)!important;border-color:#263858!important}
        .app-shell .chat-content .contact-profile-head button{color:#aabbd1!important}
        .app-shell .chat-content .contact-profile-head button:hover{color:#facc15!important}
        .app-shell .chat-content .contact-profile-title,.app-shell .chat-content .contact-profile-identity strong{color:#f8fafc!important}
        .app-shell .chat-content .contact-profile-identity{border-bottom:1px solid #263858!important}
        .app-shell .chat-content .contact-profile-identity .contact-avatar{background:#0b192c!important;color:#facc15!important;border:2px solid #facc15;box-shadow:0 0 0 5px #1b3150}
        .app-shell .chat-content .contact-profile-identity>span:last-child{color:#22d3ee!important;font-weight:700}
        .app-shell .chat-content .contact-profile-section{border-color:#263858!important}
        .app-shell .chat-content .contact-profile-section h3{color:#facc15!important;font-size:11px;font-weight:800;letter-spacing:.45px;text-transform:uppercase}
        .app-shell .chat-content .contact-profile-detail{color:#e2e8f0!important}
        .app-shell .chat-content .contact-profile-detail span,.app-shell .chat-content .contact-media-item small,.app-shell .chat-content .contact-profile-empty{color:#94a3b8!important}
        .app-shell .chat-content .contact-profile-detail strong{color:#22d3ee!important}
        .app-shell .chat-content .contact-profile-account .contact-profile-detail{padding:10px 12px;border:1px solid #263858;border-radius:10px;background:#102039}
        .app-shell .chat-content .contact-profile-activity .contact-profile-detail{padding:8px 10px;margin-bottom:6px;border:1px solid #263858;border-radius:10px;background:#102039}
        .app-shell .chat-content .contact-profile-action{background:#14233b!important;border-color:#334766!important;color:#e2e8f0!important}
        .app-shell .chat-content .contact-profile-action:hover{background:#1b3150!important}
        .app-shell .chat-content .contact-profile-action.danger{color:#fca5a5!important}
        .app-shell .chat-content .contact-media-item{padding:9px;border:1px solid #263858;border-radius:10px;background:#102039;color:#e2e8f0!important}
        .app-shell .chat-content .contact-media-icon{background:#075985!important;color:#67e8f9!important}
        .app-shell .chat-content .contact-media-thumbnail{background:#14233b}
        .app-shell .chat-content .contact-link{color:#67e8f9!important}
        @media(max-width:650px){.app-shell .chat-content .contact-profile{border-left:3px solid #eab308!important}}
    </style>

    <aside class="chat-list">
        <div class="chat-list-head">
            <h2>Hilos de conversación</h2>
            <p>{{ $campaignMessageCount }} mensajes de campaña · {{ $personalInboundMessageCount }} mensajes entrantes</p>
            <label class="chat-phone-search">
                <span aria-hidden="true">⌕</span>
                <input type="search" inputmode="tel" wire:model.live.debounce.300ms="phoneSearch" placeholder="Buscar por número de teléfono" aria-label="Buscar por número de teléfono">
            </label>
            <div class="chat-tabs">
                <button class="chat-tab {{ $tab === 'campaigns' ? 'active' : '' }}" wire:click="selectCampaign">Campañas</button>
                <button class="chat-tab {{ $tab === 'personal' ? 'active' : '' }}" wire:click="selectPersonalTab">Chat global</button>
            </div>
        </div>
        @if($tab === 'campaigns')
            <div class="chat-group-title">Mensajes de campañas</div>
            <div class="chat-thread-list">
                @forelse($campaignMessages as $campaignMessage)
                    <button class="thread {{ $selectedCampaignMessage?->id === $campaignMessage->id ? 'selected' : '' }}" wire:click="selectCampaignMessage({{ $campaignMessage->id }})" aria-label="Abrir conversación del paquete {{ $campaignMessage->package?->tracking_number ?? 'sin guía' }}">
                        <span class="thread-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($campaignMessage->package?->recipient ?: $campaignMessage->package?->tracking_number ?: 'P', 0, 1)) }}</span>
                        <span class="thread-copy">
                            <span class="thread-top">
                                <span>{{ $campaignMessage->package?->tracking_number ?? 'Paquete' }}</span>
                                <small>{{ optional($campaignMessage->sent_at ?? $campaignMessage->created_at)->timezone('America/La_Paz')->diffForHumans() }}</small>
                            </span>
                            <span class="thread-preview">{{ $campaignMessage->campaign?->name }} · {{ $campaignMessage->phone ?: 'Sin teléfono' }}</span>
                            <span class="thread-footer"><span class="thread-status">{{ $this->messageStatusLabel($campaignMessage->status) }}</span></span>
                        </span>
                    </button>
                @empty
                    <div class="personal-empty">{{ $phoneSearchDigits !== '' ? 'No se encontraron conversaciones con ese número.' : 'Todavía no hay campañas.' }}</div>
                @endforelse
            </div>
        @else
            <div class="chat-group-title">Chat global</div>
            <div class="chat-thread-list">
                @foreach($filteredPersonalMessages->groupBy('phone') as $phone => $phoneMessages)
                    <button class="thread {{ $selectedPhone === $phone ? 'selected' : '' }}" wire:click="selectPersonal('{{ $phone }}')" aria-label="Abrir chat con {{ $phoneMessages->first()->contact_name ?: $phone }}">
                        <span class="thread-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($phoneMessages->first()->contact_name ?: $phone, 0, 1)) }}</span>
                        <span class="thread-copy">
                            <span class="thread-top">
                                <span>{{ $phoneMessages->first()->contact_name ?: $phone }}</span>
                                <small>{{ optional($phoneMessages->last()->received_at)->timezone('America/La_Paz')->diffForHumans() }}</small>
                            </span>
                            <span class="thread-preview">{{ $phoneMessages->last()->message ?: '['.$this->messageTypeLabel($phoneMessages->last()->message_type).']' }}</span>
                            <span class="thread-footer">
                                <span class="thread-status">{{ $phoneMessages->count() }} mensaje(s)</span>
                                @if(($unreadCounts[$phone] ?? 0) > 0)
                                    <span class="thread-unread-count" aria-label="{{ $unreadCounts[$phone] }} mensajes sin leer">{{ $unreadCounts[$phone] }}</span>
                                @endif
                            </span>
                        </span>
                    </button>
                @endforeach
                @if($filteredPersonalMessages->isEmpty())
                    <div class="personal-empty">{{ $phoneSearchDigits !== '' ? 'No se encontraron conversaciones con ese número.' : 'No hay chats personales registrados.' }}</div>
                @endif
            </div>
        @endif
    </aside>

    <section class="conversation">
        @if (session()->has('chatCleared'))
            <div class="chat-clear-feedback" role="status">{{ session('chatCleared') }}</div>
        @endif
        <button
            type="button"
            class="conversation-head"
            wire:click="toggleContactProfile"
            aria-label="Abrir perfil del contacto"
            aria-expanded="{{ $showContactProfile ? 'true' : 'false' }}"
            @disabled(! $selectedPhone && ! $selectedCampaignMessage)
        >
            <span class="contact-avatar">{{ $selectedPhone ? mb_strtoupper(mb_substr($selectedContactMessages->first()?->contact_name ?: $selectedCampaignMessage?->package?->recipient ?: $selectedPhone, 0, 1)) : '•' }}</span>
            <span class="conversation-head-copy">
                <strong>{{ $selectedCampaignMessage ? ($selectedCampaignMessage->package?->recipient ?: $selectedCampaignMessage->package?->tracking_number) : ($selectedPhone ? ($selectedContactMessages->first()?->contact_name ?: $selectedPhone) : 'Mensajes') }}</strong>
                <small>{{ $selectedCampaignMessage ? 'Paquete '.$selectedCampaignMessage->package?->tracking_number.' · '.($selectedPhone ?: 'Sin teléfono') : ($selectedPhone ?: 'Selecciona un chat') }}</small>
            </span>
            @if($selectedPhone || $selectedCampaignMessage)
                <span class="profile-open-icon" aria-hidden="true">ⓘ</span>
            @endif
        </button>
        @if($selectedPhone)
            <div class="conversation-mode-bar" role="group" aria-label="Modo de atención del cliente">
                <span>Atención:</span>
                <button type="button" class="conversation-mode {{ $conversationMode === 'global' ? 'active' : '' }}" wire:click="setConversationMode('global')">Global</button>
                <button type="button" class="conversation-mode {{ $conversationMode === 'manual' ? 'active' : '' }}" wire:click="setConversationMode('manual')">Manual</button>
                <button type="button" class="conversation-mode {{ $conversationMode === 'ai' ? 'active' : '' }}" wire:click="setConversationMode('ai')">IA</button>
                <button type="button" class="conversation-mode {{ $conversationMode === 'automatic' ? 'active' : '' }}" wire:click="setConversationMode('automatic')">Automática</button>
            </div>
        @endif
        <div
            wire:key="conversation-messages-{{ $selectedCampaignMessage?->id ?? $selectedPhone ?? 'none' }}"
            class="messages"
            data-selected-campaign-message-id="{{ $selectedCampaignMessage?->id }}"
            x-data="{ pinnedToBottom: true }"
            x-init="$nextTick(() => { const selectedId = $el.dataset.selectedCampaignMessageId; const target = selectedId ? $el.querySelector('[data-campaign-message-id=&quot;' + selectedId + '&quot;]') : null; if (target) { pinnedToBottom = false; target.scrollIntoView({ block: 'center' }); } else { $el.scrollTop = $el.scrollHeight; } }); const observer = new MutationObserver(() => { if (pinnedToBottom) $el.scrollTop = $el.scrollHeight }); observer.observe($el, { childList: true, subtree: true }); $el.__chatObserver = observer"
            @scroll="pinnedToBottom = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 80"
        >
            @if($selectedPhone || $selectedCampaignMessage)
                @forelse($selectedConversationMessages as $conversationMessage)
                    @php
                        $chatMessage = $conversationMessage['model'];
                        $status = strtolower((string) $conversationMessage['status']);
                        $statusLabel = $this->messageStatusLabel($status);
                    @endphp
                    <div
                        class="message {{ $conversationMessage['direction'] === 'outbound' ? 'out' : 'in' }}{{ $conversationMessage['is_campaign'] && $selectedCampaignMessage?->id === $chatMessage->id ? ' campaign-message-focused' : '' }}"
                        @if($conversationMessage['is_campaign'] && $selectedCampaignMessage?->id === $chatMessage->id) data-campaign-message-id="{{ $chatMessage->id }}" @endif
                    >
                        @if($conversationMessage['is_campaign'])
                            <div class="message-source">{{ $conversationMessage['campaign']?->name ?? 'Campaña' }} · Paquete {{ $conversationMessage['package']?->tracking_number ?? 'sin guía' }}</div>
                        @endif
                        @if(in_array($chatMessage->message_type, ['image', 'video', 'audio', 'document', 'sticker'], true) && data_get($chatMessage->raw_payload, $chatMessage->message_type.'.local_path'))
                            <button type="button" class="image-open-button" data-image-url="{{ route('whatsapp.media', $chatMessage) }}" @click="previewImage = $el.dataset.imageUrl" aria-label="Ampliar imagen">
                                @if($chatMessage->message_type === 'video')<video class="message-image" controls preload="metadata"><source src="{{ route('whatsapp.media', $chatMessage) }}"></video>@elseif($chatMessage->message_type === 'audio')<audio controls><source src="{{ route('whatsapp.media', $chatMessage) }}"></audio>@elseif($chatMessage->message_type === 'document')<a href="{{ route('whatsapp.media', $chatMessage) }}" target="_blank" rel="noopener">📎 Descargar documento</a>@else<img class="message-image {{ $chatMessage->message_type === 'sticker' ? 'message-sticker' : '' }}" src="{{ route('whatsapp.media', $chatMessage) }}" alt="{{ $chatMessage->message_type === 'sticker' ? 'Sticker recibido' : 'Imagen recibida' }}">@endif
                            </button>
                        @endif
                        @if($conversationMessage['message'])
                            <div class="message-body">{{ $conversationMessage['message'] }}</div>
                        @elseif(! $conversationMessage['is_campaign'] && (! in_array($chatMessage->message_type, ['image', 'video', 'audio', 'document', 'sticker'], true) || ! data_get($chatMessage->raw_payload, $chatMessage->message_type.'.local_path')))
                            <div class="message-body">[{{ $this->messageTypeLabel($conversationMessage['message_type']) }}]</div>
                        @endif
                        <div class="message-meta">
                            <span>{{ optional($conversationMessage['timestamp'])->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
                            @if($conversationMessage['direction'] === 'outbound')
                                <span>{{ $statusLabel }}</span>
                                @if(in_array($status, ['sent', 'delivered', 'read'], true))
                                    <span class="message-status-icon {{ $status === 'read' ? 'read' : '' }}" aria-label="{{ $statusLabel }}" title="{{ $statusLabel }}">{{ $status === 'sent' ? '✓' : '✓✓' }}</span>
                                @endif
                            @else
                                <span>{{ $statusLabel }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-chat">No hay mensajes en esta conversación.</div>
                @endforelse
            @else
                <div class="empty-chat">Selecciona un chat para ver sus mensajes.</div>
            @endif
        </div>
        @if($selectedPhone)
            <form wire:submit="sendMessage" class="chat-composer">
                <input wire:model="message" class="input" placeholder="Escribe un mensaje para WhatsApp..." autocomplete="off">
                <label class="chat-attach" title="Adjuntar foto, video, audio o documento">＋<input wire:model="attachment" type="file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="this.closest('form').querySelector('.chat-file-name').textContent=this.files[0]?.name||''"></label><span class="chat-file-name"></span>
                <button class="primary-btn chat-send" type="submit" wire:loading.attr="disabled" title="Enviar por WhatsApp" aria-label="Enviar por WhatsApp">➤</button>
            </form>
            @error('message')<small class="chat-error">{{ $message }}</small>@enderror
            @error('attachment')<small class="chat-error">{{ $message }}</small>@enderror
        @endif
    </section>
    @if($showContactProfile && $selectedPhone)
        @php
            $contactName = $personalMessages->firstWhere('phone', $selectedPhone)?->contact_name ?: $selectedPhone;
            $incomingCount = $selectedContactMessages->where('direction', 'inbound')->count();
            $outgoingCount = $selectedContactMessages->where('direction', 'outbound')->count();
            $lastContactMessage = $selectedContactMessages->last();
        @endphp
        <aside class="contact-profile" aria-label="Perfil del contacto">
            <div class="contact-profile-head">
                <button type="button" wire:click="closeContactProfile" aria-label="Cerrar perfil">×</button>
                <span class="contact-profile-title">Ficha del cliente</span>
            </div>
            <div class="contact-profile-identity">
                <span class="contact-avatar">{{ mb_strtoupper(mb_substr($contactName, 0, 1)) }}</span>
                <strong>{{ $contactName }}</strong>
                <span>{{ $selectedPhone }}</span>
            </div>
            <div class="contact-profile-section contact-profile-account">
                <h3>Datos del cliente</h3>
                <div class="contact-profile-detail"><span>Teléfono</span><strong>{{ $selectedPhone }}</strong></div>
            </div>
            <div class="contact-profile-section contact-profile-activity">
                <h3>Actividad de la conversación</h3>
                <div class="contact-profile-detail"><span>Mensajes</span><strong>{{ $selectedContactMessages->count() }}</strong></div>
                <div class="contact-profile-detail"><span>Recibidos</span><strong>{{ $incomingCount }}</strong></div>
                <div class="contact-profile-detail"><span>Enviados</span><strong>{{ $outgoingCount }}</strong></div>
                @if($lastContactMessage?->received_at)
                    <div class="contact-profile-detail"><span>Último mensaje</span><strong>{{ $lastContactMessage->received_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</strong></div>
                @endif
            </div>
            <div class="contact-profile-section">
                <h3>Multimedia, enlaces y documentos</h3>
                <div class="contact-media-list">
                    @forelse($selectedContactMedia as $media)
                        <div class="contact-media-item" data-media-type="{{ strtolower($media['type']) }}">
                            @if($media['image_url'])
                                <button type="button" class="image-open-button" data-image-url="{{ $media['image_url'] }}" @click="previewImage = $el.dataset.imageUrl" aria-label="Ampliar imagen">
                                    <img class="contact-media-thumbnail" src="{{ $media['image_url'] }}" alt="{{ $media['type'] === 'Sticker' ? 'Sticker compartido' : 'Imagen compartida' }}">
                                </button>
                            @else
                                <span class="contact-media-icon" aria-hidden="true">▧</span>
                            @endif
                            <span>
                                <strong>{{ $media['type'] }}</strong>
                                <small>{{ $media['description'] }} · {{ optional($media['received_at'])->timezone('America/La_Paz')->format('d/m/Y H:i') }}</small>
                            </span>
                        </div>
                    @empty
                        <span class="contact-profile-empty">No hay archivos multimedia en este chat.</span>
                    @endforelse
                </div>
                @foreach($selectedContactLinks as $link)
                    <a class="contact-link" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ $link['url'] }}</a>
                @endforeach
            </div>
            <div class="contact-profile-section">
                <h3>Administrar conversación</h3>
                <button
                    type="button"
                    class="contact-profile-action danger"
                    wire:click="clearChat"
                    wire:confirm="¿Limpiar el historial de este chat? Esta acción eliminará sus mensajes permanentemente."
                >Limpiar chat</button>
            </div>
        </aside>
    @endif
    <div
        class="image-preview-modal"
        x-show="previewImage"
        x-cloak
        x-transition.opacity
        @click.self="previewImage = null"
        role="dialog"
        aria-modal="true"
        aria-label="Vista ampliada de imagen"
    >
        <div class="image-preview-content">
            <span class="image-preview-hint">Clic fuera de la imagen o Esc para cerrar</span>
            <button type="button" class="image-preview-close" @click="previewImage = null" aria-label="Cerrar imagen">×</button>
            <img :src="previewImage" alt="Imagen ampliada del chat">
        </div>
    </div>
</div>
