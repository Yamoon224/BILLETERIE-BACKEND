<?php

namespace App\Domains\Sms\Http\Controllers;

use App\Domains\Sms\Http\Resources\SmsDispatchResource;
use App\Domains\Sms\Services\SmsBoxService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmsQueueController extends Controller
{
    public function __construct(private readonly SmsBoxService $smsBox) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SmsDispatchResource::collection(
            $this->smsBox->queue($request->only('status', 'sort', 'direction'), $request->integer('per_page', 15)),
        );
    }
}
