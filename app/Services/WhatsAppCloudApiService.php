<?php

namespace App\Services;

use App\Models\PostalCampaignMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use App\Services\WhatsAppTestNumberRegistry;

class WhatsAppCloudApiService
{
    public function downloadInboundImage(string $mediaId, string $messageId, string $mediaType = 'image'): array
    {
        $token = (string) config('services.whatsapp.access_token');
        if ($token === '') {
            throw new RuntimeException('WhatsApp Cloud API no está configurada: falta access_token.');
        }

        $apiVersion = (string) config('services.whatsapp.api_version');
        $metadata = $this->authenticatedClient()
            ->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}")
            ->throw()
            ->json();
        $url = (string) data_get($metadata, 'url', '');
        $mimeType = strtolower((string) data_get($metadata, 'mime_type', ''));

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/3gpp', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'];
        if (! $this->isTrustedMediaUrl($url) || ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('WhatsApp devolvió una URL o formato de imagen no permitido.');
        }

        $mediaResponse = $this->authenticatedClient()
            ->accept($mimeType)
            ->withOptions(['allow_redirects' => false])
            ->get($url)
            ->throw();
        $contents = $mediaResponse->body();
        $maxBytes = (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) ? 16 * 1024 * 1024 : 100 * 1024 * 1024;
        if ($contents === '' || strlen($contents) > $maxBytes) {
            throw new RuntimeException('La imagen de WhatsApp está vacía o supera el límite de 10 MB.');
        }
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) !== $mimeType) {
            throw new RuntimeException('El contenido descargado no coincide con el tipo de imagen informado por WhatsApp.');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'text/plain' => 'txt',
        };
        $path = 'whatsapp-media/'.hash('sha256', $messageId).'.'.$extension;
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar la imagen recibida de WhatsApp.');
        }

        return ['path' => $path, 'mime_type' => $mimeType, 'media_type' => $mediaType];
    }

    public function sendText(string $to, string $text, ?bool $testMode = null, ?string $testRecipient = null): array
    {
        $this->ensureConfigured();
        $to = preg_replace('/[^0-9]/', '', $to) ?? '';
        if ($to === '') throw new RuntimeException('El número de WhatsApp está vacío o no es válido.');
        $to = $this->resolveRecipient($to, $testMode, $testRecipient);

        $response = $this->client()->post($this->endpoint(), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);

        if ($response->failed()) {
            Log::error('WhatsApp Cloud API rechazó el mensaje', ['status' => $response->status(), 'body' => $response->json()]);
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'No se pudo enviar el mensaje por WhatsApp.'));
        }

        return $response->json();
    }

    public function sendMedia(string $to, string $path, string $mimeType, bool $testMode = false, ?string $caption = null): array
    {
        $this->ensureConfigured(); $to = $this->resolveRecipient(preg_replace('/[^0-9]/', '', $to) ?? '', $testMode);
        if (! is_file($path)) throw new RuntimeException('No se encontró el archivo multimedia.');
        $media = $this->client()->attach('file', fopen($path, 'r'), basename($path), ['Content-Type' => $mimeType])->post('https://graph.facebook.com/'.config('services.whatsapp.api_version').'/'.config('services.whatsapp.phone_number_id').'/media', ['messaging_product' => 'whatsapp']);
        if ($media->failed()) throw new RuntimeException((string) data_get($media->json(), 'error.message', 'No se pudo cargar el archivo en WhatsApp.'));
        $type = str_starts_with($mimeType, 'image/') ? 'image' : (str_starts_with($mimeType, 'video/') ? 'video' : (str_starts_with($mimeType, 'audio/') ? 'audio' : 'document'));
        $body = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => $type, $type => ['id' => data_get($media->json(), 'id')]];
        if ($caption && in_array($type, ['image', 'video'], true)) $body[$type]['caption'] = $caption;
        $response = $this->client()->post($this->endpoint(), $body);
        if ($response->failed()) throw new RuntimeException((string) data_get($response->json(), 'error.message', 'No se pudo enviar el archivo por WhatsApp.'));
        return $response->json();
    }

    public function sendChatText(
        string $to,
        string $text,
        bool $testMode,
        WhatsAppTestNumberRegistry $testNumbers,
    ): array {
        $to = preg_replace('/\D+/', '', $to) ?? '';
        if ($to === '') {
            throw new RuntimeException('El número de WhatsApp está vacío o no es válido.');
        }

        if (! $testMode || $testNumbers->contains($to)) {
            return ['response' => $this->sendText($to, $text, false), 'recipient' => $to];
        }

        $testRecipient = preg_replace(
            '/\D+/',
            '',
            (string) config('services.whatsapp.test_recipient', '64229403'),
        ) ?? '';
        if ($testRecipient === '') {
            throw new RuntimeException('No hay un número de prueba configurado para redirigir este mensaje.');
        }

        return [
            'response' => $this->sendText($testRecipient, $text, false),
            'recipient' => $testRecipient,
        ];
    }

    public function sendCampaignMessage(PostalCampaignMessage $message): array
    {
        $originalPhone = $message->phone;
        $result = $this->sendText((string) $message->phone, $message->message, false);
        $providerId = data_get($result, 'messages.0.id');
        $message->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'error_message' => null, 'phone' => $originalPhone]);
        $message->campaign()->increment('sent');
        $message->campaign()->decrement('pending');
        return $result;
    }

    private function client(): PendingRequest
    {
        return $this->authenticatedClient()->acceptJson();
    }

    private function authenticatedClient(): PendingRequest
    {
        $client = Http::withToken(config('services.whatsapp.access_token'))
            ->acceptJson()
            ->timeout((int) config('services.whatsapp.timeout', 20));
        $caBundle = config('services.whatsapp.ca_bundle');
        if (is_string($caBundle) && is_file($caBundle)) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }
        return $client;
    }

    private function endpoint(): string
    {
        return 'https://graph.facebook.com/'.config('services.whatsapp.api_version').'/'.config('services.whatsapp.phone_number_id').'/messages';
    }

    private function ensureConfigured(): void
    {
        foreach (['phone_number_id', 'access_token'] as $key) {
            if (! config('services.whatsapp.'.$key)) throw new RuntimeException('WhatsApp Cloud API no está configurada: falta '.$key.'.');
        }
    }

    private function resolveRecipient(string $to, ?bool $testMode = null, ?string $testRecipient = null): string
    {
        if ($testMode === false || ($testMode === null && !(bool) config('services.whatsapp.test_mode', true))) return $to;
        $test = preg_replace('/[^0-9]/', '', $testRecipient ?: (string) config('services.whatsapp.test_recipient', '64229403')) ?? '';
        if ($test === '') throw new RuntimeException('WHATSAPP_TEST_RECIPIENT no está configurado.');
        return $test;
    }

    private function isTrustedMediaUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host === '') {
            return false;
        }

        foreach (['facebook.com', 'fbsbx.com', 'fbcdn.net'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
