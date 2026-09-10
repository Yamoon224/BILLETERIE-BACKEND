<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles et permissions de la plateforme.
 *
 * Quatre roles, calques sur les quatre acteurs du cahier des charges, et une
 * regle de separation des taches qui merite d'etre explicitee :
 *
 * **Celui qui encaisse n'est pas celui qui rembourse.** L'agent de guichet
 * vend, imprime et scanne ; il ne peut ni annuler un depart, ni rembourser un
 * encaissement, ni consulter la recette globale. Le gestionnaire, lui,
 * programme, rembourse et pilote — mais ne tient pas la caisse. Sans cette
 * separation, un meme compte pourrait encaisser en especes puis effacer la
 * trace de l'encaissement, et aucun controle de caisse ne le verrait.
 *
 * Les roles sont **cumulables** : dans une petite compagnie ou le gestionnaire
 * tient lui-meme le guichet, on lui attribue les deux roles. C'est une
 * decision explicite, prise compte par compte, et non un effet de bord d'une
 * matrice trop permissive.
 *
 * Ce seeder est la **source de verite** des droits : ils ne se modifient pas en
 * production. Une matrice editable a chaud est une matrice dont personne ne
 * sait plus dire, six mois plus tard, pourquoi elle est dans cet etat.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission => raison d'etre.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'network.view' => 'Consulter le referentiel : gares, vehicules, itineraires.',
        'network.manage' => 'Declarer et modifier gares, vehicules et itineraires.',
        'trips.view' => 'Consulter les departs programmes.',
        'trips.manage' => 'Programmer, modifier et annuler des departs.',
        'sales.create' => 'Vendre au guichet et remonter les ventes hors ligne.',
        'bookings.view' => 'Consulter les reservations.',
        'tickets.view' => 'Consulter les billets et les reimprimer.',
        'tickets.validate' => 'Scanner les billets a l embarquement.',
        'payments.view' => 'Consulter les encaissements.',
        'payments.refund' => 'Rembourser un encaissement.',
        'reports.view' => 'Consulter le suivi d activite et exporter les donnees.',
        'users.view' => 'Consulter les comptes.',
        'users.manage' => 'Creer, modifier et desactiver des comptes.',
        'audit.view' => 'Consulter le journal d audit.',
        'platform.manage' => 'Administrer les compagnies partenaires et le referentiel des villes.',
    ];

    /**
     * Role => permissions.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        // Supervision globale : le seul role qui traverse les compagnies.
        'platform_admin' => ['*'],

        // Pilote sa compagnie. Ne vend pas au guichet : c'est lui qui
        // rembourse et qui annule, et cumuler les deux avec la tenue de caisse
        // supprimerait tout controle croise.
        'company_manager' => [
            'network.view', 'network.manage',
            'trips.view', 'trips.manage',
            'bookings.view', 'tickets.view',
            'payments.view', 'payments.refund',
            'reports.view',
            'users.view', 'users.manage',
            'audit.view',
        ],

        // Guichet et embarquement. Voit les encaissements pour rapprocher sa
        // caisse, mais ne peut pas en annuler un.
        'agent' => [
            'sales.create',
            'bookings.view',
            'tickets.view', 'tickets.validate',
            'trips.view',
            'payments.view',
        ],

        // Voyageur : aucune permission de back-office. Ses reservations lui
        // sont accessibles parce qu'il en est le titulaire, verification faite
        // par le controleur — pas parce qu'un droit global le lui permettrait.
        'passenger' => [],
    ];

    public function run(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                $permissions === ['*'] ? array_keys(self::PERMISSIONS) : $permissions,
            );
        }

        // Le cache du paquet garde l'ancienne matrice : sans ce vidage, les
        // droits fraichement semes ne s'appliquent qu'a la requete suivante —
        // et un test qui seme puis appelle echouerait sans raison visible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
