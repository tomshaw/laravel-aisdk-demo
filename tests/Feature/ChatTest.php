<?php

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders the sidebar and the main empty state', function () {
    Livewire::test('pages::chat')
        ->assertOk()
        ->assertSee('AI SDK Chat')
        ->assertSee('Ask me')
        ->assertSee('Claude Sonnet 4.6');
});

it('streams an answer and persists the conversation', function () {
    ChatAgent::fake(['Hello from the agent!']);

    $component = Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', 'Hi there')
        ->assertSee('Hi there')
        ->assertSee('Hello from the agent!')
        ->assertDispatched('conversation-updated');

    expect($component->get('conversationId'))->not->toBeNull();

    ChatAgent::assertPrompted('Hi there');

    expect(ConversationMessage::where('role', 'user')->count())->toBe(1)
        ->and(ConversationMessage::where('role', 'assistant')->count())->toBe(1);
});

it('ignores blank prompts', function () {
    ChatAgent::fake(['should not be used']);

    Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', '   ')
        ->assertSet('conversationId', null)
        ->assertNotDispatched('conversation-updated');

    ChatAgent::assertNotPrompted('should not be used');
});

it('clears the thread when the sidebar changes conversation', function () {
    ChatAgent::fake(['First answer.']);

    Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', 'First question')
        ->assertSee('First answer.')
        ->dispatch('conversation-changed', id: null)
        ->assertSet('conversationId', null)
        ->assertDontSee('First answer.');
});

it('dispatches conversation-changed when starting a new conversation', function () {
    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->set('activeId', 'some-id')
        ->call('newConversation')
        ->assertSet('activeId', null)
        ->assertDispatched('conversation-changed', id: null);
});

it('dispatches conversation-changed when selecting a conversation', function () {
    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->call('selectConversation', 'conv-123')
        ->assertSet('activeId', 'conv-123')
        ->assertDispatched('conversation-changed', id: 'conv-123');
});

it('deletes a conversation from the sidebar', function () {
    ChatAgent::fake(['An answer.']);

    $conversationId = Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', 'A question')
        ->get('conversationId');

    expect(Conversation::count())->toBe(1);

    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->call('deleteConversation', $conversationId);

    expect(Conversation::count())->toBe(0);
});

it('clears the active conversation when deleting it', function () {
    ChatAgent::fake(['An answer.']);

    $conversationId = Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', 'A question')
        ->get('conversationId');

    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->set('activeId', $conversationId)
        ->call('deleteConversation', $conversationId)
        ->assertSet('activeId', null)
        ->assertDispatched('conversation-changed', id: null);
});

it('does not delete another user\'s conversation', function () {
    ChatAgent::fake(['An answer.']);

    $otherUser = User::factory()->create();

    $conversationId = Livewire::test('chat.main', ['userId' => $otherUser->id])
        ->call('send', 'A question')
        ->get('conversationId');

    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->call('deleteConversation', $conversationId);

    expect(Conversation::count())->toBe(1);
});

it('lists past conversations and highlights the active one', function () {
    ChatAgent::fake(['An answer about Laravel.']);

    $main = Livewire::test('chat.main', ['userId' => $this->user->id])
        ->call('send', 'Tell me about Laravel');

    expect(Conversation::count())->toBe(1);

    $conversationId = $main->get('conversationId');

    Livewire::test('chat.sidebar', ['userId' => $this->user->id])
        ->dispatch('conversation-updated', id: $conversationId)
        ->assertSet('activeId', $conversationId)
        ->assertSee('bg-accent-soft');
});
