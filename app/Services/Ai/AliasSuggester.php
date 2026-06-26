<?php

namespace App\Services\Ai;

use App\Services\Linking\AliasGenerator;

/**
 * Suggests short, brandable, memorable aliases for a destination URL using
 * Claude, then filters the candidates down to ones that are actually free and
 * valid for the given domain (so every suggestion shown is clickable).
 */
class AliasSuggester
{
    public function __construct(
        private ClaudeClient $claude,
        private AliasGenerator $aliases,
    ) {}

    /**
     * @return list<string> available, validated alias candidates
     */
    public function suggest(string $url, ?string $title, int $domainId, int $count = 6): array
    {
        $system = <<<'SYS'
        You name short links. Given a destination URL and optional title, propose concise,
        brandable, human-readable alias slugs (the path segment after the domain).

        Rules for every slug:
        - lowercase letters, digits and hyphens only; no spaces, no leading/trailing hyphen
        - 3 to 24 characters
        - meaningful and memorable (relate to the brand, product or campaign), not random
        - distinct from one another
        SYS;

        $prompt = "Destination URL: {$url}".PHP_EOL
            .'Title: '.($title !== null && $title !== '' ? $title : '(none)').PHP_EOL.PHP_EOL
            .'Propose up to 10 candidate slugs.';

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'aliases' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['aliases'],
        ];

        $result = $this->claude->structured($system, $prompt, $schema, 512);

        $out = [];
        foreach ((array) ($result['aliases'] ?? []) as $candidate) {
            $slug = $this->normalise((string) $candidate);
            if ($slug === '' || in_array($slug, $out, true)) {
                continue;
            }
            // Only surface slugs that are genuinely free + valid for this domain.
            if ($this->aliases->validateCustom($slug, $domainId) === null) {
                $out[] = $slug;
            }
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    private function normalise(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return strlen($slug) >= 3 && strlen($slug) <= 24 ? $slug : '';
    }
}
