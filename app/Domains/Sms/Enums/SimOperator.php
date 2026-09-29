<?php

namespace App\Domains\Sms\Enums;

/** Operateur mobile porteur d'une carte SIM du SMS Box. */
enum SimOperator: string
{
    case Orange = 'orange';
    case Mtn = 'mtn';
    case Moov = 'moov';

    public function label(): string
    {
        return match ($this) {
            self::Orange => 'Orange',
            self::Mtn => 'MTN',
            self::Moov => 'Moov',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
