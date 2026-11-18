<?php

namespace App\Events;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClaimStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Claim $claim,
        public ?ClaimStatus $previousStatus,
        public ?User $actor = null,
    ) {}
}
