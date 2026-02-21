<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SoulController extends Controller
{
    public function show(): JsonResponse
    {
        $agent = app('agent');

        return response()->json([
            'data' => [
                'soul_md' => $agent->soul_md,
                'soul_hash' => $agent->soul_hash,
            ],
        ]);
    }
}
