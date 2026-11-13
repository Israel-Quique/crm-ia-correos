<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWhatsAppInboundMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public int $messageId,
        public string $mediaId,
        public string $mediaType,
    ) {
        $this->onQueue('whatsapp-media');
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(WhatsAppCloudApiService $whatsapp): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (! $message) {
            return;
        }

        $media = $whatsapp->downloadInboundImage(
            $this->mediaId,
            (string) $message->provider_message_id,
            $this->mediaType,
        );
        $payload = $message->raw_payload ?? [];
        $payload[$this->mediaType]['local_path'] = $media['path'];
        $payload[$this->mediaType]['local_mime_type'] = $media['mime_type'];
        $message->forceFill(['raw_payload' => $payload])->save();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo descargar el medio recibido por WhatsApp tras agotar los reintentos.', [
            'message_id' => $this->messageId,
            'media_type' => $this->mediaType,
            'exception' => $exception->getMessage(),
        ]);
    }
}
