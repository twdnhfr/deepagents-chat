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

$buildAgent = fn (): DeepAgent => DeepAgent::make()
    ->provider('openrouter')
    ->model(env('OPENROUTER_MODEL', 'openai/gpt-5.4-nano'))
    ->instructions('You are a concise, friendly assistant in a demo for the laravel-deepagents package. '.
        'Tools: get_weather (weather), roll_dice (dice), delete_records (deleting old records), '.
        'fetch_report (a long report — its output is offloaded; use read_artifact to read details). '.
        'Use them when relevant, then answer in a sentence or two.')
    ->backend(new DatabaseBackend) // persistent: artifacts survive across the conversation
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

Route::get('/', function () {
    // A fresh page load starts a fresh conversation.
    session()->forget(['deepagents_chat', 'deepagents_pending']);

    return view('welcome');
});

Route::post('/chat', function (Request $request) use ($buildAgent, $trace) {
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

    // Paused before a gated tool: the suspended run is the conversation's single
    // pending approval, stored server-side in the session under a one-time token.
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
                fn ($call) => ['name' => $call['name'], 'arguments' => $call['arguments']],
                $state->pendingToolCalls,
            ),
        ]);
    }

    // No-progress run stopped by guardAgainstLoops(): surface the reason instead
    // of an empty reply. The halted state is serializable; continue() can resume it.
    if ($state->isHalted()) {
        session(['deepagents_chat' => $state->toJson()]);

        return response()->json([
            'status' => 'halted',
            'reply' => '🛑 '.$state->haltReason,
            'tools' => $trace(array_slice($state->history, $turnStart)),
        ]);
    }

    session(['deepagents_chat' => $state->toJson()]);

    return response()->json([
        'status' => 'done',
        'reply' => $state->finalText ?? '(no reply)',
        'tools' => $trace(array_slice($state->history, $turnStart)),
    ]);
});

Route::post('/chat/approve', function (Request $request) use ($buildAgent, $trace) {
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

    if (! $approved) {
        return response()->json(['status' => 'rejected', 'reply' => "Okay — I won't run that."]);
    }

    $state = $buildAgent()->resume(RunState::fromJson($pending['state']));
    session(['deepagents_chat' => $state->toJson()]);

    if ($state->isHalted()) {
        return response()->json([
            'status' => 'halted',
            'reply' => '🛑 '.$state->haltReason,
            'tools' => $trace(array_slice($state->history, $pending['from'])),
        ]);
    }

    return response()->json([
        'status' => 'done',
        'reply' => $state->finalText ?? '(no reply)',
        'tools' => $trace(array_slice($state->history, $pending['from'])),
    ]);
});
