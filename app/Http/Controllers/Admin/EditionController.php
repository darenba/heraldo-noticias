<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditionRequest;
use App\Models\Edition;
use App\Services\EditionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditionController extends Controller
{
    public function __construct(private EditionService $editionService) {}

    public function index(Request $request): View
    {
        $query = Edition::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $editions = $query->paginate(20);

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
                ->with('success', "Edición '{$edition->filename}' subida y puesta en cola de procesamiento.");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['file' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    public function show(Edition $edition): View
    {
        $edition->load('extractionJobs');
        $job = $edition->extractionJobs()->latest()->first();

        return view('admin.editions.show', compact('edition', 'job'));
    }

    public function destroy(Edition $edition): RedirectResponse
    {
        if ($edition->isProcessing()) {
            return back()->withErrors(['edition' => 'No se puede eliminar una edición que está en proceso.']);
        }

        $filename = $edition->filename;
        $edition->delete(); // Articles cascade via DB constraint

        return redirect()
            ->route('admin.editions.index')
            ->with('success', "Edición '{$filename}' eliminada correctamente.");
    }
}
