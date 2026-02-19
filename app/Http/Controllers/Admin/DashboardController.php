<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Edition;
use App\Services\SupabaseRestService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        try {
            $totalEditions   = Edition::count();
            $totalArticles   = Article::count();
            $processingCount = Edition::where('status', 'processing')->orWhere('status', 'pending')->count();
            $errorCount      = Edition::where('status', 'error')->count();
            $recentEditions  = Edition::latest()->take(10)->get();
            $dbError = false;
        } catch (\Exception $e) {
            // Native DB failed — try PostgREST fallback
            try {
                $rest = new SupabaseRestService();
                if ($rest->available()) {
                    $totalArticles   = $rest->countArticles();
                    $totalEditions   = $rest->countEditions();
                    $processingCount = $rest->countEditionsByStatuses(['processing', 'pending']);
                    $errorCount      = $rest->countEditionsByStatus('error');
                    $recentEditions  = collect($rest->recentEditions(10))->map(function (array $row): Edition {
                        $ed = new Edition();
                        $ed->setRawAttributes($row);
                        $ed->exists = true;
                        return $ed;
                    });
                    $dbError = false;
                } else {
                    $totalEditions = $totalArticles = $processingCount = $errorCount = 0;
                    $recentEditions = collect([]);
                    $dbError = true;
                }
            } catch (\Exception $e2) {
                $totalEditions = $totalArticles = $processingCount = $errorCount = 0;
                $recentEditions = collect([]);
                $dbError = true;
            }
        }

        return view('admin.dashboard', compact(
            'totalEditions', 'totalArticles', 'processingCount',
            'errorCount', 'recentEditions', 'dbError'
        ));
    }
}
