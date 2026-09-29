<?php

namespace App\Domains\Promotions\Enums;

/**
 * Nature d'une promotion.
 *
 * Distinction commerciale, pas seulement d'affichage : une tuile editoriale
 * met en avant l'offre Kaara elle-meme, une publicite est un espace vendu a
 * un partenaire. Le badge affiche a l'administrateur en depend directement.
 */
enum PromotionKind: string
{
    case Editorial = 'editorial';
    case Advertisement = 'advertisement';

    public function label(): string
    {
        return match ($this) {
            self::Editorial => 'Contenu Kaara (editorial)',
            self::Advertisement => 'Publicite partenaire',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
