<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Edition;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalEditions = Edition::count();
        $totalArticles = Article::count();
        $processingCount = Edition::where('status', 'processing')->orWhere('status', 'pending')->count();
        $errorCount = Edition::where('status', 'error')->count();
        $recentEditions = Edition::latest()->take(10)->get();

        return view('admin.dashboard', compact(
            'totalEditions',
            'totalArticles',
            'processingCount',
            'errorCount',
            'recentEditions'
        ));
    }
}
