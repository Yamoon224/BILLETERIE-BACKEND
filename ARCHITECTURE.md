# Architecture du backend

API REST Laravel 13, MySQL 8, authentification Sanctum par jeton, documentation
OpenAPI servie par Swagger UI.

## Organisation par domaine metier

```
app/Domains/{Domaine}/
    Contracts/       Interfaces (depots, lectures etroites, prestataires) — les frontieres du domaine
    Enums/           Vocabulaire metier type, avec libelles et regles associees
    DTOs/            Objets immuables echanges entre couches
    Repositories/    Implementations Eloquent des contrats
    Services/        Logique applicative et orchestration
    Exceptions/      Erreurs metier portant leur code HTTP et leur code applicatif
    Support/         Outils propres au domaine (signature, rendu QR, plan de salle)
    Http/
        Controllers/ Controleurs minces : recoivent, font valider, delèguent, repondent
        Requests/    FormRequests : validation des entrees
        Resources/   JsonResource : forme des reponses
```

| Domaine | Responsabilite |
|---|---|
| `Shared` | Exception metier racine, montants (`Money`), references publiques, tri, perimetre de compagnie, sante, documentation |
| `Auth` | Connexion, inscription voyageur, deconnexion |
| `Users` | Comptes, profil, roles |
| `Network` | Compagnies, villes, gares, vehicules et plan de salle, itineraires |
| `Partners` | Proprietaires et agences partenaires (appartements, location auto) |
| `Housing` | Catalogue d'appartements meubles |
| `CarRental` | Catalogue de vehicules de location courte duree |
| `Scheduling` | Departs : programmation, cycle de vie, recherche voyageur |
| `Booking` | Reservation en ligne, vente au guichet, synchronisation hors ligne, expiration |
| `Ticketing` | Emission, signature, QR code, controle a l'embarquement |
| `Payments` | Encaissements, agregateur mobile money, rappels signes |
| `Notifications` | Envoi du billet par SMS, journal des envois |
| `Reporting` | Tableau de bord et exports CSV |
| `Audit` | Journal d'audit en lecture seule |

Les modeles Eloquent restent dans `app/Models/` : ils sont partages entre
domaines (un billet est lu par `Booking`, `Ticketing` et `Reporting`), et les
dupliquer creerait des dependances croisees pires que le partage.

## Inversion des dependances et segregation des interfaces

`app/Providers/DomainServiceProvider.php` est le point unique de cablage entre
contrats et implementations. Aucun service ne reference une classe concrete de
persistance ou de prestataire.

Deux lectures etroites portent la segregation d'interfaces :

- **`TripLookupContract`** (expose par `Scheduling`) : la vente lit un depart,
  mais ne peut ni le reprogrammer ni l'annuler.
- **`OccupiedSeatReaderContract`** (expose par `Ticketing`) : la recherche et la
  vente comptent les places prises, sans recevoir de quoi emettre ou annuler un
  billet.

Le tableau de bord ne dispose que de `SalesReportReaderContract`, en lecture
seule.

## Les garanties du domaine

### Une place n'est jamais vendue deux fois

Garantie **par la base** : index unique `tickets(trip_id, seat_number)`. Une
verification applicative prealable produit un message utile (« les places 12A et
12B ne sont plus disponibles ») ; la violation d'index, rattrapee, produit la
garantie en cas de course. Un billet annule vide `seat_number` et recopie la
valeur dans `released_seat_number` : les NULL ne se heurtent pas dans un index
unique, ce qui libere la place sans recourir a un index partiel que MySQL ne sait
pas exprimer.

### Un billet scanne une fois est refuse au second scan

`TicketValidationService` decide **sous verrou de ligne** (`lockForUpdate`) :
deux tablettes presentant le meme billet a la meme seconde sont serialisees. Un
rejeu hors ligne du **meme geste** (meme `client_reference`) est reconnu comme tel
et n'est pas compte comme une fraude.

### Les billets sont verifiables hors ligne

Le QR code est autoportant : `B1|CODE|DEPART|PLACE|HORODATAGE|VERSION|SIGNATURE`.
La signature HMAC-SHA256 (tronquee a 128 bits pour rester lisible sur ticket
thermique) utilise une cle **distincte de `APP_KEY`**, versionnee pour permettre
une rotation sans invalider les billets en circulation. Elle prouve l'authenticite
du billet ; la non-reutilisation, elle, se verifie en base ou sur la liste
d'embarquement locale.

### La synchronisation hors ligne est correcte

Chaque vente hors ligne porte une `client_reference` attribuee par la tablette
avant l'envoi (index unique en base). Un lot rejoue ne cree aucun doublon ; un lot
n'est pas atomique (une vente en conflit ne fait pas perdre les autres) ; chaque
refus revient a la tablette avec son `error_code`. L'heure reelle de la vente
(`sold_offline_at`) est distincte de l'heure de synchronisation.

### Aucune donnee de paiement n'est stockee

`payments` conserve montant, moyen, statut et reference externe. Les rappels de
l'agregateur sont authentifies par HMAC calcule **sur le corps brut** ; un rappel
rejoue ne reecrit pas un encaissement deja tranche.

## Montants

Francs CFA en **entiers**, partout. La commission plateforme est exprimee en pour
mille, figee a la vente avec son taux, et arrondie au franc le plus proche
(`Money::commission`).

## Copies figees

Un depart recopie a sa creation le tarif de l'itineraire, la capacite et le plan
de salle du vehicule. Reviser un tarif ou changer de car ne reecrit jamais ce qu'un
voyageur a deja achete. Des la premiere vente, le tarif et le vehicule d'un depart
ne sont plus modifiables.

## Roles et separation des taches

| Role | Perimetre |
|---|---|
| `platform_admin` | Toutes les compagnies, tous les partenaires, referentiel des villes, commissions |
| `company_manager` | Sa compagnie : reseau, departs, remboursements, suivi, comptes |
| `agent` | Guichet, embarquement, consultation des encaissements |
| `partner_manager` | Son partenaire : fiche, appartements, vehicules de location |
| `passenger` | Ses propres reservations |

**Celui qui encaisse n'est pas celui qui rembourse** : l'agent n'a pas
`payments.refund`, le gestionnaire n'a pas `sales.create`. Les roles sont
cumulables par decision explicite. Le perimetre de compagnie
(`Shared\Support\CompanyScope`) est deduit du jeton, **jamais** de la requete.

## Gestion des erreurs

Toutes les erreurs metier heritent de `Shared\Exceptions\DomainException` et
portent un code HTTP et un `error_code` stable. Le rendu JSON est centralise dans
`bootstrap/app.php` ; les controleurs n'interceptent jamais d'exception. Un refus
de scan, lui, n'est pas une exception : c'est une reponse metier ordinaire
(`ScanResult`) rendue avec un code HTTP significatif.

## Base de donnees

MySQL 8, y compris pour les tests : ce projet repose sur une contrainte d'unicite
et sur le verrouillage de lignes, que SQLite ne reproduit pas fidelement. La base
de test est distincte (`phpunit.xml`).

## Documentation de l'API

Specification ecrite en amont dans `resources/openapi/openapi.yaml`, servie sur
`/docs` (Swagger UI, assets locaux) et `/docs/openapi.json`.
`tests/Feature/Documentation/OpenApiSpecificationTest.php` echoue si une route
n'est pas documentee, si une operation documentee n'existe plus, ou si un enum
documente diverge de l'enum PHP.

## Taches planifiees

`bookings:expire`, chaque minute, sans chevauchement : libere les places des
reservations en ligne non payees dans le delai (`TICKETING_HOLD_MINUTES`).
