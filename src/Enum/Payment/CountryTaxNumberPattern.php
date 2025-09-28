<?php

namespace App\Enum\Payment;

enum CountryTaxNumberPattern: string
{
    case GERMANY = '/^DE\d{9}$/';
    case ITALY = '/^IT\d{11}$/';
    case GREECE = '/^GR\d{9}$/';
    case FRANCE = '/^FR[A-Z]{2}\d{9}$/';

    public static function getCountry(string $value): ?string
    {
        $country = null;
        foreach (self::cases() as $case) {
            if (preg_match($case->value, $value)) {
                $country = $case->name;
                break;
            }
        }

        return $country;
    }
}