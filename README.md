# Backend — API de billetterie

API REST Laravel 13 de la billetterie interurbaine. Voir
[`ARCHITECTURE.md`](ARCHITECTURE.md) pour les decisions de conception.

## Installation

```bash
composer run setup        # dependances, .env, APP_KEY, cle de signature, migrations, demo
composer run docs:assets  # Swagger UI local
php artisan serve
php artisan schedule:work # expiration des blocages de places
```

La base `billeterie` (et `billeterie_testing` pour les tests) doit exister :

```sql
CREATE DATABASE billeterie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE billeterie_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Commandes

| Commande | Role |
|---|---|
| `composer run check` | Pint + PHPStan (niveau 6) + suite complete |
| `composer run test` | Tests unitaires et d'integration (MySQL) |
| `php artisan ticketing:signing-key` | Genere la cle de signature des billets (refuse d'ecraser une cle existante) |
| `php artisan bookings:expire` | Libere les places des reservations non payees |

## Configuration metier

| Variable | Defaut | Role |
|---|---|---|
| `TICKETING_HOLD_MINUTES` | `15` | Duree de blocage des places d'une reservation en ligne |
| `TICKETING_SIGNING_KEY` | — | Cle HMAC des QR codes, distincte de `APP_KEY` |
| `TICKETING_KEY_VERSION` | `1` | Version de cle, pour la rotation |
| `TICKETING_COMMISSION_PER_MILLE` | `25` | Commission plateforme par defaut (2,5 %) |
| `PAYMENT_GATEWAY` | `simulated` | Agregateur mobile money |
| `PAYMENT_GATEWAY_WEBHOOK_SECRET` | — | Secret de verification des rappels |
| `NOTIFICATION_DRIVER` | `log` | Envoi des billets par SMS (`log` en local) |

## Documentation

- Swagger UI : <http://localhost:8000/docs>
- Specification : <http://localhost:8000/docs/openapi.json>
- Sante : <http://localhost:8000/api/health>
# BILLETERIE-BACKEND
