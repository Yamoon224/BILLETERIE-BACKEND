<?php

namespace App\Domains\Ticketing\Support;

use App\Domains\Ticketing\Contracts\QrCodeRendererContract;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Rendu des QR codes de billets.
 *
 * Le niveau de correction d'erreur est volontairement eleve (`Quartile`, 25 %
 * de redondance). Ce n'est pas un reglage par defaut recopie : un billet de
 * car interurbain est plie dans une poche, imprime sur du papier thermique qui
 * palit, et scanne dans la poussiere d'une gare routiere a la lumiere du jour.
 * Un code lisible seulement intact serait refuse a l'embarquement une fois sur
 * dix, et chaque refus se paie en file d'attente devant la porte du bus.
 *
 * `RoundBlockSizeMode::Margin` garantit que chaque module tombe sur un nombre
 * entier de pixels : sur une imprimante thermique 384 points, un module a
 * 5,3 pixels produit des bords baveux qu'aucune camera ne relit.
 */
final class EndroidQrCodeRenderer implements QrCodeRendererContract
{
    public function toSvg(string $content, int $size = 320): string
    {
        return (new SvgWriter)->write($this->qrCode($content, $size, margin: 8))->getString();
    }

    public function toPng(string $content, int $size = 384): string
    {
        // Marge minimale mais non nulle : la specification du QR impose une
        // zone calme de quatre modules, et l'omettre est la premiere cause de
        // codes illisibles sur ticket imprime bord a bord.
        return (new PngWriter)->write($this->qrCode($content, $size, margin: 16))->getString();
    }

    private function qrCode(string $content, int $size, int $margin): QrCode
    {
        return new QrCode(
            data: $content,
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
    }
}
