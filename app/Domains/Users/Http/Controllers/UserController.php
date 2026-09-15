<?php

namespace App\Domains\Users\Http\Controllers;

use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Domains\Users\Http\Requests\StoreUserRequest;
use App\Domains\Users\Http\Requests\UpdateUserRequest;
use App\Domains\Users\Http\Resources\UserResource;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Administration des comptes.
 *
 * Un gestionnaire de compagnie administre les comptes de sa compagnie — ses
 * agents de guichet, essentiellement. L'administrateur plateforme administre
 * tout le monde. Deux roles ne sont jamais attribuables par un gestionnaire :
 * `platform_admin`, qui donnerait acces a toutes les compagnies, et
 * `passenger`, qui n'a rien a faire dans un back-office.
 */
class UserController extends Controller
{
    /** Roles qu'un gestionnaire de compagnie peut attribuer. */
    private const COMPANY_ASSIGNABLE_ROLES = ['company_manager', 'agent'];

    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->list(
            CompanyScope::apply(
                $request->only('search', 'role', 'is_active', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->only('name', 'email', 'phone', 'password', 'company_id', 'partner_id', 'is_active');
        /** @var list<string> $roles */
        $roles = $request->input('roles');

        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            $data['company_id'] = $scope;
            $this->guardAssignableRoles($roles);
        }

        return (new UserResource($this->users->create($data, $roles)->load(['roles', 'company'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->authorizeUser($request, $user);

        return new UserResource($this->users->find($user->id));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorizeUser($request, $user);

        $data = $request->safe()->only('name', 'email', 'phone', 'password', 'company_id', 'partner_id', 'is_active');
        /** @var list<string>|null $roles */
        $roles = $request->input('roles');

        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            // Un gestionnaire ne deplace pas un compte vers une autre
            // compagnie, meme par erreur de formulaire.
            $data['company_id'] = $scope;

            if ($roles !== null) {
                $this->guardAssignableRoles($roles);
            }
        }

        return new UserResource(
            $this->users->update($user, $data, $roles)->load(['roles', 'company']),
        );
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->authorizeUser($request, $user);
        $this->users->delete($user, $request->user()?->id);

        return response()->noContent();
    }

    private function authorizeUser(Request $request, User $user): void
    {
        if (! CompanyScope::allows($request->user(), $user->company_id)) {
            throw CompanyScopeViolationException::make();
        }
    }

    /** @param  list<string>  $roles */
    private function guardAssignableRoles(array $roles): void
    {
        if (array_diff($roles, self::COMPANY_ASSIGNABLE_ROLES) !== []) {
            throw CompanyScopeViolationException::make();
        }
    }
}
