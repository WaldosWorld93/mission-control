<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $agent = app('agent');

        $projects = $agent->projects()
            ->where('status', ProjectStatus::Active)
            ->get();

        return response()->json(['data' => $projects]);
    }
}
