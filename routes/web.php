<?php

use App\Tools\DeleteRecords;
use App\Tools\FetchReport;
use App\Tools\GetWeather;
use App\Tools\RollDice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Twdnhfr\LaravelDeepagents\Backends\DatabaseBackend;
use Twdnhfr\LaravelDeepagents\DeepAgent;
use Twdnhfr\LaravelDeepagents\Runtime\RunState;

/**
 * A sub-agent for the `task` tool: runs in its own isolated context window and
 * returns only its final text, keeping the report's tokens out of the main
 * conversation. It has no backend of its own, so it inherits the parent's
 * DatabaseBackend at delegation time (shared artifact store). Sub-agents must
 * run autonomously — gate the *delegation* on the parent, not the sub-agent.
 */
$buildAnalyst = fn (): DeepAgent => DeepAgent::make()
    ->provider('openrouter')
    ->model(env('OPENROUTER_MODEL', 'openai/gpt-5.4-nano'))
    ->instructions('You are a report analyst. Fetch the report on the requested topic with fetch_report, '.
        'read offloaded details via read_artifact where needed, and answer the question in 2-3 '.
        'precise sentences. Your reply goes back to another agent — no greetings, just the findings.')
    ->tool(new FetchReport)
    ->offloadLargeToolResults(800)
    ->validateToolArgs()
    ->maxTurns(8);

$buildAgent = fn (): DeepAgent => DeepAgent::make()
    ->provider('openrouter')
    ->model(env('OPENROUTER_MODEL', 'openai/gpt-5.4-nano'))
    ->instructions('You are a concise, friendly assistant in a demo for the laravel-deepagents package. '.
        'Tools: get_weather (weather), roll_dice (dice), delete_records (deleting old records), '.
        'fetch_report (a long report — its output is offloaded; use read_artifact to read details). '.
        'For a task with several steps, first record a plan with write_todos and keep it updated as '.
        'you work (in_progress before a step, completed after). '.
        'For analyzing or comparing reports, delegate to the report-analyst sub-agent via the task tool '.
        'instead of reading the reports yourself. '.
        'You have persistent memory: whenever the user asks you to remember something or shares a '.
        'lasting fact/preference about themselves, you MUST call write_artifact with path '.
        '"memory/profile.md" and the COMPLETE updated profile as content (the file is replaced; '.
        'merge in anything already known from the memory section of this prompt). That file is '.
        'loaded into your context when a new conversation starts — without the write, the fact is '.
        'lost on reload. '.
        'Use them when relevant, then answer in a sentence or two.')
    ->backend(new DatabaseBackend) // persistent: artifacts survive across the conversation
    ->memory('memory/profile.md') // loaded into the system prompt at run() — survives page reloads,
                                  // unlike the conversation history, which a reload resets
    ->withTodos() // planning: the model keeps a todo list on the RunState, shown live in the UI
    ->subAgent(
        'report-analyst',
        'Fetches the report on a topic and answers a focused question about it. Pass a complete, '.
        'standalone task (topic + question + expected output).',
        $buildAnalyst(),
    )
    ->tool(new GetWeather)
    ->tool(new RollDice)
    ->tool(new DeleteRecords)
    ->tool(new FetchReport)
    ->offloadLargeToolResults(800) // clip big tool outputs; auto-adds read_artifact
    ->withArtifacts()
    ->validateToolArgs()      // check tool arguments against each tool's schema; bad calls get a correction, not a crash
    ->guardAgainstLoops()     // stop a no-progress run (same tool call repeated) instead of churning to maxTurns
    ->requireApproval(['delete_records']); // gate only the destructive tool

/** Extract executed tool calls from a slice of run history, for the UI trace. */
$trace = fn (array $history): array => collect($history)
    ->where('role', 'tool_result')
    ->flatMap(fn ($entry) => $entry['toolResults'])
    ->map(fn ($result) => [
        'name' => $result['name'],
        'arguments' => $result['arguments'],
        'result' => $result['result'],
    ])
    ->values()
    ->all();

