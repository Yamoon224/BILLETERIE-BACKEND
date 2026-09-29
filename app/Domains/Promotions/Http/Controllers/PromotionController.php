<?php

namespace App\Domains\Promotions\Http\Controllers;

use App\Domains\Promotions\Http\Requests\StorePromotionRequest;
use App\Domains\Promotions\Http\Requests\UpdatePromotionRequest;
use App\Domains\Promotions\Http\Resources\PromotionResource;
use App\Domains\Promotions\Services\PromotionService;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Bannieres et tuiles « A la une » de la page d'accueil.
 *
 * Reserve a l'administrateur de plateforme (permissions `promotions.*`) : ce
 * n'est ni la vitrine d'une compagnie ni celle d'un partenaire, mais celle de
 * la plateforme elle-meme.
 */
class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PromotionResource::collection($this->promotions->list(
            $request->only('zone', 'kind', 'is_active', 'sort', 'direction'),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        return (new PromotionResource($this->promotions->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($this->promotions->find($promotion->id));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        return new PromotionResource($this->promotions->update($promotion, $request->validated()));
    }

    public function destroy(Promotion $promotion): Response
    {
        $this->promotions->delete($promotion);

        return response()->noContent();
    }
}
