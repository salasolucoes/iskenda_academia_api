<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('idempotency:clean')]
#[Description('Remove expired idempotency keys')]
class CleanExpiredIdempotencyKeys extends Command
{
    public function handle(): int
    {
        $deleted = DB::table('idempotency_keys')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Cleaned {$deleted} expired idempotency keys.");

        return self::SUCCESS;
    }
}
