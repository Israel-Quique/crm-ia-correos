<?php

namespace App\Livewire;

use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppTestNumberRegistry;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class PostalCampaignRegistry extends Component
{
    public string $scope = 'internacional';
    public ?string $campaignFeedback = null;
    public ?string $campaignError = null;
    public bool $showCreateCampaign = false;
    public ?int $infoCampaignId = null;

    public function mount(string $scope = 'internacional'): void
    {
        $this->scope = $scope;
    }

    public function openCreateCampaign(): void
    {
        $this->showCreateCampaign = true;
    }

    #[On('campaign-created')]
    public function campaignCreated(int $count): void
    {
        $this->campaignFeedback = "Campaña creada con {$count} paquete(s).";
        $this->campaignError = null;
    }

    public function getCampaignsProperty()
    {
        return PostalCampaign::withCount('messages')->whereJsonContains('filters->scope', $this->scope)->latest()->get();
    }

    public function getInfoCampaignProperty(): ?PostalCampaign
    {
        if (! $this->infoCampaignId) {
            return null;
        }

        return PostalCampaign::query()
            ->whereKey($this->infoCampaignId)
            ->whereJsonContains('filters->scope', $this->scope)
            ->with(['messages' => fn ($query) => $query
                ->whereIn('status', ['sent', 'failed', 'replied'])
                ->with('package')
                ->oldest('id')])
            ->first();
    }

    public function openCampaignInfo(int $id): void
    {
        $this->infoCampaignId = PostalCampaign::query()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->value('id');

        abort_if($this->infoCampaignId === null, 404);
    }

    public function closeCampaignInfo(): void
    {
        $this->infoCampaignId = null;
    }

    public function messageStatusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Enviado',
            'failed' => 'No se pudo enviar',
            'sending' => 'Enviando',
            'pending' => 'Pendiente',
            'replied' => 'Respondió',
            'cancelled' => 'Cancelado',
            default => ucfirst($status),
        };
    }

    public function getMessagesProperty()
    {
        return PostalCampaignMessage::with(['campaign', 'package'])->whereHas('campaign', fn ($query) => $query->whereJsonContains('filters->scope', $this->scope))->latest()->get();
    }

    public function sendCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->firstOrFail();

        if ($campaign->status === 'sending') {
            $this->campaignError = 'Esta campaña ya se está enviando.';
            return;
        }

        if ($campaign->pending < 1) {
            $this->campaignError = 'Esta campaña no tiene mensajes pendientes.';
            return;
        }

        $this->campaignFeedback = null;
        $this->campaignError = null;
        $campaign->update(['status' => 'sending']);
        $this->campaignFeedback = 'Envío iniciado. Los mensajes se procesarán uno por uno y el progreso se actualizará automáticamente.';
    }

    public function retryFailedCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->firstOrFail();
        $failed = $campaign->messages()->where('status', 'failed')->count();

        if ($failed === 0) {
            $this->campaignError = 'Esta campaña no tiene mensajes fallidos para reintentar.';
            return;
        }

        if ($campaign->status === 'sending') {
            $this->campaignError = 'Espera a que termine el envío actual antes de reintentar.';
            return;
        }

        DB::transaction(function () use ($campaign, $failed): void {
            $campaign->messages()
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'error_message' => null, 'updated_at' => now()]);
            $campaign->update([
                'failed' => max(0, $campaign->failed - $failed),
                'pending' => $campaign->pending + $failed,
                'status' => 'prepared',
            ]);
        });

        $this->sendCampaign($id);
    }

    public function processNextCampaignMessage(WhatsAppCloudApiService $whatsapp, WhatsAppTestNumberRegistry $testNumbers): void
    {
        $campaign = PostalCampaign::query()
            ->whereJsonContains('filters->scope', $this->scope)
            ->where('status', 'sending')
            ->where('pending', '>', 0)
            ->oldest('updated_at')
            ->first();

        if (! $campaign) {
            return;
        }

        if (($campaign->sent + $campaign->failed) > 0) {
            $lastProcessed = $campaign->messages()
                ->whereIn('status', ['sent', 'failed'])
                ->latest('updated_at')
                ->value('updated_at');
            $secondsPerMessage = $campaign->interval_seconds / max(1, $campaign->total);
            if ($lastProcessed && now()->lt(\Illuminate\Support\Carbon::parse($lastProcessed)->addMilliseconds($secondsPerMessage * 1000))) {
                return;
            }
        }

        $messageId = DB::transaction(function () use ($campaign): ?int {
            $lockedCampaign = PostalCampaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedCampaign || $lockedCampaign->status !== 'sending' || $lockedCampaign->pending < 1) {
                return null;
            }

            $message = $lockedCampaign->messages()
                ->where('status', 'pending')
                ->oldest('id')
                ->first();
            if (! $message) {
                return null;
            }

            $claimed = $lockedCampaign->messages()
                ->whereKey($message->id)
                ->where('status', 'pending')
                ->update(['status' => 'sending', 'updated_at' => now()]);

            return $claimed === 1 ? $message->id : null;
        });

        if ($messageId === null) {
            $this->finishCampaignIfComplete($campaign);
            return;
        }

        $message = PostalCampaignMessage::findOrFail($messageId);
        try {
            if ($campaign->test_mode && ! $testNumbers->contains((string) $message->phone)) {
                throw new \RuntimeException('El teléfono del paquete ya no está registrado en Números de prueba.');
            }

            $whatsapp->sendCampaignMessage($message);
            $this->campaignError = null;
        } catch (\Throwable $exception) {
            report($exception);
            $updated = $campaign->messages()
                ->whereKey($message->id)
                ->where('status', 'sending')
                ->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'updated_at' => now(),
                ]);
            if ($updated === 1) {
                $campaign->increment('failed');
                $campaign->decrement('pending');
            }
            $this->campaignError = $exception->getMessage();
        }

        $this->finishCampaignIfComplete($campaign);
        $campaign->refresh();
        $this->campaignFeedback = $campaign->status === 'sending'
            ? "Progreso: {$campaign->sent} de {$campaign->total} mensajes enviados."
            : "Envío finalizado: {$campaign->sent} enviado(s), {$campaign->failed} fallido(s).";
    }

    private function finishCampaignIfComplete(PostalCampaign $campaign): void
    {
        $campaign->refresh();
        if ($campaign->status === 'sending' && $campaign->pending === 0) {
            $campaign->update([
                'status' => $campaign->failed > 0 ? 'failed' : 'sent',
            ]);
        }
    }

    public function cancelCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->firstOrFail();

        if (! in_array($campaign->status, ['prepared', 'partial', 'sending'], true) || $campaign->pending < 1) {
            $this->campaignError = 'Esta campaña no tiene envíos pendientes que se puedan cancelar.';
            return;
        }

        $cancelled = DB::transaction(function () use ($campaign): ?int {
            $campaign = PostalCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (! in_array($campaign->status, ['prepared', 'partial', 'sending'], true) || $campaign->pending < 1) {
                return null;
            }

            $cancelled = $campaign->messages()
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            $campaign->update([
                'pending' => max(0, $campaign->pending - $cancelled),
                'status' => 'cancelled',
            ]);

            return $cancelled;
        });

        if ($cancelled === null) {
            $this->campaignError = 'Esta campaña ya no tiene envíos pendientes que se puedan cancelar.';
            return;
        }

        $this->campaignError = null;
        $this->campaignFeedback = 'Envíos pendientes cancelados. Los mensajes ya enviados se conservaron.';
    }

    public function deleteCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->firstOrFail();

        if ($campaign->status === 'sending') {
            $this->campaignError = 'No se puede eliminar una campaña mientras se está enviando.';
            return;
        }

        DB::transaction(fn () => $campaign->delete());
        $this->campaignError = null;
        $this->campaignFeedback = 'Campaña y mensajes asociados eliminados.';
    }

    public function render()
    {
        return view('livewire.postal-campaign-registry');
    }
}
