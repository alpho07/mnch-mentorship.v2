<?php

namespace App\Services\Rag;

class SentenceGate
{
    private string $buffer = '';

    public function push(string $delta): array
    {
        $this->buffer .= $delta;
        $out = [];

        while (preg_match('/^(.*?[.!?:](?:\s|$)|.*?\n)/s', $this->buffer, $matches)) {
            $out[] = trim($matches[1]);
            $this->buffer = mb_substr($this->buffer, mb_strlen($matches[1]));
        }

        if (mb_strlen($this->buffer) > 400) {
            $out[] = trim($this->buffer);
            $this->buffer = '';
        }

        return array_values(array_filter($out));
    }

    public function flush(): ?string
    {
        $value = trim($this->buffer);
        $this->buffer = '';

        return $value !== '' ? $value : null;
    }
}
