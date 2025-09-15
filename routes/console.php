<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule workspace cleanup to run daily at 2 AM
Schedule::command('workspace:cleanup')->dailyAt('02:00');

// Schedule GDevelop preview cleanup to run every hour
Schedule::command('gdevelop:cleanup-previews --force')->hourly();
