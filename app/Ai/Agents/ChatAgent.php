<?php

namespace App\Ai\Agents;

use App\Ai\Tools\Calculator;
use App\Ai\Tools\CurrentDateTime;
use App\Ai\Tools\WikipediaLookup;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

/**
 * A minimal ReAct agent.
 *
 * The Laravel AI SDK runs the ReAct loop for us: the model reasons about the
 * question, decides whether to *act* by calling one of the tools below, the SDK
 * executes the tool and feeds the *observation* back, and the loop repeats until
 * the model is ready to answer. `#[MaxSteps]` simply bounds that loop.
 */
#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[Temperature(0.7)]
#[MaxSteps(8)]
class ChatAgent implements Agent, Conversational, HasTools
{
    // `Promptable` adds prompt()/stream(); `RemembersConversations` persists and
    // replays the conversation history (the messages() method) automatically.
    use Promptable, RemembersConversations;

    /**
     * Get the instructions (system prompt) that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a friendly, knowledgeable assistant that answers using the ReAct pattern:
        Reason about the question, Act by calling a tool when it helps, observe the
        result, then continue until you can give a clear final answer.

        Guidelines:
        - Think step by step, but keep your final answer concise and well formatted (Markdown).
        - Use the `calculator` tool for any arithmetic instead of computing in your head.
        - Use the `current_datetime` tool whenever the user asks about the current date or time.
        - Use the `wikipedia_lookup` tool for factual background on a specific topic, person, or place.
        - Use web search for recent events or anything that may have changed after your training.
        - If a tool fails or returns nothing useful, say so honestly rather than guessing.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<int, \Laravel\Ai\Contracts\Tool|\Laravel\Ai\Providers\Tools\ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            new Calculator,
            new CurrentDateTime,
            new WikipediaLookup,
            (new WebSearch)->max(5),
        ];
    }
}
