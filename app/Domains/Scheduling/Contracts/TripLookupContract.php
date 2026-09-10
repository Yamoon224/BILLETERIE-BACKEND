<?php

namespace App\Domains\Scheduling\Contracts;

use App\Models\Trip;

/**
 * Lecture d'un depart, exposee par Scheduling aux domaines qui vendent.
 *
 * Segregation deliberee : la vente a besoin de lire un depart — son tarif, son
 * plan de salle, son statut — et n'a aucune raison de pouvoir le reprogrammer,
 * changer son vehicule ou l'annuler. Un service de reservation capable
 * d'annuler un depart est un service qu'il faudra relire a chaque revue de
 * securite, et une erreur de code qui deviendrait un incident d'exploitation.
 *
 * Le depot complet (`TripRepositoryContract`) reste reserve au domaine
 * Scheduling et a ses controleurs.
 */
interface TripLookupContract
{
    public function findOrFail(string $id): Trip;

    /**
     * Charge un depart en le verrouillant jusqu'a la fin de la transaction.
     *
     * Sert de point de serialisation pour les ventes concurrentes sur un meme
     * depart. L'unicite des places est garantie par l'index en base, mais le
     * verrou evite que deux ventes simultanees calculent leur disponibilite
     * sur le meme etat pour echouer ensuite toutes les deux : la seconde voit
     * l'etat reel et choisit d'autres places.
     */
    public function lockForUpdate(string $id): ?Trip;
}
