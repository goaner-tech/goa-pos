<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Server = 'server';
    case Bartender = 'bartender';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';

    /**
     * Managers and admins can approve voids, comps and menu changes.
     */
    public function canManage(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    /**
     * Kitchen staff only work the ticket screens, never the till.
     */
    public function canTakeOrders(): bool
    {
        return $this !== self::Kitchen;
    }
}
