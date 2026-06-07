<?php

use App\Ai\Agents\ChatAgent;
use App\Ai\Enums\ExamplePrompt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, array{role: string, content: string, tools: array<int, string>}> $messages
 */
new class extends Component
{
    /**
     * The id of the (seeded) demo user this chat runs as.
     */
    public int $userId;

    /**
     * The active conversation id, or null when starting a new conversation.
     */
    public ?string $conversationId = null;

    /**
     * Switch the thread to the conversation chosen in the sidebar (null starts
     * a fresh conversation). Dispatched by the sidebar component.
     */
    #[On('conversation-changed')]
    public function changeConversation(?string $id): void
    {
        $this->conversationId = $id;

        unset($this->messages);
    }

    /**
     * Send a prompt to the agent and stream the answer back to the browser.
     *
     * The Laravel AI SDK runs the ReAct loop and persists both the user and
     * assistant messages (via the RemembersConversations trait) once the stream
     * completes. We forward text deltas to the `answer` target and tool calls to
     * the `status` target using Livewire's wire:stream.
     */
    public function send(string $prompt): void
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return;
        }

        // The ReAct loop streams over a long-lived request (tool calls, web
        // search, multi-step reasoning); lift the request time limit so the
        // response isn't truncated mid-stream by PHP's max_execution_time.
        set_time_limit(0);

        $agent = $this->conversationId === null
            ? ChatAgent::make()->forUser($this->user())
            : ChatAgent::make()->continue($this->conversationId, as: $this->user());

        $response = $agent->stream($prompt);

        $answer = '';

        foreach ($response as $event) {
            if ($event instanceof ToolCall) {
                $this->stream(to: 'status', content: 'Using '.$event->toolCall->name.'…', replace: true);
            } elseif ($event instanceof TextDelta) {
                // Render the accumulated answer as Markdown on each delta so the
                // live stream shows formatted text (not raw Markdown), matching
                // how the persisted message is rendered once the turn completes.
                $answer .= $event->delta;

                $this->stream(
                    to: 'answer',
                    content: Str::markdown($answer, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
                    replace: true,
                );
            }
        }

        // After the stream finishes, the SDK has persisted the conversation.
        $this->conversationId = $response->conversationId;

        unset($this->messages);

        // Tell the sidebar to refresh its history list and highlight this
        // (possibly brand-new) conversation.
        $this->dispatch('conversation-updated', id: $this->conversationId);
    }

    /**
     * The messages for the active conversation, oldest first.
     *
     * @return Collection<int, array{role: string, content: string, tools: array<int, string>}>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->conversationId === null) {
            return new Collection();
        }

        return ConversationMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get()
            ->map(function (ConversationMessage $message): array {
                $role = $message->getAttribute('role');
                $content = $message->getAttribute('content');

                return [
                    'role' => is_string($role) ? $role : '',
                    'content' => is_string($content) ? $content : '',
                    'tools' => $this->toolNames($message->getAttribute('tool_calls')),
                ];
            })
            ->values();
    }

    /**
     * Extract the tool names from a message's persisted tool_calls payload.
     *
     * @return array<int, string>
     */
    private function toolNames(mixed $toolCalls): array
    {
        if (! is_array($toolCalls)) {
            return [];
        }

        $names = [];

        foreach ($toolCalls as $call) {
            $name = is_array($call) ? ($call['name'] ?? null) : null;

            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Whether the active conversation already has messages to display.
     */
    #[Computed]
    public function hasMessages(): bool
    {
        return $this->messages->isNotEmpty();
    }

    /**
     * The example prompts shown in the empty/hero state.
     *
     * @return array<int, ExamplePrompt>
     */
    #[Computed]
    public function examplePrompts(): array
    {
        return ExamplePrompt::cases();
    }

    protected function user(): User
    {
        return User::findOrFail($this->userId);
    }
}; ?>

<div x-data="chatBox()" class="flex min-w-0 flex-1 flex-col">
    {{-- Header --}}
    <header class="flex items-center justify-between border-b border-border bg-bg/60 px-4 py-3 backdrop-blur-md md:px-6">
        <div class="flex items-center gap-3">
            <button type="button" @click="sidebarOpen = true" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-surface-2 md:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-muted">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                Claude Sonnet 4.6
            </span>
        </div>

        <button
            type="button"
            @click="toggleTheme()"
            class="grid h-9 w-9 place-items-center rounded-lg border border-border bg-surface transition hover:bg-surface-2"
            aria-label="Toggle dark mode"
        >
            <svg class="hidden h-4.5 w-4.5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            <svg class="h-4.5 w-4.5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
    </header>

    {{-- Thread --}}
    <main x-ref="thread" class="relative flex-1 overflow-y-auto scroll-soft">
        <div class="mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6 px-4 py-8 md:px-6">

            {{-- Empty / hero state --}}
            @unless ($this->hasMessages)
                <div x-show="!streaming" class="flex flex-1 flex-col items-center justify-center py-10 text-center">
                    <h1 class="font-display text-4xl font-semibold tracking-tight md:text-5xl">
                        Ask me <span class="text-accent">anything</span>.
                    </h1>
                    <p class="mt-3 max-w-md text-[15px] text-muted">
                        An agent that reasons, reaches for tools, and answers — built on the Laravel AI SDK.
                    </p>

                    <div class="mt-8 grid w-full max-w-xl gap-2.5 sm:grid-cols-2">
                        @foreach ($this->examplePrompts as $prompt)
                            <button
                                type="button"
                                @click="ask(@js($prompt->example()))"
                                class="group flex cursor-pointer flex-col gap-1 rounded-xl border border-border bg-surface/70 p-3.5 text-left transition hover:border-accent hover:bg-accent-soft/40"
                            >
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-accent">{{ $prompt->label() }}</span>
                                <span class="text-sm text-ink/90">{{ $prompt->example() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endunless

            {{-- Persisted messages --}}
            @foreach ($this->messages as $message)
                <div wire:key="msg-{{ $loop->index }}" class="rise">
                    @if ($message['role'] === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-accent px-4 py-2.5 text-[15px] text-accent-fg shadow-sm">
                                {{ $message['content'] }}
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col gap-2">
                            @if (! empty($message['tools']))
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach (collect($message['tools'])->unique() as $tool)
                                        <span wire:key="msg-{{ $loop->parent->index }}-tool-{{ $tool }}" class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-medium text-muted">
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 1 5.4-5.4l-2.8 2.8-2-2 2.8-2.8z"/></svg>
                                            {{ $tool }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="reply max-w-[90%] text-[15px]">
                                {!! str($message['content'])->markdown(['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Optimistic in-flight question --}}
            <div x-show="streaming" x-cloak class="flex justify-end">
                <div class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-accent px-4 py-2.5 text-[15px] text-accent-fg shadow-sm" x-text="pending"></div>
            </div>

            {{-- Live streaming reply (always present so wire:stream can target it) --}}
            <div x-show="streaming" x-cloak class="flex flex-col gap-2">
                <div x-ref="status" wire:stream="status" class="text-xs font-medium text-accent"></div>
                <div class="max-w-[90%] text-[15px]">
                    <span x-show="!hasAnswer" class="inline-flex gap-1 align-middle">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted [animation-delay:-0.3s]"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted [animation-delay:-0.15s]"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted"></span>
                    </span>
                    <div x-ref="answer" wire:stream="answer" class="reply" :class="hasAnswer && 'stream-caret'"></div>
                </div>
            </div>
        </div>
    </main>

    {{-- Composer --}}
    <footer class="border-t border-border bg-bg/60 px-4 py-4 backdrop-blur-md md:px-6">
        <form @submit.prevent="submit()" class="mx-auto w-full max-w-3xl">
            <div class="flex items-end gap-2 rounded-2xl border border-border bg-surface p-2 shadow-sm transition focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20">
                <textarea
                    x-ref="input"
                    x-model="draft"
                    @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); submit() }"
                    rows="1"
                    placeholder="Ask anything… (Shift + Enter for a new line)"
                    class="max-h-40 min-h-10 flex-1 resize-none bg-transparent px-3 py-2 text-[15px] placeholder:text-muted focus:outline-none"
                    x-effect="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                ></textarea>
                <button
                    type="submit"
                    :disabled="streaming || draft.trim() === ''"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-fg transition enabled:hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="Send"
                >
                    <svg x-show="!streaming" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    <svg x-show="streaming" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.6" opacity="0.9"/></svg>
                </button>
            </div>
            <p class="mt-2 text-center text-[11px] text-muted">
                The agent can use a calculator, the current time, Wikipedia, and web search.
            </p>
        </form>
    </footer>

    @script
    <script>
        Alpine.data('chatBox', () => ({
            draft: '',
            pending: '',
            streaming: false,
            hasAnswer: false,
            observer: null,

            init() {
                // Live-grow the reply and keep the thread pinned to the bottom.
                this.observer = new MutationObserver(() => {
                    this.hasAnswer = (this.$refs.answer?.textContent.trim().length ?? 0) > 0;
                    if (this.streaming) this.toBottom();
                });
                if (this.$refs.answer) {
                    this.observer.observe(this.$refs.answer, { childList: true, subtree: true, characterData: true });
                }

                // Switching conversations from the sidebar clears any half-typed
                // draft or stale in-flight UI in the composer/thread.
                Livewire.on('conversation-changed', () => this.reset());

                this.$nextTick(() => this.toBottom());
            },

            toBottom() {
                const el = this.$refs.thread;
                if (el) el.scrollTop = el.scrollHeight;
            },

            submit() {
                const text = this.draft.trim();
                if (text === '' || this.streaming) return;

                this.pending = text;
                this.draft = '';
                this.hasAnswer = false;
                this.streaming = true;

                // Clear any reply streamed during the previous turn.
                if (this.$refs.answer) this.$refs.answer.innerHTML = '';
                if (this.$refs.status) this.$refs.status.textContent = '';

                this.$nextTick(() => this.toBottom());

                this.$wire.send(text).finally(() => {
                    this.streaming = false;
                    this.pending = '';
                    this.$nextTick(() => { this.toBottom(); this.$refs.input?.focus(); });
                });
            },

            ask(text) {
                this.draft = text;
                this.submit();
            },

            reset() {
                this.draft = '';
                this.pending = '';
                this.streaming = false;
                this.hasAnswer = false;
            },
        }));
    </script>
    @endscript
</div>
