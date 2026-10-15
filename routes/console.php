<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily()->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\ContentPublishing::class)->publishDue())->name('content:publish-due')->everyMinute()->withoutOverlapping();
