<?php

namespace App\Domains\Reporting\Contracts;

use App\Domains\Reporting\DTOs\ReportFilters;

/**
 * Lectures agregees du suivi d'activite.
 *
 * Un contrat de **lecture seule**, distinct des depots de vente : le tableau
 * de bord ne doit disposer d'aucun moyen d'ecrire. Il lit des recettes, des
 * remplissages et des historiques, et rien de ce qu'il fait ne doit pouvoir
 * modifier une reservation.
 *
 * Les methodes rendent des tableaux plutot que des modeles Eloquent : ce sont
 * des agregats, pas des entites, et les faire passer par des modeles
 * inviterait a appeler `save()` sur une ligne de statistique.
 */
interface SalesReportReaderContract
{
    /**
     * Indicateurs de tete : billets vendus, recette, commission, panier moyen.
     *
     * @return array{
     *     bookings: int,
     *     tickets: int,
     *     gross_revenue: int,
     *     commission: int,
     *     net_revenue: int,
     *     average_basket: int,
     *     cancelled_bookings: int,
     *     expired_bookings: int
     * }
     */
    public function summary(ReportFilters $filters): array;

    /**
     * Encaissements par moyen de paiement.
     *
     * Critere de recette explicite du cahier des charges, et surtout le seul
     * moyen pour un chef de gare de rapprocher sa caisse physique de ce que la
     * plateforme a enregistre.
     *
     * @return list<array{method: string, label: string, count: int, amount: int}>
     */
    public function revenueByPaymentMethod(ReportFilters $filters): array;

    /**
     * Ventes par canal (en ligne, guichet, guichet hors ligne).
     *
     * @return list<array{channel: string, label: string, bookings: int, tickets: int, amount: int}>
     */
    public function salesByChannel(ReportFilters $filters): array;

    /**
     * Recette jour par jour, pour la courbe d'activite.
     *
     * @return list<array{date: string, bookings: int, tickets: int, amount: int}>
     */
    public function dailyRevenue(ReportFilters $filters): array;

    /**
     * Taux de remplissage par depart.
     *
     * @return list<array{
     *     trip_id: string,
     *     reference: string,
     *     departs_at: string,
     *     origin: string,
     *     destination: string,
     *     capacity: int,
     *     sold: int,
     *     occupancy_rate: float,
     *     revenue: int
     * }>
     */
    public function occupancyByTrip(ReportFilters $filters, int $limit = 100): array;

    /**
     * Ventes par agent, pour le suivi de caisse d'une gare.
     *
     * @return list<array{user_id: string, name: string, bookings: int, tickets: int, amount: int}>
     */
    public function salesByAgent(ReportFilters $filters): array;
}
