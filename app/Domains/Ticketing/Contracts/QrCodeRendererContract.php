<?php

namespace App\Domains\Ticketing\Contracts;

/**
 * Rendu graphique d'un QR code.
 *
 * Separe de la signature a dessein : signer un billet et le dessiner sont deux
 * responsabilites sans rapport, et le service d'emission n'a besoin que de la
 * premiere. Le rendu, lui, sert a l'affichage web (SVG) et a l'impression
 * thermique au guichet (PNG monochrome).
 */
interface QrCodeRendererContract
{
    /** QR code vectoriel, pour l'ecran et l'impression laser. */
    public function toSvg(string $content, int $size = 320): string;

    /**
     * QR code matriciel, pour une imprimante thermique Bluetooth.
     *
     * Ces imprimantes ne connaissent ni SVG ni niveaux de gris : elles
     * impriment des points noirs sur 384 ou 576 points de large. Le PNG est
     * donc rendu sans marge superflue, a une taille multiple de la resolution
     * cible pour eviter un reechantillonnage qui rendrait le code illisible.
     */
    public function toPng(string $content, int $size = 384): string;
}
