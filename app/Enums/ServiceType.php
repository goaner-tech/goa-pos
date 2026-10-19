<?php

namespace App\Enums;

/**
 * One order model covers all three venue styles.
 */
enum ServiceType: string
{
    case Table = 'table';     // restaurant: pay last
    case Tab = 'tab';         // bar: pay when leaving
    case Counter = 'counter'; // cafe: pay first
}
