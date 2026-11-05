<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppAutoReplyService
{
    public function __construct(
        private readonly AutomaticResponseMatcher $responseMatcher,
        private readonly OllamaService $ollama,
        private readonly WhatsAppCloudApiService $whatsapp,
        private readonly WhatsAppTestNumberRegistry $testNumbers,
        private readonly LanguageService $language,
    ) {
    }

    public function replyTo(WhatsAppMessage $incoming): void
    {
        $incomingPhone = $this->normalizePhone((string) $incoming->phone);
        $testMode = $this->settingEnabled('test_mode');
        $automaticResponsesEnabled = $this->settingEnabled('automatic_responses_enabled');
        $aiEnabled = $this->settingEnabled('ai_enabled');
        if ($incoming->message_type !== 'text'
            || trim((string) $incoming->message) === ''
            || (! $automaticResponsesEnabled && ! $aiEnabled)) {
            return;
        }

        $replyRecipient = $incomingPhone;
        if ($replyRecipient === '') {
            Log::error('No se pudo responder automáticamente: el destinatario de WhatsApp no está configurado.');
            return;
        }

        if ($testMode && ! $this->testNumbers->contains($incomingPhone)) {
            return;
        }

        $incomingAt = $incoming->received_at ?? now();
        $replyWindowEnd = $incomingAt->copy()->addMinute();
        $pendingMessages = WhatsAppMessage::query()
            ->where('phone', $incoming->phone)
            ->where('direction', 'inbound')
            ->where('message_type', 'text')
            ->whereNotNull('message')
            ->where('message', '!=', '')
            ->whereNull('auto_reply_status')
            ->where('received_at', '>=', $incomingAt)
            ->where('received_at', '<', $replyWindowEnd)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $claimedIds = $pendingMessages->modelKeys();
        if ($claimedIds === []) {
            return;
        }

        $claimedCount = WhatsAppMessage::query()
            ->whereKey($claimedIds)
            ->whereNull('auto_reply_status')
            ->update(['auto_reply_status' => 'processing', 'updated_at' => now()]);

        if ($claimedCount === 0) {
            return;
        }

        $claimedMessages = WhatsAppMessage::query()
            ->whereKey($claimedIds)
            ->where('auto_reply_status', 'processing')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        if ($claimedMessages->isEmpty()) {
            return;
        }

        $combinedMessage = $claimedMessages->pluck('message')->implode("\n");
        $responseLanguage = $this->language->detect($combinedMessage);
        $claimedIds = $claimedMessages->modelKeys();

        try {
            $reply = null;
            $replySource = 'automatic';
            if ($automaticResponsesEnabled) {
                foreach ($claimedMessages->reverse() as $message) {
                    $reply = $this->responseMatcher->match($message->message)?->template;
                    if ($reply !== null) {
                        break;
                    }
                }
            }
            if ($reply === null && $aiEnabled) {
                $history = WhatsAppMessage::query()
                    ->where('phone', $incoming->phone)
                    ->whereIn('direction', ['inbound', 'outbound'])
                    ->where('message_type', 'text')
                    ->whereNotNull('message')
                    ->whereNotIn('id', $claimedIds)
                    ->latest('received_at')
                    ->limit(16)
                    ->get()
                    ->reverse()
                    ->map(fn (WhatsAppMessage $message) => [
                        'role' => $message->direction === 'inbound' ? 'user' : 'assistant',
                        'text' => $message->message,
                    ])
                    ->values()
                    ->all();

                $replySource = 'ai_agent';
                $reply = $responseLanguage === 'es'
                    ? $this->ollama->chat($combinedMessage, $history, 'general')
                    : $this->ollama->chat($combinedMessage, $history, 'general', $responseLanguage);
            }

            if ($reply === null) {
                WhatsAppMessage::whereKey($claimedIds)->update(['auto_reply_status' => 'ignored']);
                return;
            }

            $reply = trim($reply);
            if ($reply === '') {
                throw new RuntimeException('El agente devolvió una respuesta vacía.');
            }

            if ($responseLanguage !== 'es') {
                $reply = $this->ollama->translate($reply, $responseLanguage);
            }
            $result = $this->whatsapp->sendText($replyRecipient, $reply, false);
            WhatsAppMessage::create([
                'provider_message_id' => data_get($result, 'messages.0.id'),
                'phone' => $replyRecipient,
                'contact_name' => $incoming->contact_name,
                'direction' => 'outbound',
                'message_type' => 'text',
                'message' => $reply,
                'status' => 'sent', 'source' => $replySource, 'language_code' => $responseLanguage,
                'raw_payload' => $result,
                'received_at' => now(),
            ]);
            WhatsAppMessage::whereKey($claimedIds)->update(['auto_reply_status' => 'sent']);
        } catch (Throwable $exception) {
            WhatsAppMessage::whereKey($claimedIds)->update(['auto_reply_status' => 'failed']);
            Log::error('No se pudo responder automáticamente al mensaje de WhatsApp', [
                'message_id' => $incoming->provider_message_id,
                'message_ids' => $claimedIds,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function settingEnabled(string $key): bool
    {
        $value = DB::table('app_settings')->where('key', $key)->value('value');

        return filter_var($value ?? '1', FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
