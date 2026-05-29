<?php

namespace App\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetWeather implements Tool
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get the current weather for a city.';
    }

    public function handle(Request $request): string
    {
        $city = trim((string) ($request['city'] ?? '')) ?: 'somewhere';

        // Deterministic fake weather so the demo is reproducible.
        $conditions = ['sunny', 'light rain', 'overcast', 'partly cloudy', 'foggy'];
        $temperature = (crc32($city) % 22) + 6; // 6–27°C
        $condition = $conditions[crc32($city) % count($conditions)];

        return "{$temperature}°C and {$condition} in {$city}";
    }

    public function schema(JsonSchema $schema): array
    {
        return ['city' => $schema->string()->description('The city name.')->required()];
    }
}
