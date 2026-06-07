<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Evaluates a basic arithmetic expression.
 *
 * Security: this never uses eval(). It tokenizes the expression and walks a tiny
 * recursive-descent grammar that only understands numbers, `+ - * / %`, the `^`
 * power operator, parentheses, and unary minus. Anything else is rejected.
 */
class Calculator implements Tool
{
    /**
     * The tool name exposed to the model.
     */
    public function name(): string
    {
        return 'calculator';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Evaluate a basic arithmetic expression and return the numeric result. '
            .'Supports + - * / %, ^ for powers, parentheses, and decimals. '
            .'Example expression: "(1234 * 7 + 19) / 2".';
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'expression' => $schema->string()
                ->description('The arithmetic expression to evaluate, e.g. "3 * (4 + 5)".')
                ->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $expression = $request->string('expression')->toString();

        try {
            $result = $this->evaluate($expression);
        } catch (Throwable $e) {
            return "Could not evaluate \"{$expression}\": {$e->getMessage()}";
        }

        // Render integers without a trailing ".0" for nicer output.
        $formatted = $result == (int) $result
            ? (string) (int) $result
            : (string) $result;

        return "{$expression} = {$formatted}";
    }

    /**
     * The token cursor used while parsing.
     */
    private int $position = 0;

    /**
     * @var array<int, string>
     */
    private array $tokens = [];

    /**
     * Safely evaluate the given arithmetic expression.
     */
    private function evaluate(string $expression): float
    {
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;

        if ($this->tokens === []) {
            throw new \InvalidArgumentException('the expression is empty');
        }

        $value = $this->parseExpression();

        if ($this->position < count($this->tokens)) {
            throw new \InvalidArgumentException("unexpected token \"{$this->tokens[$this->position]}\"");
        }

        return $value;
    }

    /**
     * Break the expression into numbers, operators and parentheses.
     *
     * @return array<int, string>
     */
    private function tokenize(string $expression): array
    {
        if (! preg_match('/^[\d\s.+\-*\/%^()]*$/', $expression)) {
            throw new \InvalidArgumentException('it contains unsupported characters');
        }

        preg_match_all('/\d+\.?\d*|[.+\-*\/%^()]/', $expression, $matches);

        return $matches[0];
    }

    /**
     * expression = term (("+" | "-") term)*
     *
     * @phpstan-impure This advances the shared $position cursor as it consumes tokens.
     */
    private function parseExpression(): float
    {
        $value = $this->parseTerm();

        while (in_array($this->current(), ['+', '-'], true)) {
            $operator = $this->consume();
            $right = $this->parseTerm();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * term = factor (("*" | "/" | "%") factor)*
     */
    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while (in_array($this->current(), ['*', '/', '%'], true)) {
            $operator = $this->consume();
            $right = $this->parseFactor();

            $value = match ($operator) {
                '*' => $value * $right,
                '/' => $this->divide($value, $right),
                '%' => $this->modulo($value, $right),
                default => throw new \LogicException("unexpected operator \"{$operator}\""),
            };
        }

        return $value;
    }

    /**
     * factor = "-" factor | base ("^" factor)?
     */
    private function parseFactor(): float
    {
        if ($this->current() === '-') {
            $this->consume();

            return -$this->parseFactor();
        }

        $value = $this->parseBase();

        if ($this->current() === '^') {
            $this->consume();

            return $value ** $this->parseFactor();
        }

        return $value;
    }

    /**
     * base = number | "(" expression ")"
     */
    private function parseBase(): float
    {
        $token = $this->current();

        if ($token === '(') {
            $this->consume();
            $value = $this->parseExpression();

            if ($this->consume() !== ')') {
                throw new \InvalidArgumentException('missing a closing parenthesis');
            }

            return $value;
        }

        if ($token !== null && is_numeric($token)) {
            $this->consume();

            return (float) $token;
        }

        throw new \InvalidArgumentException('expected a number');
    }

    private function divide(float $left, float $right): float
    {
        if ($right === 0.0) {
            throw new \InvalidArgumentException('division by zero');
        }

        return $left / $right;
    }

    private function modulo(float $left, float $right): float
    {
        if ($right === 0.0) {
            throw new \InvalidArgumentException('modulo by zero');
        }

        return fmod($left, $right);
    }

    private function current(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function consume(): ?string
    {
        return $this->tokens[$this->position++] ?? null;
    }
}
