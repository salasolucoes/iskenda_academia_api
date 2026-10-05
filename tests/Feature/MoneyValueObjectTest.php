<?php

use Domain\Wallet\ValueObjects\Money;

test('money can be created from cents', function () {
    $money = new Money(1000);

    expect($money->getCents())->toBe(1000);
    expect($money->toFloat())->toBe(10.0);
});

test('money can be created from float', function () {
    $money = Money::fromFloat(10.50);

    expect($money->getCents())->toBe(1050);
});

test('money can be added', function () {
    $a = new Money(1000);
    $b = new Money(500);

    $result = $a->add($b);

    expect($result->getCents())->toBe(1500);
});

test('money can be subtracted', function () {
    $a = new Money(1000);
    $b = new Money(300);

    $result = $a->subtract($b);

    expect($result->getCents())->toBe(700);
});

test('money subtract with insufficient funds throws exception', function () {
    $a = new Money(100);
    $b = new Money(500);

    $a->subtract($b);
})->throws(UnderflowException::class);

test('money cannot be negative', function () {
    new Money(-1);
})->throws(InvalidArgumentException::class);

test('money comparison works', function () {
    $a = new Money(500);
    $b = new Money(300);
    $c = new Money(500);

    expect($a->isGreaterThanOrEqual($b))->toBeTrue();
    expect($b->isGreaterThanOrEqual($a))->toBeFalse();
    expect($a->isGreaterThanOrEqual($c))->toBeTrue();
    expect($a->equals($c))->toBeTrue();
});

test('money isZero works', function () {
    expect((new Money(0))->isZero())->toBeTrue();
    expect((new Money(1))->isZero())->toBeFalse();
});
