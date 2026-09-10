<?php

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Contracts\AuditLogRepositoryContract;
use App\Domains\Audit\Http\Resources\AuditLogResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogRepositoryContract $logs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AuditLogResource::collection($this->logs->paginate(
            $request->only('subject_type', 'subject_id', 'event', 'causer_id', 'from', 'to', 'search'),
            $request->integer('per_page', 25),
        ));
    }

    /** Valeurs disponibles dans les filtres du journal. */
    public function facets(): JsonResponse
    {
        return response()->json(['data' => $this->logs->facets()]);
    }
}
