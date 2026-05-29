<?php

namespace App\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteRecords implements Tool
{
    public function name(): string
    {
        return 'delete_records';
    }

    public function description(): string
    {
        return 'Permanently delete records older than the given number of days. This is destructive.';
    }

    public function handle(Request $request): string
    {
        $days = max(0, (int) ($request['days'] ?? 0));

        // Demo only — no real data is touched.
        return "Deleted 12 records older than {$days} days.";
    }

    public function schema(JsonSchema $schema): array
    {
        return ['days' => $schema->integer()->description('Age threshold in days.')->required()];
    }
}
