<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the attendance log archiving to run automatically once a year
// Explicitly scopes archiving only to BUNHS data per client business logic limits
Schedule::command('attendance:archive --school="BUNHS"')->yearlyOn(6, 1, '01:00');
