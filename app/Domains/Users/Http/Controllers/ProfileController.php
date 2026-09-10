<?php

namespace App\Domains\Users\Http\Controllers;

use App\Domains\Auth\Http\Resources\AuthenticatedUserResource;
use App\Domains\Users\Http\Requests\UpdatePasswordRequest;
use App\Domains\Users\Http\Requests\UpdateProfileRequest;
use App\Domains\Users\Services\ProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compte de l'utilisateur connecte.
 *
 * Aucune permission n'est exigee sur ces routes : elles n'agissent que sur
 * l'appelant lui-meme, et la garantie tient a cela — pas a un controle
 * d'identifiant qu'il faudrait ecrire, et donc qu'on pourrait oublier.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profile) {}

    public function update(UpdateProfileRequest $request): AuthenticatedUserResource
    {
        $user = $this->profile->update($request->user(), $request->validated());

        return new AuthenticatedUserResource($user->load('company'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profile->updatePassword(
            $request->user(),
            (string) $request->input('current_password'),
            (string) $request->input('password'),
        );

        return response()->json([
            'data' => ['message' => 'Mot de passe mis a jour.'],
        ]);
    }

    /**
     * Revoque tous les jetons du compte sauf celui utilise pour l'appel.
     *
     * Utile apres la perte d'une tablette de guichet : l'agent se deconnecte
     * partout ailleurs sans perdre la session depuis laquelle il agit.
     */
    public function revokeOtherTokens(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $revoked = $request->user()->tokens()
            ->where('id', '!=', $current->id)
            ->delete();

        return response()->json([
            'data' => ['revoked' => $revoked],
        ]);
    }
}
