<?php

use App\Ai\Tools\WikipediaLookup;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

function lookup(string $topic): string
{
    return (string) (new WikipediaLookup)->handle(new Request(['topic' => $topic]));
}

it('returns a Wikipedia summary', function () {
    Http::fake([
        'en.wikipedia.org/*' => Http::response([
            'title' => 'Eiffel Tower',
            'extract' => 'The Eiffel Tower is a wrought-iron lattice tower in Paris.',
            'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Eiffel_Tower']],
        ]),
    ]);

    expect(lookup('Eiffel Tower'))
        ->toContain('Eiffel Tower')
        ->toContain('wrought-iron lattice tower')
        ->toContain('https://en.wikipedia.org/wiki/Eiffel_Tower');
});

it('handles a missing article', function () {
    Http::fake(['en.wikipedia.org/*' => Http::response([], 404)]);

    expect(lookup('Asdfqwerty Nonexistent'))->toContain('No Wikipedia article was found');
});

it('handles an empty topic without calling the API', function () {
    Http::fake();

    expect(lookup(''))->toBe('No topic was provided to look up.');

    Http::assertNothingSent();
});
