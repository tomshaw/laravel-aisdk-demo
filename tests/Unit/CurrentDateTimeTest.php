<?php

use App\Ai\Tools\CurrentDateTime;
use Laravel\Ai\Tools\Request;

function currentTime(array $arguments = []): string
{
    return (string) (new CurrentDateTime)->handle(new Request($arguments));
}

it('defaults to UTC when no timezone is given', function () {
    expect(currentTime())->toContain('(UTC)');
});

it('returns the time for a valid timezone', function () {
    expect(currentTime(['timezone' => 'Asia/Tokyo']))->toContain('(Asia/Tokyo)');
});

it('rejects an unknown timezone', function () {
    expect(currentTime(['timezone' => 'Mars/Olympus_Mons']))
        ->toStartWith('Unknown timezone');
});
