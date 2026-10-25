<?php

namespace App\Enums;

enum Temastatus: string
{
    case Foreslatt = 'foreslatt';
    case Godkjent = 'godkjent';
    case Analysert = 'analysert';
    case Publisert = 'publisert';
}
