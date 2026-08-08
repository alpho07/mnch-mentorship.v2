<?php

namespace App\Services\Rag\Settings;

use App\Models\RagSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RagSettings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $runtime = $this->runtime();
        if (array_key_exists($key, $runtime)) {
            return $runtime[$key];
        }

        return config('rag.'.$key, $default);
    }

    public function put(string $key, mixed $value, string $source = 'manual', ?string $notes = null): RagSetting
    {
        $version = $this->nextVersion();
        $setting = RagSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->wrap($value),
                'version' => $version,
                'source' => $source,
                'notes' => $notes,
            ]
        );

        Cache::forget((string) config('rag.runtime_settings.cache_key', RagSetting::CACHE_KEY));

        return $setting;
    }

    public function corpusVersion(): int
    {
        return (int) $this->get('corpus_version', 0);
    }

    public function bumpCorpusVersion(string $reason = 'corpus changed'): int
    {
        $next = $this->corpusVersion() + 1;
        $this->put('corpus_version', $next, 'system', $reason);

        return $next;
    }

    public function version(): string
    {
        return (string) $this->get('settings_version', 'config');
    }

    private function runtime(): array
    {
        if (! (bool) config('rag.runtime_settings.enabled', true)) {
            return [];
        }

        return Cache::remember(
            (string) config('rag.runtime_settings.cache_key', RagSetting::CACHE_KEY),
            (int) config('rag.runtime_settings.cache_ttl', 60),
            fn (): array => RagSetting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (RagSetting $setting): array => [$setting->key => $this->unwrap($setting->value)])
                ->all()
        );
    }

    private function nextVersion(): string
    {
        return now()->format('YmdHis').'-'.Str::lower(Str::random(6));
    }

    private function wrap(mixed $value): array
    {
        return ['value' => $value];
    }

    private function unwrap(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }
}
