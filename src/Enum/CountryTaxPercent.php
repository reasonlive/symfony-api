<?php

namespace App\Enum;

enum CountryTaxPercent: int
{
    case GERMANY = 19;
    case ITALY = 22;
    case GREECE = 24;
    case FRANCE = 20;

    public static function getPercent(string $country): int
    {
        $value = 0;
        foreach (self::cases() as $case) {
            if ($case->name === $country) {
                $value = $case->value;
                break;
            }
        }

        return $value;
    }
}