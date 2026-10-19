<?php

namespace App\Enums;

enum Tender: string
{
    case CashSsp = 'cash_ssp';
    case CashUsd = 'cash_usd';
    case MobileMoney = 'mobile_money';
    case Card = 'card';

    /**
     * The currency the guest hands over. Everything else on the bill is in the venue currency.
     */
    public function currency(): string
    {
        return $this === self::CashUsd ? 'USD' : config('pos.currency');
    }

    public function isCash(): bool
    {
        return $this === self::CashSsp || $this === self::CashUsd;
    }

    /**
     * Mobile money and card payments are checked later against the provider, so they need its transaction reference.
     */
    public function needsReference(): bool
    {
        return $this === self::MobileMoney || $this === self::Card;
    }
}
