<?php

namespace App\Jobs;

use App\Enums\EclaimSyncStatus;
use App\Models\EclaimSync;
use App\Services\BpjsEclaimService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncEclaimBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Maximum execution time for one batch.
     */
    public int $timeout = 120;

    /**
     * Number of seconds the batch lock is held.
     */
    public int $uniqueFor = 130;

    /**
     * @param  array<int, int>  $claimIds
     */
    public function __construct(
        public EclaimSync $sync,
        public array $claimIds,
    ) {}

    public function uniqueId(): string
    {
        return 'eclaim-sync-'.$this->sync->id;
    }

    public function handle(BpjsEclaimService $eclaimService): void
    {
        $eclaimService->processBatch($this->sync, $this->claimIds);
    }

    public function failed(Throwable $exception): void
    {
        $this->sync->update([
            'status' => EclaimSyncStatus::Failed,
            'error_log' => [[
                'message' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]],
            'completed_at' => now(),
        ]);
    }
}
