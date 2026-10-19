<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Voided = 'voided';
}
