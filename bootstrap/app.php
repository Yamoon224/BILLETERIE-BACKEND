<?php

use App\Domains\Shared\Exceptions\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // API pure, sans page de login web : ne jamais tenter de rediriger un
        // invite vers une route nommee `login` qui n'existe pas (sinon 500 au
        // lieu du 401 attendu).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Gestion centralisee des erreurs : un seul endroit decide de la forme
         * d'une reponse d'erreur, pour que tous les endpoints repondent de la
         * meme maniere et que les controleurs restent minces.
         *
         * Contrat de reponse d'erreur :
         *   { "message": string, "error_code": string, "context": object }
         * enrichi de "errors" pour les erreurs de validation (422).
         *
         * Ce contrat compte doublement ici : l'application agent tourne sur une
         * tablette en reseau instable et doit pouvoir distinguer, sans lire un
         * message francais, « place deja vendue » de « billet deja scanne » de
         * « reseau perdu ». C'est le role de `error_code`.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La validation garde la forme native de Laravel (`errors` par champ),
        // enrichie du code applicatif pour que le client n'ait qu'une seule
        // cle a tester pour router une erreur.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'validation_failed',
                'errors' => $exception->errors(),
                'context' => (object) [],
            ], 422);
        });

        // Les exceptions metier portent elles-memes leur code HTTP et leur code
        // applicatif : les controleurs n'ont jamais a les intercepter.
        $exceptions->render(function (DomainException $exception, Request $request) {
            return $request->is('api/*') || $request->expectsJson()
                ? $exception->render()
                : null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Non authentifie — reconnectez-vous.',
                'error_code' => 'unauthenticated',
                'context' => (object) [],
            ], 401);
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => "Vous n'avez pas les droits necessaires pour cette action.",
                'error_code' => 'forbidden',
                'context' => (object) ['required_permissions' => $exception->getRequiredPermissions()],
            ], 403);
        });

        // Un modele introuvable est un 404 metier, pas une fuite de nom de
        // classe Eloquent vers le client.
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });

        // Le guichet retente automatiquement ses envois differes : sans delai
        // explicite, l'application agent reessaierait en boucle serree et
        // s'auto-bloquerait sur un reseau deja fragile.
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Trop de tentatives. Patientez avant de reessayer.',
                'error_code' => 'too_many_requests',
                'context' => (object) ['retry_after_seconds' => (int) $exception->getHeaders()['Retry-After'] ?? null],
            ], 429, $exception->getHeaders());
        });
    })->create();