/**
 * Turn a RunState into the JSON response + session bookkeeping all routes share:
 * suspended → an approval card (state parked under a one-time token),
 * halted → the guard's reason, done → the reply with its tool trace.
 */
$respondFromState = function (RunState $state, int $turnStart) use ($trace) {
    if ($state->isSuspended()) {
        $token = (string) Str::uuid();
        session(['deepagents_pending' => [
            'token' => $token,
            'state' => $state->toJson(),
            'from' => $turnStart,
        ]]);

        return response()->json([
            'status' => 'approval',
            'token' => $token,
            'pending' => array_map(
                fn ($call) => ['id' => $call['id'], 'name' => $call['name'], 'arguments' => $call['arguments']],
                $state->pendingToolCalls,
            ),
            'todos' => $state->todos,
        ]);
    }

    session(['deepagents_chat' => $state->toJson()]);

    if ($state->isHalted()) {
        return response()->json([
            'status' => 'halted',
            'reply' => '🛑 '.$state->haltReason,
            'tools' => $trace(array_slice($state->history, $turnStart)),
            'todos' => $state->todos,
        ]);
    }

    return response()->json([
        'status' => 'done',
        'reply' => $state->finalText ?? '(no reply)',
        'tools' => $trace(array_slice($state->history, $turnStart)),
        'todos' => $state->todos,
    ]);
};

Route::get('/', function () {
    // A fresh page load starts a fresh conversation.
    session()->forget(['deepagents_chat', 'deepagents_pending']);

    return view('welcome');
});

Route::post('/chat', function (Request $request) use ($buildAgent, $respondFromState) {
    $message = trim((string) $request->input('message', ''));

    if ($message === '') {
        return response()->json(['status' => 'done', 'reply' => 'Please type a message first 🙂', 'tools' => []]);
    }

    // A new message supersedes any approval still pending from a previous turn:
    // a conversation only follows one path, so old approvals become invalid.
    session()->forget('deepagents_pending');

    $agent = $buildAgent();
    $stored = session('deepagents_chat');

    if ($stored) {
        $previous = RunState::fromJson($stored);
        $turnStart = count($previous->history);
        $state = $agent->continue($previous, $message);
    } else {
        $turnStart = 0;
        $state = $agent->run($message);
    }

    return $respondFromState($state, $turnStart);
});

Route::post('/chat/approve', function (Request $request) use ($buildAgent, $respondFromState) {
    $token = (string) $request->input('token', '');
    $approved = (bool) $request->input('approve', false);

    $pending = session('deepagents_pending');

    // Only the conversation's current pending approval is valid. Approving an
    // older, superseded card (its token no longer matches) is refused.
    if (! is_array($pending) || ($pending['token'] ?? null) !== $token) {
        return response()->json([
            'status' => 'stale',
            'reply' => '⌛ That approval is no longer valid — a newer message or action superseded it.',
        ]);
    }

    session()->forget('deepagents_pending');

    $state = RunState::fromJson($pending['state']);

    // Approval is the default, so untouched calls need nothing. Edited calls go
    // through RunState::edit(): the human-corrected arguments replace the
    // model's before the call runs (the third per-call decision next to
    // approve/reject).
    if ($approved) {
        $validIds = array_column($state->pendingToolCalls, 'id');

        foreach ((array) $request->input('edits', []) as $edit) {
            if (is_array($edit)
                && in_array($edit['id'] ?? null, $validIds, true)
                && is_array($edit['arguments'] ?? null)) {
                $state->edit($edit['id'], $edit['arguments']);
            }
        }
    }

    // A rejection is a per-call decision, not a dead end: reject() records the
    // human's reason on the pending call, and resume() hands it to the model as
    // the tool's result — so it reacts in-conversation instead of the turn being
    // silently dropped.
    if (! $approved) {
        $reason = trim((string) $request->input('reason', ''))
            ?: 'The user declined this action in the chat.';

        foreach ($state->pendingToolCalls as $call) {
            $state->reject($call['id'], $reason);
        }
    }

    return $respondFromState($buildAgent()->resume($state), $pending['from']);
});
