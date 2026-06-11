<?php

use Illuminate\Support\Facades\Schedule;

// Delete guest endpoints past their retention window (default 2 days).
// Hourly so data disappears close to the promised deadline, not up to a day late.
Schedule::command('endpoints:expire')->hourly();
