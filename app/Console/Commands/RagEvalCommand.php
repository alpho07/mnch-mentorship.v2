<?php

namespace App\Console\Commands;

use App\Models\RagEvalCase;
use App\Models\RagEvalRun;
use App\Services\Rag\RagClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagEvalCommand extends Command
{
    protected $signature = 'rag:eval {--cases=frozen : frozen|all} {--json : Output JSON}';

    protected $description = 'Run enabled adaptive RAG evaluation cases.';

    public function handle(RagClient $client): int
    {
        $query = RagEvalCase::query()->where('enabled', true);
        if ($this->option('cases') === 'frozen') {
            $query->where('frozen', true);
        }

        $cases = $query->get();
        $failures = [];
        $latencies = [];

        foreach ($cases as $case) {
            try {
                $response = $client->ask($case->question, (int) config('rag.top_k.default', 5));
                $answer = (string) ($response['answer'] ?? '');
                $latencies[] = (int) ($response['latency_ms'] ?? 0);
                $passed = $this->passes($case, $response, $answer);
            } catch (\Throwable $e) {
                $passed = false;
                $response = ['error' => $e->getMessage()];
                $answer = '';
            }

            if (! $passed) {
                $failures[] = [
                    'case_id' => $case->id,
                    'question' => $case->question,
                    'model' => $response['model'] ?? null,
                    'answer' => Str::limit($answer, 500, ''),
                    'error' => $response['error'] ?? null,
                ];
            }
        }

        $passedCount = $cases->count() - count($failures);
        $latencies = collect($latencies)->sort()->values();
        $run = RagEvalRun::query()->create([
            'label' => 'manual-'.now()->format('YmdHis'),
            'settings' => ['config' => config('rag')],
            'cases_total' => $cases->count(),
            'cases_passed' => $passedCount,
            'accuracy' => $cases->count() > 0 ? round($passedCount / $cases->count(), 4) : 0,
            'latency_p50_ms' => (int) ($latencies[(int) floor(max(0, $latencies->count() - 1) * 0.50)] ?? 0),
            'latency_p95_ms' => (int) ($latencies[(int) floor(max(0, $latencies->count() - 1) * 0.95)] ?? 0),
            'failures' => $failures,
        ]);

        $payload = ['run_id' => $run->id, 'cases' => $cases->count(), 'passed' => $passedCount, 'failures' => $failures];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("RAG eval run {$run->id}: {$passedCount}/{$cases->count()} passed.");
            foreach ($failures as $failure) {
                $this->warn('#'.$failure['case_id'].' '.$failure['question']);
            }
        }

        return count($failures) === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function passes(RagEvalCase $case, array $response, string $answer): bool
    {
        foreach ($case->must_include ?? [] as $needle) {
            if (! Str::contains(Str::lower($answer), Str::lower((string) $needle))) {
                return false;
            }
        }

        foreach ($case->must_not_include ?? [] as $needle) {
            if (Str::contains(Str::lower($answer), Str::lower((string) $needle))) {
                return false;
            }
        }

        if ($case->expected_route && ! Str::contains(Str::lower((string) ($response['model'] ?? '')), Str::lower($case->expected_route))) {
            return false;
        }

        if ($case->require_citations && empty($response['citations'] ?? [])) {
            return false;
        }

        return true;
    }
}
