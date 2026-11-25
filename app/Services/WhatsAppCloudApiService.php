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
    public function listMessageTemplates(): array
    {
        $this->ensureConfigured();

        $businessAccountId = (string) config('services.whatsapp.business_account_id');
        if ($businessAccountId === '') {
            throw new RuntimeException('WhatsApp Cloud API no está configurada: falta business_account_id.');
        }

        $templates = [];
        $url = "https://graph.facebook.com/{$this->apiVersion()}/{$businessAccountId}/message_templates";
        $query = [
                'limit' => 250,
                'fields' => 'id,name,language,status,category,components,quality_score,previous_quality_score',
        ];

        do {
            $response = $this->authenticatedClient()->get($url, $query)->throw();
            $templates = array_merge($templates, $response->json('data', []));
            $next = $response->json('paging.next');
            $url = is_string($next) && $next !== '' ? $next : null;
            $query = [];
        } while ($url !== null);

        return $templates;
    }

    public function createMessageTemplate(string $name, string $language, string $category, string $body, string $header = '', string $footer = '', string $mediaType = 'NONE'): array
    {
        $this->ensureConfigured();
        $businessAccountId = (string) config('services.whatsapp.business_account_id');
        if ($businessAccountId === '') {
            throw new RuntimeException('WhatsApp Cloud API no está configurada: falta business_account_id.');
        }

        $components = [];
        if ($mediaType !== 'NONE') {
            $format = ['IMAGE' => 'IMAGE', 'VIDEO' => 'VIDEO', 'DOCUMENT' => 'DOCUMENT', 'LOCATION' => 'LOCATION'][$mediaType] ?? 'IMAGE';
            $headerComponent = ['type' => 'HEADER', 'format' => $format];
            if ($mediaType !== 'LOCATION' && trim($header) !== '') $headerComponent['example'] = ['header_handle' => [$header]];
            $components[] = $headerComponent;
        } elseif (trim($header) !== '') {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => trim($header)];
        }
        $components[] = ['type' => 'BODY', 'text' => $body];
        if (trim($footer) !== '') $components[] = ['type' => 'FOOTER', 'text' => trim($footer)];

        return $this->authenticatedClient()
            ->post("https://graph.facebook.com/{$this->apiVersion()}/{$businessAccountId}/message_templates", [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components,
            ])
            ->throw()->json();
    }

    public function uploadTemplateMedia(string $path, string $mimeType): string
    {
        $this->ensureConfigured();
        $appId = (string) config('services.whatsapp.app_id');
        if ($appId === '') throw new RuntimeException('WhatsApp Cloud API no está configurada: falta app_id.');
        $size = filesize($path);
        if (! is_int($size) || $size < 1) throw new RuntimeException('El archivo multimedia está vacío.');
        $session = $this->authenticatedClient()->post("https://graph.facebook.com/{$this->apiVersion()}/{$appId}/uploads", [
            'file_length' => $size, 'file_type' => $mimeType, 'file_name' => basename($path),
        ])->throw()->json();
        $uploadId = (string) ($session['id'] ?? '');
        if ($uploadId === '') throw new RuntimeException('Meta no devolvió una sesión de carga multimedia.');
        $response = $this->authenticatedClient()->withHeaders(['file_offset' => '0'])
            ->withBody(file_get_contents($path), $mimeType)
            ->post("https://graph.facebook.com/{$this->apiVersion()}/{$uploadId}")
            ->throw()->json();
        $handle = (string) ($response['h'] ?? $response['handle'] ?? '');
        if ($handle === '') throw new RuntimeException('Meta no devolvió el identificador multimedia.');
        return $handle;
    }

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

        $response = $this->client()->retry(3, 1000, throw: false)->post($this->endpoint(), [
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

    public function sendTemplate(string $to, string $name, string $language = 'es', ?bool $testMode = null, array $parameters = []): array
    {
        $this->ensureConfigured();
        $to = $this->resolveRecipient(preg_replace('/[^0-9]/', '', $to) ?? '', $testMode);
        if ($to === '' || $name === '') {
            throw new RuntimeException('La plantilla o el nÃºmero de WhatsApp no son vÃ¡lidos.');
        }

        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($parameters !== []) {
            $template['components'] = [['type' => 'body', 'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $parameters)]];
        }
        $response = $this->client()->retry(3, 1000, throw: false)->post($this->endpoint(), [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ]);
        if ($response->failed()) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'No se pudo enviar la plantilla por WhatsApp.'));
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
        $campaign = $message->campaign;
        $campaignTemplate = (string) $campaign->template;
        if ($campaign->campaign_type === 'promocional' && str_starts_with($campaignTemplate, 'meta:')) {
            [$name, $language, $parameterCount] = array_pad(explode('|', substr($campaignTemplate, 5), 3), 3, '0');
            if ((int) $parameterCount === 0) {
                $parameterCount = $this->messageTemplateParameterCount($name, $language);
            }
            $package = $message->package;
            $values = array_values(array_filter([
                (string) ($package?->recipient ?: 'cliente'),
                (string) ($package?->tracking_number ?: ''),
                (string) ($package?->current_event ?: ''),
                now()->format('d/m/Y'),
                now()->format('H:i'),
            ], fn (string $value): bool => $value !== ''));
            $result = $this->sendTemplate((string) $message->phone, $name, $language, false, array_slice($values, 0, (int) $parameterCount));
        } else {
            $result = $this->sendText((string) $message->phone, $message->message, false);
        }
        $providerId = data_get($result, 'messages.0.id');
        $message->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'error_message' => null, 'phone' => $originalPhone]);
        $campaign->increment('sent');
        $campaign->decrement('pending');
        return $result;
    }

    private function messageTemplateParameterCount(string $name, string $language): int
    {
        foreach ($this->listMessageTemplates() as $template) {
            if (($template['name'] ?? '') !== $name || ($template['language'] ?? '') !== $language) {
                continue;
            }
            foreach (($template['components'] ?? []) as $component) {
                if (($component['type'] ?? '') !== 'BODY') {
                    continue;
                }
                preg_match_all('/\{\{(\d+)\}\}/', (string) ($component['text'] ?? ''), $matches);
                return count($matches[1] ?? []);
            }
        }
        return 0;
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
        return 'https://graph.facebook.com/'.$this->apiVersion().'/'.config('services.whatsapp.phone_number_id').'/messages';
    }

    private function apiVersion(): string
    {
        return trim((string) config('services.whatsapp.api_version'), '/');
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
