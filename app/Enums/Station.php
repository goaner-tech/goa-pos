<?php

namespace App\Enums;

/**
 * Where an item is prepared. Each station gets its own ticket screen.
 */
enum Station: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Barista = 'barista';
}
