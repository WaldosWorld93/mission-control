<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoulController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        return response()->json([
            'data' => [
                'soul_md' => $agent->soul_md,
                'soul_hash' => $agent->soul_hash,
            ],
        ]);
    }
}
