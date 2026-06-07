<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    /**
     * The id of the (seeded) demo user this chat runs as.
     */
    public int $userId;

    /**
     * Resolve the demo user. This app intentionally has no auth UI; the chat
     * simply runs as a single seeded user so conversation history has an owner.
     */
    public function mount(): void
    {
        $this->userId = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => 'password'],
        )->id;
    }
}; ?>

<div
    x-data="{
        sidebarOpen: false,
        toggleTheme() {
            const root = document.documentElement;
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        },
    }"
    class="flex h-screen overflow-hidden text-ink"
>
    <livewire:chat.sidebar :user-id="$userId" />

    {{-- Mobile sidebar backdrop --}}
    <div
        x-cloak
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        x-transition.opacity
        class="fixed inset-0 z-30 bg-black/30 md:hidden"
    ></div>

    <livewire:chat.main :user-id="$userId" />
</div>
