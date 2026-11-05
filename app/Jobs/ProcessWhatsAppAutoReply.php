<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppAutoReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppAutoReply implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $incomingMessageId)
    {
    }

    public function handle(WhatsAppAutoReplyService $autoReply): void
    {
        $incoming = WhatsAppMessage::find($this->incomingMessageId);
        if ($incoming) {
            $autoReply->replyTo($incoming);
        }
    }
}
