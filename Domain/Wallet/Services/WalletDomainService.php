<?php

namespace Domain\Wallet\Services;

use Domain\Wallet\Entities\Wallet;
use Domain\Wallet\ValueObjects\Direction;
use Domain\Wallet\ValueObjects\Money;

class WalletDomainService
{
    public function debit(Wallet $wallet, Money $amount): Wallet
    {
        if (! $wallet->hasSufficientBalance($amount)) {
            throw new \DomainException('Insufficient wallet balance.');
        }

        $wallet->debit($amount);

        return $wallet;
    }

    public function credit(Wallet $wallet, Money $amount): Wallet
    {
        $wallet->credit($amount);

        return $wallet;
    }

    public function getDirectionForAmount(int $amountCents): Direction
    {
        return $amountCents >= 0 ? Direction::In : Direction::Out;
    }
}
