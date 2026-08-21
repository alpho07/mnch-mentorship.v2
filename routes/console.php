<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mentorships:auto-close')->dailyAt('00:05');
Schedule::command('mentorships:send-stall-reminders')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('rag:lexicon')->dailyAt('02:40')->withoutOverlapping();
Schedule::command('db:backup:check')->everyFiveMinutes()->withoutOverlapping();
