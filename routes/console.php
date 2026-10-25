<?php

use Illuminate\Support\Facades\Schedule;

// Prosessflyten: start neste steg når det er ledig kapasitet. Jobbene ber
// også om ny planlegging når de blir ferdige, så dette er et sikkerhetsnett.
Schedule::command('flyt:planlegg')->everyMinute()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
