<?php

namespace App\Jobs;

use App\Services\CdsDeclarationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCdsDeclarationsForCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public array $trackings)
    {
    }

    public function handle(CdsDeclarationSyncService $sync): void
    {
        $sync->sync($this->trackings, 'internacional');
    }
}
