<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\JsonResponse;

class JobStatusController extends Controller
{
    public function index(Edition $edition): JsonResponse
    {
        $job = $edition->extractionJobs()->latest()->first();

        return response()->json([
            'status' => $job?->status ?? $edition->status,
            'page_current' => $job?->page_current,
            'page_total' => $job?->page_total,
            'articles_extracted' => $job?->articles_extracted ?? 0,
            'errors' => $job?->errors ?? [],
        ]);
    }
}
