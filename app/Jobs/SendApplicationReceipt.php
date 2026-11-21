<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ApplicationReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendApplicationReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public ApplicationReceipt $receipt) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if ($user) {
            $user->notifyNow($this->receipt, ['mail']);
        }
    }
}
