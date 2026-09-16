<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A simple key/value store for global, singleton app settings (not tied to
 * any one record) — e.g. toggling a whole creation flow on or off. For
 * per-record flags (like a single Program's is_active), use that model's
 * own column instead; this is only for app-wide switches.
 */
class Setting extends Model
{
    public const NEW_MENTORSHIP_BUTTON_ENABLED = 'new_mentorship_button_enabled';

    public const GUIDED_SETUP_BUTTON_ENABLED = 'guided_setup_button_enabled';

    public const CHAT_SETUP_BUTTON_ENABLED = 'chat_setup_button_enabled';

    public const MNCHGPT_BUTTON_ENABLED = 'mnchgpt_button_enabled';

    public const QUICK_SETUP_BUTTON_ENABLED = 'quick_setup_button_enabled';

    /**
     * Master switch for per-user Program Scope (EmONC / Newborn Care /
     * Infant & Child Care / Both) — see User::allowedProgramIds(). When off,
     * no user's program_scope value has any effect regardless of what it's
     * set to.
     */
    public const PROGRAM_SCOPING_ENABLED = 'program_scoping_enabled';

    public const BACKUP_SCHEDULE_ENABLED = 'backup_schedule_enabled';

    public const BACKUP_SCHEDULE_FREQUENCY = 'backup_schedule_frequency';

    public const BACKUP_SCHEDULE_TIME = 'backup_schedule_time';

    public const BACKUP_SCHEDULE_DAY_OF_WEEK = 'backup_schedule_day_of_week';

    public const BACKUP_SCHEDULE_DAY_OF_MONTH = 'backup_schedule_day_of_month';

    public const BACKUP_RETENTION_COUNT = 'backup_retention_count';

    public const BACKUP_LAST_SCHEDULED_RUN_AT = 'backup_last_scheduled_run_at';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "setting:{$key}",
            fn () => static::where('key', $key)->value('value') ?? $default
        );
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $value = static::get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    public static function setBool(string $key, bool $value): void
    {
        static::set($key, $value ? '1' : '0');
    }
}
