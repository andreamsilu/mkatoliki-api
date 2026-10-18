<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatholicDailyReadings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DailyReadingsController extends Controller
{
    public function __invoke(CatholicDailyReadings $readings): JsonResponse
    {
        $data = $readings->today();

        return $data === null
            ? ApiResponse::error('DAILY_READINGS_UNAVAILABLE', 'Daily reading references are temporarily unavailable.', 503)
            : ApiResponse::success($data);
    }
}
