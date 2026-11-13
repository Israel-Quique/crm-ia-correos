<?php

namespace App\Livewire;

use App\Models\PostalCampaignMessage;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppConversationSetting;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppTestNumberRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Chat extends Component
{
    use WithFileUploads;
    public string $tab = 'personal';
    public string $phoneSearch = '';
    public ?string $selectedPhone = null;
    public ?int $selectedCampaignMessageId = null;
    public string $message = '';
    public bool $showContactProfile = false;
    public ?TemporaryUploadedFile $attachment = null;
    public string $conversationMode = 'automatic';

    public function mount(): void
    {
        $campaignMessageId = request()->query('campaign_message');
        if ($campaignMessageId !== null) {
            abort_unless(filter_var($campaignMessageId, FILTER_VALIDATE_INT) !== false, 404);
            $this->selectCampaignMessage((int) $campaignMessageId);
            return;
        }

        $this->selectedPhone = WhatsAppMessage::query()
            ->latest('received_at')
            ->value('phone');
        $this->loadConversationMode();
        $this->markSelectedMessagesAsRead();
    }

    public function selectPersonal(string $phone): void
    {
        $this->selectedPhone = $phone;
        $this->selectedCampaignMessageId = null;
        $this->tab = 'personal';
        $this->showContactProfile = false;
        $this->loadConversationMode();
        $this->markSelectedMessagesAsRead();
    }

    public function setConversationMode(string $mode): void
    {
        abort_unless(in_array($mode, ['global', 'manual', 'ai', 'automatic'], true), 422);
        if (! $this->selectedPhone) return;

        WhatsAppConversationSetting::updateOrCreate(
            ['phone' => $this->selectedPhone],
            ['mode' => $mode, 'updated_by' => auth()->id()],
        );
        $this->conversationMode = $mode;
    }

    private function loadConversationMode(): void
    {
        $this->conversationMode = $this->selectedPhone
            ? (WhatsAppConversationSetting::where('phone', $this->selectedPhone)->value('mode') ?: 'automatic')
            : 'global';
    }

    public function refreshChat(): void
    {
        $this->markSelectedMessagesAsRead();
    }

    private function markSelectedMessagesAsRead(): void
    {
        if (! $this->selectedPhone) {
            return;
        }

        WhatsAppMessage::query()
            ->where('phone', $this->selectedPhone)
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function toggleContactProfile(): void
    {
        if ($this->selectedPhone) {
            $this->showContactProfile = ! $this->showContactProfile;
        }
    }

    public function closeContactProfile(): void
    {
        $this->showContactProfile = false;
    }

    public function clearChat(): void
    {
        if (! $this->selectedPhone) {
            return;
        }

        $messages = WhatsAppMessage::query()
            ->where('phone', $this->selectedPhone)
            ->get();
        foreach ($messages as $chatMessage) {
            $mediaPath = (string) data_get($chatMessage->raw_payload, 'image.local_path', '');
            if (str_starts_with($mediaPath, 'whatsapp-media/')) {
                Storage::disk('local')->delete($mediaPath);
            }
        }
        WhatsAppMessage::query()->where('phone', $this->selectedPhone)->delete();

        $this->selectedPhone = null;
        $this->selectedCampaignMessageId = null;
        $this->showContactProfile = false;
        session()->flash('chatCleared', 'Se limpió el historial de esta conversación.');
    }

    public function selectCampaign(): void
    {
        $this->tab = 'campaigns';
        $this->selectedPhone = null;
        $this->selectedCampaignMessageId = null;
        $this->showContactProfile = false;
    }

    public function selectPersonalTab(): void
    {
        $this->tab = 'personal';
        $this->selectedCampaignMessageId = null;
        $this->showContactProfile = false;
    }

    public function selectCampaignMessage(int $id): void
    {
        $campaignMessage = PostalCampaignMessage::findOrFail($id);
        $this->selectedCampaignMessageId = $campaignMessage->id;
        $this->selectedPhone = $campaignMessage->phone;
        $this->tab = 'campaigns';
        $this->showContactProfile = false;
        $this->markSelectedMessagesAsRead();
    }

    public function sendMessage(WhatsAppCloudApiService $whatsapp): void
    {
        if ($this->attachment) {
            $this->sendAttachment($whatsapp);
            return;
        }

        $this->validate(['selectedPhone' => ['required', 'string'], 'message' => ['required', 'string', 'max:4096']]);
        $text = trim($this->message);
        $originalPhone = $this->selectedPhone;
        $contactName = WhatsAppMessage::where('phone', $originalPhone)->value('contact_name');
        $testMode = filter_var(
            DB::table('app_settings')->where('key', 'test_mode')->value('value') ?? '1',
            FILTER_VALIDATE_BOOLEAN,
        );

        try {
            $delivery = $whatsapp->sendChatText(
                $originalPhone,
                $text,
                $testMode,
                app(WhatsAppTestNumberRegistry::class),
            );
            WhatsAppMessage::create([
                'provider_message_id' => data_get($delivery['response'], 'messages.0.id'),
                'phone' => $delivery['recipient'],
                'contact_name' => $contactName,
                'direction' => 'outbound', 'message_type' => 'text', 'message' => $text,
                'status' => 'sent', 'source' => 'manual', 'sender_user_id' => auth()->id(), 'raw_payload' => $delivery['response'], 'received_at' => now(),
            ]);
            \App\Models\AuditLog::create(['user_id'=>auth()->id(),'action'=>'Mensaje enviado manualmente','category'=>'mensajes','description'=>'Mensaje manual enviado a '.$delivery['recipient'],'ip_address'=>request()->ip()]);
            $this->message = '';
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('message', $exception->getMessage());
        }
    }

    public function sendAttachment(WhatsAppCloudApiService $whatsapp): void
    {
        $this->validate(['selectedPhone' => ['required', 'string'], 'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,3gp,mp3,ogg,aac,m4a,wav,pdf,doc,docx,xls,xlsx,txt', 'max:102400']]);
        $mime = $this->attachment->getMimeType();
        $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));
        $result = $whatsapp->sendMedia($this->selectedPhone, $this->attachment->getRealPath(), $mime);
        WhatsAppMessage::create(['provider_message_id' => data_get($result, 'messages.0.id'), 'phone' => $this->selectedPhone, 'contact_name' => WhatsAppMessage::where('phone', $this->selectedPhone)->value('contact_name'), 'direction' => 'outbound', 'message_type' => $type, 'message' => '', 'status' => 'sent', 'source' => 'manual', 'sender_user_id' => auth()->id(), 'raw_payload' => $result, 'received_at' => now()]);
        \App\Models\AuditLog::create(['user_id'=>auth()->id(),'action'=>'Mensaje enviado manualmente','category'=>'mensajes','description'=>'Archivo enviado manualmente a '.$this->selectedPhone,'ip_address'=>request()->ip()]);
        $this->attachment = null;
    }

    public function messageStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'read' => 'Visto',
            'failed' => 'No enviado',
            'received' => 'Recibido',
            'pending' => 'Pendiente',
            'sending' => 'Enviando',
            'cancelled' => 'Cancelado',
            'replied' => 'Respondido',
            default => 'Estado no identificado',
        };
    }

    public function messageTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'text' => 'Texto',
            'image' => 'Imagen',
            'video' => 'Video',
            'audio' => 'Audio',
            'document' => 'Documento',
            'sticker' => 'Sticker',
            'location' => 'Ubicación',
            'contacts' => 'Contacto',
            'interactive' => 'Interacción',
            'button' => 'Botón',
            default => 'Otro tipo',
        };
    }

    public function render()
    {
        $personalMessages = WhatsAppMessage::whereIn('direction', ['inbound', 'outbound'])
            ->orderBy('received_at')
            ->get();
        $phoneSearchDigits = preg_replace('/\D+/', '', $this->phoneSearch) ?? '';
        $matchesPhoneSearch = fn ($message): bool => $phoneSearchDigits === ''
            || str_contains(preg_replace('/\D+/', '', (string) $message->phone) ?? '', $phoneSearchDigits);
        $unreadCounts = $personalMessages
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->groupBy('phone')
            ->map(fn ($messages) => $messages->count());
        $personalInboundMessageCount = $personalMessages->where('direction', 'inbound')->count();
        $filteredPersonalMessages = $personalMessages->filter($matchesPhoneSearch)->values();
        $selectedCampaignMessage = $this->selectedCampaignMessageId
            ? PostalCampaignMessage::with(['campaign', 'package'])->find($this->selectedCampaignMessageId)
            : null;
        $conversationPhone = $selectedCampaignMessage?->phone ?? $this->selectedPhone;
        $normalizedPhone = preg_replace('/\D+/', '', (string) $conversationPhone) ?? '';
        $phoneVariants = array_values(array_unique(array_filter([
            $conversationPhone,
            $normalizedPhone,
            $normalizedPhone !== '' ? '+'.$normalizedPhone : null,
        ])));
        $selectedContactMessages = $phoneVariants
            ? $personalMessages->whereIn('phone', $phoneVariants)->values()
            : collect();
        $campaignThreadMessages = $phoneVariants
            ? PostalCampaignMessage::with(['campaign', 'package'])->whereIn('phone', $phoneVariants)->get()
            : collect();
        if ($selectedCampaignMessage && ! $campaignThreadMessages->contains('id', $selectedCampaignMessage->id)) {
            $campaignThreadMessages->push($selectedCampaignMessage);
        }
        $selectedConversationMessages = $selectedContactMessages
            ->map(fn (WhatsAppMessage $chatMessage): array => [
                'model' => $chatMessage,
                'is_campaign' => false,
                'direction' => $chatMessage->direction,
                'message_type' => $chatMessage->message_type,
                'message' => $chatMessage->message,
                'status' => $chatMessage->status,
                'timestamp' => $chatMessage->received_at,
                'campaign' => null,
                'package' => null,
            ])
            ->concat($campaignThreadMessages->map(fn (PostalCampaignMessage $campaignMessage): array => [
                'model' => $campaignMessage,
                'is_campaign' => true,
                'direction' => 'outbound',
                'message_type' => 'text',
                'message' => $campaignMessage->message,
                'status' => $campaignMessage->status,
                'timestamp' => $campaignMessage->sent_at ?? $campaignMessage->created_at,
                'campaign' => $campaignMessage->campaign,
                'package' => $campaignMessage->package,
            ]))
            ->sortBy(fn (array $item) => $item['timestamp']?->getTimestamp() ?? 0)
            ->values();
        $selectedContactMedia = $selectedContactMessages
            ->filter(fn (WhatsAppMessage $message): bool => $message->message_type !== 'text')
            ->map(function (WhatsAppMessage $message): array {
                $type = $message->message_type;
                $payload = data_get($message->raw_payload, $type, []);

                return [
                    'message_id' => $message->id,
                    'type' => $this->messageTypeLabel($type),
                    'description' => data_get($payload, 'filename')
                        ?: data_get($payload, 'caption')
                        ?: 'Archivo multimedia recibido',
                    'image_url' => in_array($type, ['image', 'sticker'], true) && data_get($payload, 'local_path')
                        ? route('whatsapp.media', $message)
                        : null,
                    'received_at' => $message->received_at,
                ];
            })
            ->values();
        $selectedContactLinks = $selectedContactMessages
            ->flatMap(function (WhatsAppMessage $message): array {
                preg_match_all('~https?://[^\s<>"\']+~i', (string) $message->message, $matches);

                return array_map(
                    fn (string $url): array => ['url' => rtrim($url, '.,);]')],
                    $matches[0],
                );
            })
            ->unique('url')
            ->values();

        $campaignMessages = PostalCampaignMessage::with(['campaign', 'package'])->latest()->get();
        $campaignMessageCount = $campaignMessages->count();
        $campaignMessages = $campaignMessages->filter($matchesPhoneSearch)->values();

        return view('livewire.chat', [
            'campaignMessages' => $campaignMessages,
            'campaignMessageCount' => $campaignMessageCount,
            'personalInboundMessageCount' => $personalInboundMessageCount,
            'phoneSearchDigits' => $phoneSearchDigits,
            'personalMessages' => $personalMessages,
            'filteredPersonalMessages' => $filteredPersonalMessages,
            'unreadCounts' => $unreadCounts,
            'selectedContactMessages' => $selectedContactMessages,
            'selectedCampaignMessage' => $selectedCampaignMessage,
            'selectedConversationMessages' => $selectedConversationMessages,
            'selectedContactMedia' => $selectedContactMedia,
            'selectedContactLinks' => $selectedContactLinks,
        ]);
    }
}
