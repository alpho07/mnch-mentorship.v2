<?php

namespace App\Services\Rag;

class Deadline
{
    private int $startNs;

    public function __construct(
        private readonly int $totalMs,
        private readonly int $reserveMs,
    ) {
        $this->startNs = hrtime(true);
    }

    public function elapsedMs(): int
    {
        return (int) round((hrtime(true) - $this->startNs) / 1_000_000);
    }

    public function remainingMs(): int
    {
        return max(0, $this->totalMs - $this->elapsedMs());
    }

    public function allows(int $estimateMs): bool
    {
        return ($this->remainingMs() - $this->reserveMs) >= $estimateMs;
    }
}
