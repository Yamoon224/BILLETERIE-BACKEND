<?php

namespace App\Domains\Ticketing\Contracts;

/**
 * Lecture etroite des places occupees, exposee par Ticketing aux domaines qui
 * vendent (Booking) et qui affichent (Scheduling).
 *
 * C'est une segregation d'interface deliberee. La recherche de departs et le
 * selecteur de place ont besoin d'une seule information — quelles places sont
 * prises — et n'ont aucune raison de recevoir un depot de billets complet,
 * capable d'emettre, d'annuler et de scanner. Un service de recherche qui peut
 * annuler un billet est un service de recherche qu'il faudra relire a chaque
 * revue de securite.
 *
 * L'implementation vit dans Ticketing : c'est ce domaine qui sait ce
 * qu'« occupee » veut dire — un billet emis ou deja utilise, jamais un billet
 * annule dont la place est retournee a la vente.
 */
interface OccupiedSeatReaderContract
{
    /**
     * Numeros de places prises sur ce depart.
     *
     * @return list<string>
     */
    public function occupiedSeatNumbers(string $tripId): array;

    /**
     * Nombre de places prises, par depart.
     *
     * Prend une liste plutot qu'un identifiant : une page de resultats de
     * recherche affiche vingt departs, et vingt requetes la ou une suffit se
     * paient cash sur un reseau mobile.
     *
     * @param  list<string>  $tripIds
     * @return array<string, int> identifiant de depart vers nombre de places prises
     */
    public function occupiedSeatCounts(array $tripIds): array;
}
