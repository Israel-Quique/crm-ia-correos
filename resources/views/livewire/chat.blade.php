<div wire:poll.5s="refreshChat" class="chat-content" x-data="{ previewImage: null }" @keydown.escape.window="previewImage = null">
    <style>
        .chat-content{padding:20px;height:calc(100vh - 76px);display:flex;min-height:0}
        .chat-list,.conversation{background:#f7f8fa;border:1px solid #d5deea;overflow:hidden}
        .chat-list{width:360px;flex-shrink:0;border-radius:14px 0 0 14px;overflow:auto}
        .chat-list-head{padding:16px;border-bottom:1px solid #d5deea}
        .chat-list-head h2{font-size:16px;margin:0}
        .chat-list-head p{color:#64748b;font-size:12px}
        .chat-tabs{display:flex;gap:6px;margin-top:12px}
        .chat-tab{border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:7px 9px;font-size:11px;cursor:pointer}
        .chat-tab.active{background:#2875ed;color:#fff}
        .chat-group-title{padding:10px 14px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase}
        .thread{display:block;width:100%;padding:13px 14px;border:0;border-bottom:1px solid #e2e8f0;text-align:left;background:transparent;cursor:pointer}
        .thread:hover,.thread.selected{background:#fff8e8}
        .thread-top{display:flex;justify-content:space-between;font-size:12px;font-weight:700}
        .thread-top small{color:#94a3b8;font-weight:400}
        .thread-preview{color:#64748b;font-size:11px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .thread-status{display:inline-block;font-size:10px;background:#d1fae5;color:#047857;padding:3px 8px;border-radius:20px;margin-top:7px}
        .thread-unread-count{display:inline-grid;place-items:center;min-width:20px;height:20px;margin-left:6px;padding:0 6px;border-radius:999px;background:#16a34a;color:#fff;font-size:11px;font-weight:700}
        .conversation{flex:1;min-width:0;display:flex;flex-direction:column;background:#eef2f7}
        .conversation-head{display:flex;align-items:center;gap:12px;width:100%;border:0;background:#fff;padding:12px 20px;border-bottom:1px solid #d5deea;text-align:left;font:inherit;cursor:pointer}
        .conversation-head:hover{background:#f8fafc}
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
        .message-body{white-space:pre-wrap}
        .message-source{font-size:10px;font-weight:700;opacity:.8;margin-bottom:5px}
        .message-meta{display:flex;justify-content:flex-end;align-items:center;gap:6px;font-size:10px;line-height:1.2;opacity:.82;margin-top:7px}
        .message-status-icon{font-size:13px;letter-spacing:-3px;padding-right:3px;font-weight:700}
        .message-status-icon.read{color:#a7f3d0;opacity:1}
        .empty-chat,.personal-empty{padding:30px;color:#64748b;text-align:center}
        .chat-composer{display:flex;align-items:center;gap:8px;background:#fff;padding:12px;border-top:1px solid #d5deea}.chat-composer .input{flex:1;min-width:0}.chat-attach{position:relative;display:grid;place-items:center;width:42px;height:42px;flex:0 0 42px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#334155;cursor:pointer;font-size:21px}.chat-attach:hover{background:#e2e8f0}.chat-attach input{position:absolute;width:1px;height:1px;opacity:0}.chat-file-name{max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;font-size:11px}.chat-send-file{background:#475569!important}
        .chat-error{color:#dc2626;padding:0 12px 10px;background:#fff}
        @media(max-width:900px){.contact-profile{width:280px}}
        @media(max-width:650px){.chat-content{padding:10px;height:calc(100vh - 60px);position:relative}.chat-list{display:none}.conversation{width:100%;border-radius:14px}.contact-profile{position:absolute;z-index:5;inset:10px 10px 10px auto;width:min(330px,calc(100% - 20px));border-left:1px solid #d5deea;border-radius:14px;box-shadow:0 12px 35px #0f172a40}.messages{padding:14px}.message{max-width:90%}}
        body.night-mode .chat-list,body.night-mode .conversation{background:#0d172b;border-color:#263858;color:#e2e8f0}
        body.night-mode .chat-list-head,body.night-mode .conversation-head,body.night-mode .contact-profile-head,body.night-mode .contact-profile-identity{background:#111c31;border-color:#263858}
        body.night-mode .conversation-head:hover{background:#172640}
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
    </style>

    <aside class="chat-list">
        <div class="chat-list-head">
            <h2>Hilos de conversación</h2>
            <p>{{ $campaignMessages->count() }} mensajes de campaña · {{ $personalMessages->where('direction', 'inbound')->count() }} mensajes entrantes</p>
            <div class="chat-tabs">
                <button class="chat-tab {{ $tab === 'campaigns' ? 'active' : '' }}" wire:click="selectCampaign">Campañas</button>
                <button class="chat-tab {{ $tab === 'personal' ? 'active' : '' }}" wire:click="selectPersonalTab">Chats personales</button>
            </div>
        </div>
        @if($tab === 'campaigns')
            <div class="chat-group-title">Mensajes de campañas</div>
            @forelse($campaignMessages as $campaignMessage)
                <button class="thread {{ $selectedCampaignMessage?->id === $campaignMessage->id ? 'selected' : '' }}" wire:click="selectCampaignMessage({{ $campaignMessage->id }})">
                    <div class="thread-top">
                        <span>{{ $campaignMessage->package?->tracking_number ?? 'Paquete' }}</span>
                        <small>{{ optional($campaignMessage->sent_at ?? $campaignMessage->created_at)->timezone('America/La_Paz')->diffForHumans() }}</small>
                    </div>
                    <div class="thread-preview">{{ $campaignMessage->campaign?->name }} · {{ $campaignMessage->phone ?: 'Sin teléfono' }}</div>
                    <span class="thread-status">{{ match ($campaignMessage->status) { 'sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Visto', 'failed' => 'Fallido', 'cancelled' => 'Cancelado', 'pending' => 'Pendiente', 'sending' => 'Enviando', default => ucfirst($campaignMessage->status) } }}</span>
                </button>
            @empty
                <div class="personal-empty">Todavía no hay campañas.</div>
            @endforelse
        @else
            <div class="chat-group-title">Chats personales</div>
            @foreach($personalMessages->groupBy('phone') as $phone => $phoneMessages)
                <button class="thread {{ $selectedPhone === $phone ? 'selected' : '' }}" wire:click="selectPersonal('{{ $phone }}')">
                    <div class="thread-top">
                        <span>{{ $phoneMessages->first()->contact_name ?: $phone }}</span>
                        <small>{{ optional($phoneMessages->last()->received_at)->timezone('America/La_Paz')->diffForHumans() }}</small>
                    </div>
                    <div class="thread-preview">{{ $phoneMessages->last()->message ?: '['.$phoneMessages->last()->message_type.']' }}</div>
                    <span class="thread-status">{{ $phoneMessages->count() }} mensaje(s)</span>
                        @if(($unreadCounts[$phone] ?? 0) > 0)
                            <span class="thread-unread-count" aria-label="{{ $unreadCounts[$phone] }} mensajes sin leer">{{ $unreadCounts[$phone] }}</span>
                        @endif
                </button>
            @endforeach
            @if($personalMessages->isEmpty())
                <div class="personal-empty">No hay chats personales registrados.</div>
            @endif
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
        <div
            wire:key="conversation-messages-{{ $selectedCampaignMessage?->id ?? $selectedPhone ?? 'none' }}"
            class="messages"
            x-data="{ pinnedToBottom: true }"
            x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight }); const observer = new MutationObserver(() => { if (pinnedToBottom) $el.scrollTop = $el.scrollHeight }); observer.observe($el, { childList: true, subtree: true }); $el.__chatObserver = observer"
            @scroll="pinnedToBottom = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 80"
        >
            @if($selectedPhone || $selectedCampaignMessage)
                @forelse($selectedConversationMessages as $conversationMessage)
                    @php
                        $chatMessage = $conversationMessage['model'];
                        $status = strtolower((string) $conversationMessage['status']);
                        $statusLabel = match ($status) {
                            'sent' => 'Enviado',
                            'delivered' => 'Entregado',
                            'read' => 'Visto',
                            'failed' => 'No enviado',
                            'received' => 'Recibido',
                            'pending' => 'Pendiente',
                            'sending' => 'Enviando',
                            'cancelled' => 'Cancelado',
                            default => ucfirst($status),
                        };
                    @endphp
                    <div class="message {{ $conversationMessage['direction'] === 'outbound' ? 'out' : 'in' }}">
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
                            <div class="message-body">[{{ ucfirst($conversationMessage['message_type']) }}]</div>
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
                <button class="primary-btn" type="submit" wire:loading.attr="disabled">Enviar por WhatsApp</button>
                <button class="primary-btn chat-send-file" type="button" wire:click="sendAttachment" wire:loading.attr="disabled">Enviar archivo</button>
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
                <span class="contact-profile-title">Info. del contacto</span>
            </div>
            <div class="contact-profile-identity">
                <span class="contact-avatar">{{ mb_strtoupper(mb_substr($contactName, 0, 1)) }}</span>
                <strong>{{ $contactName }}</strong>
                <span>{{ $selectedPhone }}</span>
            </div>
            <div class="contact-profile-section">
                <h3>Información</h3>
                <div class="contact-profile-detail"><span>Teléfono</span><strong>{{ $selectedPhone }}</strong></div>
            </div>
            <div class="contact-profile-section">
                <h3>Actividad del chat</h3>
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
                        <div class="contact-media-item">
                            @if($media['image_url'])
                                <button type="button" class="image-open-button" data-image-url="{{ $media['image_url'] }}" @click="previewImage = $el.dataset.imageUrl" aria-label="Ampliar imagen">
                                    <img class="contact-media-thumbnail" src="{{ $media['image_url'] }}" alt="Imagen compartida">
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
