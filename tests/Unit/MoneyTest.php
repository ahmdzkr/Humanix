<?php

use App\Support\Money;

test('pembulatan setengah ke atas ke 2 desimal', function () {
    expect(Money::round('1.234'))->toBe('1.23')
        ->and(Money::round('1.235'))->toBe('1.24')
        ->and(Money::round('-1.235'))->toBe('-1.24')
        ->and(Money::of(null))->toBe('0.00')
        ->and(Money::of('1500000'))->toBe('1500000.00');
});

test('operasi uang presisi tanpa galat float', function () {
    // 0.1 + 0.2 dengan float = 0.30000000000000004
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::sub('10.00', '10.01'))->toBe('-0.01')
        ->and(Money::percent('10000000.00', '1'))->toBe('100000.00')
        ->and(Money::percent('7333333.00', '1.5'))->toBe('110000.00')
        ->and(Money::div('10000000.00', '173'))->toBe('57803.47')
        ->and(Money::isNegative('-0.01'))->toBeTrue()
        ->and(Money::add())->toBe('0.00');
});
