<?php

namespace App\Services\Ai;

/**
 * Generates link-in-bio page copy (display name, headline, bio) from a short
 * topic or description the user types in the bio builder.
 */
class BioCopywriter
{
    public function __construct(private ClaudeClient $claude) {}

    /** @return array{display_name:string, headline:string, bio:string} */
    public function write(string $topic): array
    {
        $system = 'You write short, friendly copy for a link-in-bio profile page. Given a topic or '
            .'description, return a display name, a one-line headline, and a 1 to 2 sentence bio. Keep '
            .'it warm, concrete and concise. Do not use em dashes or hashtags. Return only the JSON.';

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'display_name' => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'bio' => ['type' => 'string'],
            ],
            'required' => ['display_name', 'headline', 'bio'],
        ];

        $out = $this->claude->structured($system, 'Topic: '.$topic, $schema, 220);

        return [
            'display_name' => mb_substr(trim((string) ($out['display_name'] ?? '')), 0, 80),
            'headline' => mb_substr(trim((string) ($out['headline'] ?? '')), 0, 120),
            'bio' => mb_substr(trim((string) ($out['bio'] ?? '')), 0, 280),
        ];
    }
}
