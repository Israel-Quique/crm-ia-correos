<?php

namespace App\Jobs;

use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppTestNumberRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendPostalCampaignMessages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(WhatsAppCloudApiService $whatsapp, WhatsAppTestNumberRegistry $testNumbers): void
    {
        $campaign = PostalCampaign::find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $messages = $campaign->messages()->where('status', 'pending')->orderBy('id')->get();
        $messageCount = $messages->count();
        $startedAt = microtime(true);
        $processedMessages = 0;

        foreach ($messages as $message) {
            if ($campaign->fresh()->status !== 'sending') {
                break;
            }

            if ($campaign->test_mode && ! $testNumbers->contains((string) $message->phone)) {
                $this->markFailed($campaign, $message, 'El teléfono del paquete ya no está registrado en Números de prueba.');
                continue;
            }

            $claimed = DB::transaction(function () use ($campaign, $message): int {
                $lockedCampaign = PostalCampaign::query()
                    ->whereKey($campaign->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedCampaign || $lockedCampaign->status !== 'sending') {
                    return 0;
                }

                return PostalCampaignMessage::query()
                    ->whereKey($message->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'sending', 'updated_at' => now()]);
            });

            if ($claimed !== 1) {
                if ($campaign->fresh()->status !== 'sending') {
                    break;
                }
                continue;
            }

            try {
                $whatsapp->sendCampaignMessage($message->fresh());
            } catch (Throwable $exception) {
                report($exception);
                $this->markFailed($campaign, $message, $exception->getMessage());
            }

            $processedMessages++;
            if ($processedMessages < $messageCount) {
                $nextSendAt = $startedAt + ($campaign->interval_seconds * $processedMessages / $messageCount);
                $sleepMicroseconds = (int) (($nextSendAt - microtime(true)) * 1_000_000);
                if ($sleepMicroseconds > 0) {
                    usleep($sleepMicroseconds);
                }
            }
        }

        $this->finishCampaign($campaign);
    }

    public function failed(?Throwable $exception): void
    {
        $campaign = PostalCampaign::find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $error = $exception?->getMessage() ?: 'No se pudo completar el envío de la campaña.';
        $stuckMessages = $campaign->messages()->whereIn('status', ['pending', 'sending'])->get();
        foreach ($stuckMessages as $message) {
            $this->markFailed($campaign, $message, $error);
        }

        $this->finishCampaign($campaign);
    }

    private function markFailed(PostalCampaign $campaign, PostalCampaignMessage $message, string $error): void
    {
        $updated = $campaign->messages()
            ->whereKey($message->id)
            ->whereIn('status', ['pending', 'sending'])
            ->whereHas('campaign', fn ($query) => $query->where('status', 'sending'))
            ->update([
                'status' => 'failed',
                'error_message' => $error,
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $campaign->increment('failed');
            $campaign->decrement('pending');
        }
    }

    private function finishCampaign(PostalCampaign $campaign): void
    {
        $campaign->refresh();
        if ($campaign->pending === 0 && $campaign->status === 'sending') {
            $campaign->update([
                'status' => $campaign->failed > 0 ? 'failed' : 'sent',
            ]);
        }
    }
}
