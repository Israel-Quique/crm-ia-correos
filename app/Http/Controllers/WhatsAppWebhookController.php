<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Models\PostalCampaignMessage;
use App\Jobs\ProcessWhatsAppAutoReply;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppCloudApiService $whatsapp,
    ) {
    }

    public function verify(Request $request)
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $providedToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        if ($verifyToken !== ''
            && $mode === 'subscribe'
            && hash_equals($verifyToken, (string) $providedToken)) {
            return response($request->query('hub_challenge', $request->query('hub.challenge')), 200);
        }
        return response('Token de verificación inválido.', 403);
    }

    public function receive(Request $request)
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $secret = trim((string) config('services.whatsapp.app_secret'));
        if ($secret === '' || in_array(strtolower($secret), [
            'tu_app_secret_real_aqui',
            'your_app_secret_here',
            'replace_me',
            'changeme',
        ], true)) {
            Log::error('Webhook de WhatsApp rechazado: WHATSAPP_APP_SECRET falta o sigue siendo un valor de ejemplo.');
            return response()->json(['message' => 'Webhook de WhatsApp no configurado.'], 503);
        }
        if (! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            Log::warning('Webhook de WhatsApp rechazado: firma inválida.');
            return response()->json(['message' => 'Firma inválida.'], 403);
        }
        $payload = $request->all();
        $messageCount = 0;
        $statusCount = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');
                foreach ($value['messages'] ?? [] as $incoming) {
                    $messageCount++;
                    $providerMessageId = (string) ($incoming['id'] ?? '');
                    if ($providerMessageId === '') {
                        Log::warning('Mensaje de WhatsApp sin identificador del proveedor; se omitió.');
                        continue;
                    }

                    $type = (string) ($incoming['type'] ?? 'unknown');
                    $text = $type === 'text' ? data_get($incoming, 'text.body') : '['.ucfirst($type).']';
                    $phone = (string) ($incoming['from'] ?? '');
                    $contact = $contacts->get($phone);
                    if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) && ($mediaId = (string) data_get($incoming, $type.'.id')) !== '') {
                        try {
                            $media = $this->whatsapp->downloadInboundImage($mediaId, $providerMessageId, $type);
                            $incoming[$type]['local_path'] = $media['path'];
                            $incoming[$type]['local_mime_type'] = $media['mime_type'];
                            $text = (string) data_get($incoming, $type.'.caption', $type === 'sticker' ? '' : '[Imagen]');
                        } catch (\Throwable $exception) {
                            Log::error('No se pudo descargar una imagen recibida por WhatsApp', [
                                'message_id' => $providerMessageId,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }
                    $message = WhatsAppMessage::updateOrCreate(['provider_message_id' => $providerMessageId], [
                        'phone' => $phone,
                        'contact_name' => data_get($contact, 'profile.name'),
                        'direction' => 'inbound', 'message_type' => $type, 'message' => $text,
                        'status' => 'received', 'source' => 'webhook', 'raw_payload' => $incoming,
                        'received_at' => isset($incoming['timestamp']) ? now()->setTimestamp((int) $incoming['timestamp']) : now(),
                    ]);
                    if ($type === 'text' && trim((string) $message->message) !== '') {
                        ProcessWhatsAppAutoReply::dispatch($message->id)
                            ->onConnection('database')
                            ->delay(now()->addMinute());
                    }
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    if (! empty($status['id']) && ! empty($status['status'])) {
                        $statusCount++;
                        WhatsAppMessage::where('provider_message_id', $status['id'])->update(['status' => $status['status']]);
                        PostalCampaignMessage::where('provider_message_id', $status['id'])
                            ->update(['status' => $status['status'], 'updated_at' => now()]);
                    }
                }
            }
        }
        Log::info('Webhook de WhatsApp procesado', [
            'entries' => count($payload['entry'] ?? []),
            'messages' => $messageCount,
            'statuses' => $statusCount,
        ]);
        return response()->json(['received' => true]);
    }
}
