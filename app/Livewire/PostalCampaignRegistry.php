<?php

namespace App\Livewire;

use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppTestNumberRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PostalCampaignRegistry extends Component
{
    use WithPagination;

    public string $scope = 'internacional';
    public string $campaignSearch = '';
    public string $campaignStatus = '';
    public string $campaignPeriodFrom = '';
    public string $campaignPeriodTo = '';
    public string $campaignCreatedFrom = '';
    public string $campaignCreatedTo = '';
    public ?string $campaignFeedback = null;
    public ?string $campaignError = null;
    public bool $showCreateCampaign = false;
    public ?int $infoCampaignId = null;
    public int $infoPage = 1;
    public string $infoSearch = '';
    public string $infoStatus = 'all';

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
        $this->campaignFeedback = "Campaña creada; envío automático iniciado para {$count} paquete(s).";
        $this->campaignError = null;
    }

    public function getCampaignsProperty()
    {
        return $this->campaignQuery()
            ->withCount('messages')
            ->latest('created_at')
            ->latest('id')
            ->paginate(5, ['*'], 'campaignPage');
    }

    public function getCampaignSummaryProperty(): PostalCampaign
    {
        return $this->campaignQuery()
            ->selectRaw('COUNT(*) as campaign_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_packages')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as completed_campaigns")
            ->selectRaw('COALESCE(SUM(pending), 0) as pending_messages')
            ->selectRaw('COALESCE(SUM(sent), 0) as sent_messages')
            ->selectRaw("COALESCE(MAX(CASE WHEN status = 'sending' THEN 1 ELSE 0 END), 0) as is_sending")
            ->firstOrFail();
    }

    private function campaignQuery(): Builder
    {
        $query = PostalCampaign::query()
            ->whereJsonContains('filters->scope', $this->scope);

        if (trim($this->campaignSearch) !== '') {
            $query->whereLike('name', '%'.trim($this->campaignSearch).'%');
        }

        if ($this->campaignStatus !== '') {
            $query->where('status', $this->campaignStatus);
        }

        if ($this->campaignPeriodFrom !== '') {
            $query->where('filters->from', '>=', $this->campaignPeriodFrom);
        }

        if ($this->campaignPeriodTo !== '') {
            $query->where('filters->to', '<=', $this->campaignPeriodTo);
        }

        if ($this->campaignCreatedFrom !== '') {
            $query->whereDate('created_at', '>=', $this->campaignCreatedFrom);
        }

        if ($this->campaignCreatedTo !== '') {
            $query->whereDate('created_at', '<=', $this->campaignCreatedTo);
        }

        return $query;
    }

    public function updatedCampaignSearch(): void
    {
        $this->resetPage('campaignPage');
    }

    public function updatedCampaignStatus(): void
    {
        $this->resetPage('campaignPage');
    }

    public function updatedCampaignPeriodFrom(): void
    {
        $this->resetPage('campaignPage');
    }

    public function updatedCampaignPeriodTo(): void
    {
        $this->resetPage('campaignPage');
    }

    public function updatedCampaignCreatedFrom(): void
    {
        $this->resetPage('campaignPage');
    }

    public function updatedCampaignCreatedTo(): void
    {
        $this->resetPage('campaignPage');
    }

    public function clearCampaignFilters(): void
    {
        $this->campaignSearch = '';
        $this->campaignStatus = '';
        $this->campaignPeriodFrom = '';
        $this->campaignPeriodTo = '';
        $this->campaignCreatedFrom = '';
        $this->campaignCreatedTo = '';
        $this->resetPage('campaignPage');
    }

    public function getInfoCampaignProperty(): ?PostalCampaign
    {
        if (! $this->infoCampaignId) {
            return null;
        }

        return PostalCampaign::query()
            ->withTrashed()
            ->whereKey($this->infoCampaignId)
            ->whereJsonContains('filters->scope', $this->scope)
            ->first();
    }

    public function getInfoMessagesProperty(): LengthAwarePaginator
    {
        $messages = $this->infoMessagesQuery();
        $lastPage = max(1, (int) ceil((clone $messages)->count() / 10));
        $this->infoPage = min(max(1, $this->infoPage), $lastPage);

        $page = $messages->paginate(10, ['*'], 'infoPage', $this->infoPage);
        $pageMessages = $page->getCollection();
        $phoneVariants = $pageMessages
            ->flatMap(fn (PostalCampaignMessage $message): array => $this->phoneVariants((string) $message->phone))
            ->unique()
            ->values();
        $earliestSentAt = $pageMessages
            ->pluck('sent_at')
            ->filter()
            ->sort()
            ->first();
        $repliesByPhone = $phoneVariants->isEmpty() || ! $earliestSentAt
            ? collect()
            : WhatsAppMessage::query()
                ->whereIn('phone', $phoneVariants)
                ->where('direction', 'inbound')
                ->where('received_at', '>=', $earliestSentAt)
                ->get(['phone', 'received_at'])
                ->groupBy(fn (WhatsAppMessage $reply): string => preg_replace('/\D+/', '', $reply->phone) ?? '');

        $pageMessages->each(function (PostalCampaignMessage $message) use ($repliesByPhone): void {
            $phone = preg_replace('/\D+/', '', (string) $message->phone) ?? '';
            $sentAt = $message->sent_at;
            $hasReply = $message->status === 'replied'
                || ($sentAt && $repliesByPhone->get($phone, collect())->contains(
                    fn (WhatsAppMessage $reply): bool => $reply->received_at?->greaterThanOrEqualTo($sentAt) ?? false,
                ));
            $message->setAttribute('has_reply', $hasReply);
        });

        return $page;
    }

    public function getInfoMessageCountsProperty(): array
    {
        if (! $this->infoCampaignId) {
            return ['sent' => 0, 'pending' => 0, 'failed' => 0];
        }

        $counts = PostalCampaignMessage::query()
            ->where('postal_campaign_id', $this->infoCampaignId)
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('sent', 'delivered', 'read', 'replied') THEN 1 ELSE 0 END), 0) as sent")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('pending', 'sending') THEN 1 ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed")
            ->first();

        return [
            'sent' => (int) $counts->sent,
            'pending' => (int) $counts->pending,
            'failed' => (int) $counts->failed,
        ];
    }

    private function infoMessagesQuery(): Builder
    {
        abort_unless(in_array($this->infoStatus, ['all', 'sent', 'pending', 'failed'], true), 404);

        $messages = PostalCampaignMessage::query()
            ->where('postal_campaign_id', $this->infoCampaignId);

        if ($this->infoStatus === 'sent') {
            $messages->whereIn('status', ['sent', 'delivered', 'read', 'replied']);
        } elseif ($this->infoStatus === 'pending') {
            $messages->whereIn('status', ['pending', 'sending']);
        } elseif ($this->infoStatus === 'failed') {
            $messages->where('status', 'failed');
        }

        $search = trim($this->infoSearch);
        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $phoneTerms = [$search];
            if ($digits !== '' && ! preg_match('/[a-z]/i', $search)) {
                $phoneTerms[] = $digits;
                if (strlen($digits) >= 6) {
                    $phoneTerms[] = substr($digits, -8);
                }
            }
            $phoneTerms = array_values(array_unique($phoneTerms));

            $messages->where(function (Builder $query) use ($search, $phoneTerms): void {
                $query->whereHas(
                    'package',
                    fn (Builder $packageQuery) => $packageQuery->whereLike('tracking_number', '%'.$search.'%'),
                );
                foreach ($phoneTerms as $term) {
                    $query->orWhereLike('phone', '%'.$term.'%');
                }
            });
        }

        return $messages->with('package')->oldest('id');
    }

    public function updatedInfoSearch(): void
    {
        $this->infoPage = 1;
    }

    public function filterInfoStatus(string $status): void
    {
        abort_unless(in_array($status, ['sent', 'pending', 'failed'], true), 404);
        $this->infoStatus = $this->infoStatus === $status ? 'all' : $status;
        $this->infoPage = 1;
    }

    private function phoneVariants(string $phone): array
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        return array_values(array_unique(array_filter([
            $phone,
            $normalized,
            $normalized !== '' ? '+'.$normalized : null,
        ])));
    }

    public function openCampaignInfo(int $id): void
    {
        $this->infoCampaignId = PostalCampaign::query()
            ->withTrashed()
            ->whereKey($id)
            ->whereJsonContains('filters->scope', $this->scope)
            ->value('id');

        abort_if($this->infoCampaignId === null, 404);
        $this->infoPage = 1;
        $this->infoSearch = '';
        $this->infoStatus = 'all';
    }

    public function previousInfoPage(): void
    {
        $this->infoPage = max(1, $this->infoPage - 1);
    }

    public function nextInfoPage(): void
    {
        $lastPage = max(1, (int) ceil($this->infoMessagesQuery()->count() / 10));
        $this->infoPage = min($lastPage, $this->infoPage + 1);
    }

    public function openCampaignChat(int $id)
    {
        $message = PostalCampaignMessage::query()
            ->whereKey($id)
            ->whereHas('campaign', fn ($query) => $query->whereJsonContains('filters->scope', $this->scope))
            ->firstOrFail();

        return $this->redirect(route('chat', ['campaign_message' => $message->id]));
    }

    public function closeCampaignInfo(): void
    {
        $this->infoCampaignId = null;
        $this->infoPage = 1;
        $this->infoSearch = '';
        $this->infoStatus = 'all';
    }

    public function messageStatusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'read' => 'Visto',
            'failed' => 'Error al enviar',
            'sending' => 'Enviando',
            'pending' => 'Pendiente',
            'replied' => 'Respondió',
            'cancelled' => 'Cancelado',
            'received' => 'Recibido',
            default => 'Estado no identificado',
        };
    }

    public function campaignStatusLabel(string $status): string
    {
        return match ($status) {
            'prepared' => 'Preparada',
            'sending' => 'Enviando',
            'paused' => 'Pausada',
            'partial' => 'Parcial',
            'sent' => 'Enviada',
            'failed' => 'Fallida',
            'cancelled' => 'Cancelada',
            'completed' => 'Completada',
            'draft' => 'Borrador',
            default => 'Estado no identificado',
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

    public function pauseCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()->whereKey($id)->whereJsonContains('filters->scope', $this->scope)->firstOrFail();
        if ($campaign->status !== 'sending') {
            $this->campaignError = 'Solo se puede pausar una campaña en proceso.';
            return;
        }
        $campaign->update(['status' => 'paused']);
        $this->campaignFeedback = 'Campaña pausada. Los mensajes pendientes se conservaron.';
        $this->campaignError = null;
    }

    public function resumeCampaign(int $id): void
    {
        $campaign = PostalCampaign::query()->whereKey($id)->whereJsonContains('filters->scope', $this->scope)->firstOrFail();
        if ($campaign->status !== 'paused') {
            $this->campaignError = 'Solo se puede reanudar una campaña pausada.';
            return;
        }
        $campaign->update(['status' => 'sending']);
        $this->campaignFeedback = 'Campaña reanudada.';
        $this->campaignError = null;
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

        \App\Models\AuditLog::create(['user_id'=>auth()->id(),'action'=>'Campaña reintentada','category'=>'campañas','description'=>'Se reintentaron '.$failed.' mensajes fallidos de la campaña '.$campaign->name,'ip_address'=>request()->ip()]);

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

        if (! in_array($campaign->status, ['prepared', 'partial', 'sending', 'paused'], true) || $campaign->pending < 1) {
            $this->campaignError = 'Esta campaña no tiene envíos pendientes que se puedan cancelar.';
            return;
        }

        $cancelled = DB::transaction(function () use ($campaign): ?int {
            $campaign = PostalCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (! in_array($campaign->status, ['prepared', 'partial', 'sending', 'paused'], true) || $campaign->pending < 1) {
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

    public function render()
    {
        return view('livewire.postal-campaign-registry');
    }
}
