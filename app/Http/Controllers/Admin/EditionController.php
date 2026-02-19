<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditionRequest;
use App\Models\Edition;
use App\Services\EditionService;
use App\Services\SupabaseRestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EditionController extends Controller
{
    public function __construct(private EditionService $editionService) {}

    public function index(Request $request): View
    {
        try {
            $query = Edition::latest();

            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }

            $editions = $query->paginate(20);
        } catch (\Exception $e) {
            // Fallback to REST API
            $editions = $this->indexViaRest($request);
        }

        return view('admin.editions.index', compact('editions'));
    }

    public function create(): View
    {
        return view('admin.editions.create');
    }

    public function store(StoreEditionRequest $request): RedirectResponse
    {
        try {
            $edition = $this->editionService->createFromUpload(
                $request->file('file'),
                $request->get('publication_date')
            );

            return redirect()
                ->route('admin.editions.show', $edition)
                ->with('success', "Edición '{$edition->filename}' subida. Extracción completada.");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['file' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    public function show(Edition $edition): View
    {
        $job = null;

        try {
            $edition->load('extractionJobs');
            $job = $edition->extractionJobs()->latest()->first();
        } catch (\Exception $e) {
            // REST fallback for job data
            $rest   = new SupabaseRestService();
            $jobRow = $rest->findOne('extraction_jobs', [
                'edition_id' => 'eq.' . $edition->id,
                'order'      => 'created_at.desc',
            ]);

            if ($jobRow) {
                $job = new \App\Models\ExtractionJob();
                $job->setRawAttributes($jobRow);
                $job->exists = true;
            }
        }

        return view('admin.editions.show', compact('edition', 'job'));
    }

    public function destroy(Edition $edition): RedirectResponse
    {
        if ($edition->isProcessing()) {
            return back()->withErrors(['edition' => 'No se puede eliminar una edición que está en proceso.']);
        }

        $filename = $edition->filename;

        try {
            $edition->delete();
        } catch (\Exception $e) {
            // REST fallback for delete
            $rest = new SupabaseRestService();
            $rest->update('editions', ['id' => 'eq.' . $edition->id], ['status' => 'deleted']);
        }

        return redirect()
            ->route('admin.editions.index')
            ->with('success', "Edición '{$filename}' eliminada correctamente.");
    }

    private function indexViaRest(Request $request): LengthAwarePaginator
    {
        $rest   = new SupabaseRestService();
        $params = [
            'select' => 'id,filename,publication_date,newspaper_name,status,total_articles,total_pages,created_at',
            'order'  => 'created_at.desc',
        ];

        if ($request->filled('status')) {
            $params['status'] = 'eq.' . $request->get('status');
        }

        $perPage = 20;
        $page    = (int) $request->get('page', 1);

        $all  = $rest->get('editions', $params);
        $total = count($all);

        $offset = ($page - 1) * $perPage;
        $slice  = array_slice($all, $offset, $perPage);

        $items = collect($slice)->map(function (array $row): Edition {
            $edition = new Edition();
            $edition->setRawAttributes($row);
            $edition->exists = true;
            return $edition;
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
