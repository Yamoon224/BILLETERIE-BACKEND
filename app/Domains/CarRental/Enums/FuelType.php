<?php

namespace App\Domains\CarRental\Enums;

enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case Electric = 'electric';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Essence',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybride',
            self::Electric => 'Electrique',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
