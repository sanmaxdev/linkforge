<?php

namespace App\Services\Ai;

/**
 * Generates a concise title and short description for a link from its
 * destination URL (and any existing title). URL-only: no page fetch, so there
 * is no SSRF surface and the call stays fast.
 */
class TitleWriter
{
    public function __construct(private ClaudeClient $claude) {}

    /** @return array{title:string, description:string} */
    public function write(string $url, ?string $existingTitle = null): array
    {
        $system = 'You write concise, click-worthy titles and short descriptions for shared links. '
            .'Base them on the destination URL (its domain and path keywords) and any existing title. '
            .'The title must be under 70 characters and the description under 160. Be specific and '
            .'natural, never clickbait. Do not use em dashes. Return only the JSON.';

        $prompt = 'Destination URL: '.$url
            .($existingTitle ? PHP_EOL.'Existing title: '.$existingTitle : '');

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
            'required' => ['title', 'description'],
        ];

        $out = $this->claude->structured($system, $prompt, $schema, 200);

        return [
            'title' => mb_substr(trim((string) ($out['title'] ?? '')), 0, 120),
            'description' => mb_substr(trim((string) ($out['description'] ?? '')), 0, 240),
        ];
    }
}
