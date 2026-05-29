# Deep Agents Chat

A small Laravel chat application that demonstrates the
[`twdnhfr/laravel-deepagents`](https://github.com/twdnhfr/laravel-deepagents)
package — a deep-agent harness for the Laravel AI SDK with planning, sub-agents,
persistent memory, human-in-the-loop approval and automatic context management.

It's a single-page chat that shows the package's key features working together
in a real request/response flow.

## What it demonstrates

- **Multi-turn conversations** — the agent run state is persisted in the session
  and continued on each message (`DeepAgent::continue()`).
- **Tools** — four demo tools the model can call: `get_weather`, `roll_dice`,
  `delete_records` and `fetch_report` (see `app/Tools`).
- **Human-in-the-loop approval** — the destructive `delete_records` tool is gated
  with `requireApproval()`; the run suspends and the UI asks you to approve or
  reject before it executes.
- **Persistent artifacts & offloading** — large tool outputs are clipped with
  `offloadLargeToolResults()` and stored as artifacts via the `DatabaseBackend`,
  so the model can read the full text back on demand with `read_artifact`.
- **Tool trace** — each reply shows which tools ran with their arguments and
  results.

The wiring lives almost entirely in [`routes/web.php`](routes/web.php), which is
heavily commented as the reference for how to use the package.

## Requirements

- PHP 8.3+
- Composer
- [Bun](https://bun.sh) (or npm — adjust the commands accordingly)
- An [OpenRouter](https://openrouter.ai) API key

## Setup

```bash
git clone https://github.com/twdnhfr/deepagents-chat.git
cd deepagents-chat
make install        # .env, dependencies, app key, SQLite DB, migrations
```

Then add your OpenRouter key to `.env`:

```env
OPENROUTER_API_KEY=sk-or-...
# OPENROUTER_MODEL=openai/gpt-5.4-nano   # optional, this is the default
```

Start the dev server (Laravel + Vite):

```bash
make dev
```

and open the URL it prints (e.g. http://localhost:8000).

> The demo uses SQLite out of the box for zero configuration. To use MySQL or
> Postgres instead, change the `DB_*` values in `.env`.

<details>
<summary>Manual setup without <code>make</code></summary>

```bash
cp .env.example .env
composer install
bun install
bun run build
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve   # in one terminal
bun run dev         # in another
```
</details>

## Tests

```bash
make test           # or: php artisan test
```

## License

The MIT License (MIT). See [LICENSE](LICENSE).
