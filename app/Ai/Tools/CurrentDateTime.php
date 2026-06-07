<?php

namespace App\Ai\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Returns the current date and time, optionally in a given timezone.
 *
 * A classic "observation" tool: the model cannot know the real current time on
 * its own, so it must call this and reason about the result.
 */
class CurrentDateTime implements Tool
{
    /**
     * The tool name exposed to the model.
     */
    public function name(): string
    {
        return 'current_datetime';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current date and time. Optionally pass an IANA timezone '
            .'(e.g. "Asia/Tokyo", "America/New_York") to get the local time there.';
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('An IANA timezone identifier such as "Europe/Paris". Defaults to UTC.'),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $timezone = $request->string('timezone', 'UTC')->toString();

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return "Unknown timezone \"{$timezone}\". Please use an IANA identifier like \"Asia/Tokyo\".";
        }

        $now = CarbonImmutable::now($timezone);

        return $now->format('l, F j, Y \a\t g:i A').' ('.$timezone.')';
    }
}
