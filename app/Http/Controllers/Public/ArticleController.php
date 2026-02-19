<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $articleService) {}

    public function index(Request $request): View
    {
        $query = $request->get('q', '');
        $filters = $request->only(['section', 'date_from', 'date_to', 'tag']);

        try {
            $articles = $this->articleService->search($query, $filters);
            $sections = $this->articleService->getSections();
            $dbError = false;
        } catch (\Exception $e) {
            $articles = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $sections = [];
            $dbError = true;
        }

        return view('public.index', compact('articles', 'sections', 'query', 'filters', 'dbError'));
    }

    public function show(Article $article): View
    {
        $article->load(['tags', 'edition']);
        $related = $this->articleService->findRelated($article);

        return view('public.show', compact('article', 'related'));
    }

    public function byTag(string $tag): RedirectResponse
    {
        return redirect()->route('articles.index', ['tag' => $tag]);
    }
}
