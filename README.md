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
- **Planning** — `withTodos()` gives the model the `write_todos` tool; the
  sidebar renders its plan live from `RunState->todos` as steps move through
  pending → in progress → completed.
- **Sub-agents** — a `report-analyst` registered with `subAgent()`: the parent
  delegates report analysis via the `task` tool, the sub-agent works in its own
  isolated context window (inheriting the parent's backend) and only its final
  answer returns to the conversation.
- **Persistent memory** — `memory('memory/profile.md')` loads a profile from the
  backend into the system prompt at the start of every conversation; the agent
  updates it via `write_artifact`. Reload the page (fresh conversation) and ask
  "What do you know about me?" — the history is gone, the memory isn't.
- **Human-in-the-loop with all three decisions** — the destructive
  `delete_records` tool is gated with `requireApproval()`; the run suspends and
  the UI shows the pending call. *Approve* runs it as requested, *edit* the
  arguments inline before approving (`RunState::edit()` — the call runs with
  your corrected input), or *reject* with an optional reason
  (`RunState::reject()`) that goes back to the model as the tool's result, so
  it reacts in-conversation instead of the turn being dropped.
- **Persistent artifacts & offloading** — large tool outputs are clipped with
  `offloadLargeToolResults()` and stored as run-scoped artifacts via the
  `DatabaseBackend`, so the model can read the full text back on demand with
  `read_artifact`.
- **Context compaction** — `summarize()` (with a deliberately low budget here)
  compacts older history into a summary once it grows too large; the UI marks
  the moment with a 🗜️ line, and the conversation keeps its context.
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
