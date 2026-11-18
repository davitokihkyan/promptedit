<?php

use Illuminate\Support\Facades\Schedule;

// The lock expires after 5 minutes, so a killed run can't block reconciliation for a day.
Schedule::command('generations:reconcile')->everyMinute()->withoutOverlapping(5);
