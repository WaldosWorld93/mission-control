<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $projects = $agent->projects()
            ->where('status', ProjectStatus::Active)
            ->get();

        return response()->json(['data' => $projects]);
    }
}
