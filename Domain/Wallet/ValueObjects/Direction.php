<?php

namespace Domain\Wallet\ValueObjects;

enum Direction: string
{
    case In = 'in';
    case Out = 'out';
}
