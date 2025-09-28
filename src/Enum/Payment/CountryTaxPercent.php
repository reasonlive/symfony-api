<?php

namespace App\Enum\Payment;

enum CountryTaxPercent: int
{
    case GERMANY = 19;
    case ITALY = 22;
    case GREECE = 24;
    case FRANCE = 20;

    public static function get(string $country): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === strtoupper($country)) {
                return $case;
            }
        }

        return null;
    }
}