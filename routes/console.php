<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily()->withoutOverlapping();
