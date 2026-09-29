<?php

namespace App\Domains\Sms\Http\Controllers;

use App\Domains\Sms\Http\Requests\UpdateSimCardRequest;
use App\Domains\Sms\Http\Resources\SimCardResource;
use App\Domains\Sms\Services\SmsBoxService;
use App\Http\Controllers\Controller;
use App\Models\SimCard;

class SimCardController extends Controller
{
    public function __construct(private readonly SmsBoxService $smsBox) {}

    public function update(UpdateSimCardRequest $request, SimCard $simCard): SimCardResource
    {
        return new SimCardResource($this->smsBox->updateSimCard($simCard, $request->validated()));
    }
}
