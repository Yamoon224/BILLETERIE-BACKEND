<?php

namespace App\Domains\CarRental\Enums;

enum TransmissionType: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuelle',
            self::Automatic => 'Automatique',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
