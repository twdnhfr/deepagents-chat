<?php

namespace App\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RollDice implements Tool
{
    public function name(): string
    {
        return 'roll_dice';
    }

    public function description(): string
    {
        return 'Roll a die with the given number of sides and return the result.';
    }

    public function handle(Request $request): string
    {
        $sides = max(2, (int) ($request['sides'] ?? 6));
        $result = random_int(1, $sides);

        return "Rolled a {$result} on a {$sides}-sided die.";
    }

    public function schema(JsonSchema $schema): array
    {
        return ['sides' => $schema->integer()->description('Number of sides on the die (e.g. 6 or 20).')->required()];
    }
}
