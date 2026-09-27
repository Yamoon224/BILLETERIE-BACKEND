<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\ConfirmTwoFactorRequest;
use App\Domains\Auth\Http\Requests\DisableTwoFactorRequest;
use App\Domains\Auth\Services\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inscription et gestion de la verification en deux etapes du compte
 * connecte.
 *
 * Reservee aux administrateurs de plateforme (voir `routes/api.php`) : c'est
 * la seule population dont un compte compromis ouvre un acces a l'ensemble du
 * systeme plutot qu'a une seule compagnie ou un seul guichet.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['enabled' => $this->twoFactor->isEnabled($request->user())],
        ]);
    }

    /**
     * Demarre (ou redemarre) une inscription : sans effet sur la connexion
     * tant que `confirm` n'a pas ete appelee avec un premier code valide.
     */
    public function enable(Request $request): JsonResponse
    {
        $enrollment = $this->twoFactor->startEnrollment($request->user());

        return response()->json(['data' => $enrollment]);
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactor->confirmEnrollment($request->user(), $request->string('code')->toString());

        return response()->json(['data' => ['enabled' => true]]);
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactor->disable($request->user(), $request->string('password')->toString());

        return response()->json(['data' => ['enabled' => false]]);
    }
}
