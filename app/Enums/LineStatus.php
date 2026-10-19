<?php

namespace App\Enums;

enum LineStatus: string
{
    case New = 'new';       // on the order, not yet sent to a station
    case Fired = 'fired';   // on a station's ticket screen
    case Ready = 'ready';   // bumped by the station
    case Voided = 'voided';
}
