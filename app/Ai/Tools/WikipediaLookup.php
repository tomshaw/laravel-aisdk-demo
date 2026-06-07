<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Looks up a short factual summary from Wikipedia's public REST API.
 *
 * Demonstrates a tool that reaches out to an external HTTP service and returns
 * the result as an "observation" for the agent to reason about.
 */
class WikipediaLookup implements Tool
{
    /**
     * The tool name exposed to the model.
     */
    public function name(): string
    {
        return 'wikipedia_lookup';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Look up a concise factual summary of a topic, person, or place from Wikipedia. '
            .'Pass the article title, e.g. "Eiffel Tower" or "Ada Lovelace".';
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()
                ->description('The Wikipedia article title to summarize, e.g. "Great Barrier Reef".')
                ->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $topic = $request->string('topic')->trim()->toString();

        if ($topic === '') {
            return 'No topic was provided to look up.';
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'LaravelAiSdkDemo/1.0 (tutorial)'])
                ->timeout(15)
                ->get('https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($topic));
        } catch (Throwable $e) {
            return "Wikipedia lookup for \"{$topic}\" failed: {$e->getMessage()}";
        }

        if ($response->status() === 404) {
            return "No Wikipedia article was found for \"{$topic}\".";
        }

        if ($response->failed()) {
            return "Wikipedia lookup for \"{$topic}\" failed with status {$response->status()}.";
        }

        $extract = $this->jsonString($response, 'extract');

        if ($extract === '') {
            return "No summary is available for \"{$topic}\".";
        }

        $title = $this->jsonString($response, 'title', $topic);
        $url = $this->jsonString($response, 'content_urls.desktop.page');

        return trim("**{$title}**\n{$extract}".($url !== '' ? "\nSource: {$url}" : ''));
    }

    /**
     * Read a string value from the decoded JSON response, falling back to the
     * default when the field is missing or is not a string.
     */
    private function jsonString(Response $response, string $key, string $default = ''): string
    {
        $value = $response->json($key, $default);

        return is_string($value) ? $value : $default;
    }
}
