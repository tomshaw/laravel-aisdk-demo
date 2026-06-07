<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\Conversation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Conversation> $conversations
 */
new class extends Component
{
    /**
     * The id of the (seeded) demo user this chat runs as.
     */
    public int $userId;

    /**
     * The active conversation id, used to highlight the current entry.
     */
    public ?string $activeId = null;

    /**
     * Start a fresh conversation and tell the main panel to clear its thread.
     */
    public function newConversation(): void
    {
        $this->activeId = null;

        $this->dispatch('conversation-changed', id: null);
    }

    /**
     * Switch to an existing conversation and tell the main panel to load it.
     */
    public function selectConversation(string $conversationId): void
    {
        $this->activeId = $conversationId;

        $this->dispatch('conversation-changed', id: $conversationId);
    }

    /**
     * Delete a conversation. If it was the active one, clear the main panel
     * back to a fresh conversation.
     */
    public function deleteConversation(string $conversationId): void
    {
        User::findOrFail($this->userId)
            ->conversations()
            ->whereKey($conversationId)
            ->delete();

        if ($this->activeId === $conversationId) {
            $this->activeId = null;

            $this->dispatch('conversation-changed', id: null);
        }

        unset($this->conversations);
    }

    /**
     * After the main panel persists a turn, refresh the history list and
     * highlight the (possibly brand-new) active conversation.
     */
    #[On('conversation-updated')]
    public function syncActive(?string $id): void
    {
        $this->activeId = $id;

        unset($this->conversations);
    }

    /**
     * The demo user's conversations, most recently active first.
     *
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return User::findOrFail($this->userId)
            ->conversations()
            ->latest('updated_at')
            ->get();
    }
}; ?>

<aside
    x-cloak
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-border bg-surface/80 backdrop-blur-xl transition-transform duration-300 md:static md:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex items-center gap-2.5 px-5 pt-6 pb-4">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent text-accent-fg shadow-sm">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z"/></svg>
        </span>
        <div class="leading-tight">
            <p class="font-display text-[15px] font-semibold">AI SDK Chat</p>
            <p class="text-[11px] text-muted">Laravel AI · ReAct agent</p>
        </div>
    </div>

    <div class="px-3">
        <button
            type="button"
            wire:click="newConversation"
            @click="sidebarOpen = false"
            class="group flex w-full cursor-pointer items-center justify-between rounded-xl border border-border bg-bg/40 px-3.5 py-2.5 text-sm font-medium transition hover:border-accent hover:bg-accent-soft/50"
        >
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                New conversation
            </span>
            <kbd class="rounded border border-border px-1.5 text-[10px] text-muted">⏎</kbd>
        </button>
    </div>

    <nav class="mt-4 flex-1 space-y-0.5 overflow-y-auto scroll-soft px-3 pb-4">
        <p class="px-2 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted">History</p>

        @forelse ($this->conversations as $conversation)
            <div
                wire:key="conv-{{ $conversation->id }}"
                @class([
                    'group flex w-full items-center gap-1 rounded-lg text-sm transition',
                    'bg-accent-soft text-ink' => $activeId === $conversation->id,
                    'text-muted hover:bg-surface-2 hover:text-ink' => $activeId !== $conversation->id,
                ])
            >
                <button
                    type="button"
                    wire:click="selectConversation('{{ $conversation->id }}')"
                    @click="sidebarOpen = false"
                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 px-2.5 py-2 text-left"
                >
                    <svg class="h-3.5 w-3.5 shrink-0 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span class="truncate">{{ $conversation->title }}</span>
                </button>
                <button
                    type="button"
                    wire:click="deleteConversation('{{ $conversation->id }}')"
                    wire:confirm="Delete this conversation?"
                    aria-label="Delete conversation"
                    class="mr-2 grid h-6 w-6 shrink-0 cursor-pointer place-items-center rounded-md text-muted opacity-0 transition hover:bg-red-500/10 hover:text-red-500 focus-visible:opacity-100 group-hover:opacity-100"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
            </div>
        @empty
            <p class="px-2.5 py-2 text-sm text-muted">No conversations yet.</p>
        @endforelse
    </nav>

    <div class="border-t border-border px-5 py-3 text-[11px] text-muted">
        Powered by <span class="font-medium text-ink">laravel/ai</span>
    </div>
</aside>
