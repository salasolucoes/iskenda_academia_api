<?php

use Domain\Enrollment\ValueObjects\VerificationHash;

test('verification hash can be generated from data', function () {
    $hash = VerificationHash::generate('test-data');

    expect($hash->getValue())->toBe(hash('sha256', 'test-data'));
});

test('verification hash validates format', function () {
    new VerificationHash(hash('sha256', 'test'));
})->expectNotToPerformAssertions();

test('verification hash rejects invalid format', function () {
    new VerificationHash('invalid-hash');
})->throws(InvalidArgumentException::class);

test('verification hash equality works', function () {
    $a = VerificationHash::generate('data');
    $b = VerificationHash::generate('data');
    $c = VerificationHash::generate('other');

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});
