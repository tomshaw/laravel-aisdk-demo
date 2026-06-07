<?php

namespace App\Ai\Enums;

/**
 * Example prompts shown in the chat hero/empty state. The backing value is the
 * example question; each case maps to a human-readable category label.
 */
enum ExamplePrompt: string
{
    case Calculator = 'What is 1,234 × 7 + 19?';
    case DateTime = 'What time is it in Tokyo right now?';
    case Wikipedia = 'Tell me about the Eiffel Tower.';
    case WebSearch = 'What are the newest features in Laravel 13?';

    /**
     * The example prompt text shown to, and sent by, the user.
     */
    public function example(): string
    {
        return $this->value;
    }

    /**
     * The category label displayed above the example.
     */
    public function label(): string
    {
        return match ($this) {
            self::Calculator => 'Calculator',
            self::DateTime => 'Date & time',
            self::Wikipedia => 'Wikipedia',
            self::WebSearch => 'Web search',
        };
    }
}
