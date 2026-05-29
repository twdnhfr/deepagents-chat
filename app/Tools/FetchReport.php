<?php

namespace App\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class FetchReport implements Tool
{
    public function name(): string
    {
        return 'fetch_report';
    }

    public function description(): string
    {
        return 'Fetch a long, detailed report document for a topic. Returns a lot of text.';
    }

    public function handle(Request $request): string
    {
        $topic = trim((string) ($request['topic'] ?? '')) ?: 'the topic';

        // A deliberately large output, so the offloading hook clips it and stores
        // the full text as an artifact the model can read back on demand.
        $report = "# Report: {$topic}\n\n";

        for ($section = 1; $section <= 8; $section++) {
            $report .= "## Section {$section}\n";
            $report .= str_repeat("Detailed finding {$section} about {$topic}, with supporting context and figures. ", 6);
            $report .= "\n\n";
        }

        return $report;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['topic' => $schema->string()->description('The report topic.')->required()];
    }
}
