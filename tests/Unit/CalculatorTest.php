<?php

use App\Ai\Tools\Calculator;
use Laravel\Ai\Tools\Request;

function calculate(string $expression): string
{
    return (string) (new Calculator)->handle(new Request(['expression' => $expression]));
}

it('evaluates arithmetic expressions', function (string $expression, string $expected) {
    expect(calculate($expression))->toEndWith('= '.$expected);
})->with([
    'addition' => ['2 + 2', '4'],
    'operator precedence' => ['2 + 3 * 4', '14'],
    'parentheses' => ['(2 + 3) * 4', '20'],
    'the ReAct demo prompt' => ['1234 * 7 + 19', '8657'],
    'unary minus' => ['-5 + 8', '3'],
    'powers' => ['2 ^ 10', '1024'],
    'decimals' => ['0.1 + 0.2', '0.3'],
    'division' => ['9 / 2', '4.5'],
    'modulo' => ['10 % 3', '1'],
]);

it('rejects unsafe or malformed input without using eval', function (string $expression) {
    expect(calculate($expression))->toStartWith('Could not evaluate');
})->with([
    'php code' => ['phpinfo()'],
    'letters' => ['2 + abc'],
    'dangling operator' => ['2 +'],
    'unbalanced parentheses' => ['(2 + 3'],
    'empty' => ['   '],
]);

it('reports division by zero', function () {
    expect(calculate('5 / 0'))->toContain('division by zero');
});
