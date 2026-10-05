<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Infrastructure\Persistence\Eloquent\Models\Cart;

class ExpiredCartCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        Cart::where('updated_at', '<', now()->subDays(7))
            ->each(function (Cart $cart) {
                $cart->items()->delete();
                $cart->delete();
            });
    }
}
