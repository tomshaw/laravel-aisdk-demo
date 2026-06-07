# Laravel AI SDK — ReAct Chat Agent Demo

A streaming, tool-using **ReAct** chat assistant built on the
[Laravel AI SDK](https://github.com/laravel/ai) (`laravel/ai`). The agent reasons
about a question, calls tools when it needs outside information, observes the
results, and loops until it can give a clear final answer — all streamed live to
the browser with Livewire.

> 📖 This repository is the companion code for the tutorial
> **[Building a ReAct Chat Agent with the Laravel AI SDK](https://dev.to/tomshaw/building-a-react-chat-agent-with-the-laravel-ai-sdk-4ip6)**.
> Read the tutorial for a step-by-step walkthrough of how everything below was built.

## What it does

- **ReAct loop** — the model reasons, acts (calls a tool), observes the result, and repeats. The SDK runs the loop; `#[MaxSteps]` bounds it.
- **Live streaming** — answers stream token-by-token, rendered as Markdown, with a status line showing which tool is being used.
- **Conversation memory** — each turn is persisted automatically (via the `RemembersConversations` trait) and listed in a sidebar you can switch between and delete.
- **Provider-agnostic** — works with any LLM provider supported by the SDK (Anthropic, OpenAI, Gemini, Groq, Ollama, and more) by changing environment variables only.

### Tools the agent can call

| Tool                | Purpose                                                        |
| ------------------- | ------------------------------------------------------------- |
| `Calculator`        | Exact arithmetic instead of the model guessing.               |
| `CurrentDateTime`   | The current date/time, which a model can't know on its own.   |
| `WikipediaLookup`   | Factual background on a topic, person, or place.              |
| `WebSearch`         | Recent events or anything that changed after training.        |

## Stack

- **PHP** 8.5
- **Laravel** 13
- **Livewire** 4 (single-file components)
- **Laravel AI SDK** (`laravel/ai`)
- **Tailwind CSS** 4
- **Pest** 4 for testing

## Requirements

- PHP 8.5+
- Composer
- Node.js 22+
- An API key for at least one LLM provider

## Getting started

```bash
# 1. Install dependencies
composer install
npm install

# 2. Set up the environment
cp .env.example .env
php artisan key:generate

# 3. Create the database (SQLite by default) and run migrations
touch database/database.sqlite
php artisan migrate

# 4. Add your provider credentials to .env (see "Choosing a provider" below)

# 5. Build assets and start the app
composer run dev
```

`composer run dev` runs the PHP server, queue listener, log viewer (Pail), and
Vite together. Then visit the URL it prints (typically `http://localhost:8000`).

The app intentionally has **no auth UI** — it runs as a single seeded demo user
so conversation history has an owner.

## Choosing a provider

The chat agent is not hardcoded to any provider. Set three variables in `.env`
and add the matching API key:

```ini
AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-6
AI_MODEL_LABEL="Claude Sonnet 4.6"
ANTHROPIC_API_KEY=sk-ant-...
```

To switch providers, change those values and run `php artisan config:clear`.
Every provider is already wired in `config/ai.php`. Examples:

| Provider  | `AI_PROVIDER` | `AI_MODEL`                  | Key env var          |
| --------- | ------------- | --------------------------- | -------------------- |
| Anthropic | `anthropic`   | `claude-sonnet-4-6`         | `ANTHROPIC_API_KEY`  |
| OpenAI    | `openai`      | `gpt-4o`                    | `OPENAI_API_KEY`     |
| Gemini    | `gemini`      | `gemini-2.5-flash`          | `GEMINI_API_KEY`     |
| Groq      | `groq`        | `llama-3.3-70b-versatile`   | `GROQ_API_KEY`       |
| Ollama    | `ollama`      | `llama3.1`                  | _(none — runs local)_ |

> Set `AI_MODEL` explicitly for your chosen provider. With it left blank the SDK
> falls back to the provider's default model, which isn't always defined.

## How it's structured

```
app/Ai/
├── Agents/ChatAgent.php      # The ReAct agent: instructions + tools
├── Enums/ExamplePrompt.php   # Suggested prompts shown on the empty state
└── Tools/                    # Calculator, CurrentDateTime, WikipediaLookup

resources/views/
├── pages/⚡chat.blade.php             # Route entry point (seeds the demo user)
└── components/chat/
    ├── ⚡sidebar.blade.php            # Conversation history list
    └── ⚡main.blade.php               # Chat thread + streaming + input
```

The single route lives in `routes/web.php`:

```php
Route::livewire('/', 'pages::chat')->name('home');
```

## Testing

```bash
php artisan test
```

Tests use the SDK's `ChatAgent::fake()` so they run without hitting a real
provider or needing an API key.

## License

MIT.
