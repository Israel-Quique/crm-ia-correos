<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Storage;

class WhatsAppMediaController extends Controller
{
    public function show(WhatsAppMessage $message)
    {
        abort_unless($message->direction === 'inbound' && in_array($message->message_type, ['image', 'video', 'audio', 'document', 'sticker'], true), 404);

        $type = $message->message_type;
        $path = (string) data_get($message->raw_payload, $type.'.local_path', '');
        abort_unless(str_starts_with($path, 'whatsapp-media/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $mimeType = (string) data_get($message->raw_payload, $type.'.local_mime_type', 'image/jpeg');
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/3gpp', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'], true), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
