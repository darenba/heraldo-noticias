@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
    <p class="text-slate-500 text-sm mt-1">Resumen del archivo digital de El Heraldo</p>
</div>

{{-- Metric cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
        <div class="text-3xl font-bold text-slate-800">{{ number_format($totalEditions) }}</div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Ediciones</div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
        <div class="text-3xl font-bold text-slate-800">{{ number_format($totalArticles) }}</div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Artículos</div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-500">
        <div class="text-3xl font-bold text-slate-800">{{ $processingCount }}</div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">En Proceso</div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-500">
        <div class="text-3xl font-bold text-slate-800">{{ $errorCount }}</div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Con Error</div>
    </div>
</div>

{{-- Recent editions --}}
<div class="bg-white rounded-lg shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Actividad Reciente</h2>
        <a href="{{ route('admin.editions.create') }}"
           class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium
                  hover:bg-blue-700 transition-colors">
            + Subir nuevo PDF
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">Archivo</th>
                    <th class="px-6 py-3 text-left font-semibold">Fecha pub.</th>
                    <th class="px-6 py-3 text-center font-semibold">Artículos</th>
                    <th class="px-6 py-3 text-center font-semibold">Estado</th>
                    <th class="px-6 py-3 text-center font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentEditions as $edition)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-mono text-xs text-slate-700">{{ $edition->filename }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            {{ $edition->publication_date->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-center text-slate-700 font-medium">
                            {{ $edition->total_articles }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $badge = match($edition->status) {
                                    'completed'  => 'bg-green-100 text-green-800',
                                    'processing' => 'bg-blue-100 text-blue-800',
                                    'pending'    => 'bg-yellow-100 text-yellow-800',
                                    'error'      => 'bg-red-100 text-red-800',
                                    default      => 'bg-gray-100 text-gray-800',
                                };
                                $label = match($edition->status) {
                                    'completed'  => '✓ Completado',
                                    'processing' => '⏳ Procesando',
                                    'pending'    => '⏸ Pendiente',
                                    'error'      => '✗ Error',
                                    default      => $edition->status,
                                };
                            @endphp
                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full {{ $badge }}">
                                {{ $label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="{{ route('admin.editions.show', $edition) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            <div class="text-2xl mb-2">📄</div>
                            <p>No hay ediciones importadas todavía.</p>
                            <a href="{{ route('admin.editions.create') }}" class="text-blue-600 hover:underline text-sm mt-1 inline-block">
                                Subir el primer PDF
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
