<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Ask your links" — natural-language analytics.
 *
 * Safety property: the model NEVER writes SQL. It only maps a free-text
 * question onto an allowlist of metrics, dimensions and a day range; we then
 * execute one of a fixed set of rollup queries and (optionally) ask the model
 * to narrate the resulting aggregate numbers. The model only ever sees
 * summarised figures, never the database.
 */
class NlAnalytics
{
    public const METRICS = ['clicks', 'uniques', 'bots'];

    public const DIMENSIONS = ['none', 'country', 'device', 'os', 'browser', 'referer', 'language'];

    public function __construct(
        private ClaudeClient $claude,
        private AnalyticsService $svc,
    ) {}

    /**
     * @return array{understood:bool, answer:string, intent:array<string,mixed>, data:array<string,mixed>}
     */
    public function answer(User $user, string $question): array
    {
        $intent = $this->parse($question);

        if (! ($intent['understood'] ?? false)) {
            return [
                'understood' => false,
                'answer' => "I couldn't map that to your link analytics. Try asking about clicks, unique visitors or bot traffic, optionally broken down by country, device, browser, OS, referrer or language, over a number of days. For example: \"top countries in the last 7 days\".",
                'intent' => $intent,
                'data' => [],
            ];
        }

        $range = max(1, min(365, (int) ($intent['range_days'] ?? 30)));
        $topN = max(1, min(25, (int) ($intent['top_n'] ?? 8)));
        $metric = in_array($intent['metric'] ?? '', self::METRICS, true) ? $intent['metric'] : 'clicks';
        $dimension = in_array($intent['dimension'] ?? '', self::DIMENSIONS, true) ? $intent['dimension'] : 'none';

        $to = Carbon::today();
        $from = $to->copy()->subDays($range - 1);
        $scope = fn ($q) => $q->whereIn('link_id', DB::table('links')->where('user_id', $user->id)->select('id'));

        if ($dimension === 'none') {
            $totals = $this->svc->totals($scope, $from, $to);
            $data = [
                'kind' => 'total',
                'metric' => $metric,
                'value' => (int) ($totals[$metric] ?? 0),
                'range_days' => $range,
            ];
        } else {
            // Dimension rollups are click-counted only, so a breakdown is by clicks.
            $rows = $this->svc->dimensions($scope, $from, $to, $topN)[$dimension] ?? [];
            $data = [
                'kind' => 'breakdown',
                'metric' => 'clicks',
                'dimension' => $dimension,
                'range_days' => $range,
                'rows' => $rows,
            ];
        }

        return [
            'understood' => true,
            'answer' => $this->narrate($question, $data),
            'intent' => ['metric' => $metric, 'dimension' => $dimension, 'range_days' => $range, 'top_n' => $topN],
            'data' => $data,
        ];
    }

    /** @return array<string, mixed> */
    private function parse(string $question): array
    {
        $metrics = implode(', ', self::METRICS);
        $dimensions = implode(', ', self::DIMENSIONS);

        $system = <<<SYS
        You translate a question about a user's link-click analytics into a fixed query plan.
        You do NOT answer the question and you do NOT write any code or SQL. You only choose
        values from these allowlists:

        - metric: one of [{$metrics}]. Use "clicks" unless the user clearly asks about unique
          visitors ("uniques") or bot/automated traffic ("bots").
        - dimension: one of [{$dimensions}]. Use "none" for an overall total. Use "referer" for
          "referrers"/"sources"/"where traffic came from". Use the closest match otherwise.
        - range_days: how many days back to look (integer). Map "last week" to 7, "last month"
          to 30, "this year" to 365, etc. Default to 30 when unspecified.
        - top_n: how many rows for a breakdown (integer, default 8). Ignored for dimension "none".
        - understood: false if the question is not about link-click analytics; otherwise true.
        SYS;

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'metric' => ['type' => 'string', 'enum' => self::METRICS],
                'dimension' => ['type' => 'string', 'enum' => self::DIMENSIONS],
                'range_days' => ['type' => 'integer'],
                'top_n' => ['type' => 'integer'],
                'understood' => ['type' => 'boolean'],
            ],
            'required' => ['metric', 'dimension', 'range_days', 'top_n', 'understood'],
        ];

        // Cache the parse (the intent only, never the answer): it is a deterministic
        // mapping of a question to a query plan, so repeated and example questions
        // skip the model call. The narration below always runs on fresh figures.
        return Cache::remember(
            'ai:intent:'.sha1(mb_strtolower(trim($question))),
            now()->addDay(),
            fn () => $this->claude->structured($system, $question, $schema, 300),
        );
    }

    /** Ask the model to phrase the computed figures as a short answer. */
    private function narrate(string $question, array $data): string
    {
        $system = 'You are a concise analytics assistant. Answer the question in 1 to 2 plain '
            ."sentences using ONLY the figures provided as JSON. Never invent numbers. Don't use em dashes.";

        $prompt = 'Question: '.$question.PHP_EOL.PHP_EOL
            .'Figures (JSON): '.json_encode($data);

        try {
            $answer = $this->claude->text($system, $prompt, 300);
        } catch (\Throwable) {
            $answer = '';
        }

        return $answer !== '' ? $answer : $this->fallbackAnswer($data);
    }

    /** Deterministic phrasing if the narration call is unavailable. */
    private function fallbackAnswer(array $data): string
    {
        $days = (int) ($data['range_days'] ?? 30);

        if (($data['kind'] ?? '') === 'total') {
            $label = ['clicks' => 'clicks', 'uniques' => 'unique visitors', 'bots' => 'bot clicks'][$data['metric']] ?? 'clicks';

            return number_format((int) $data['value'])." {$label} in the last {$days} days.";
        }

        $rows = $data['rows'] ?? [];
        if (empty($rows)) {
            return "No data for that breakdown in the last {$days} days yet.";
        }

        $top = $rows[0];

        return "Top {$data['dimension']}: {$top['label']} with ".number_format((int) $top['clicks'])." clicks (last {$days} days).";
    }
}
