@extends('layouts.admin')

@section('title', 'Ediciones')

@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Ediciones Importadas</h1>
        <p class="text-slate-500 text-sm mt-1">Gestiona las ediciones PDF del periódico</p>
    </div>
    <a href="{{ route('admin.editions.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium
              hover:bg-blue-700 transition-colors flex items-center gap-2">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Subir nueva PDF
    </a>
</div>

{{-- Status filter --}}
<div class="bg-white rounded-lg shadow-sm mb-4 px-4 py-3 flex items-center gap-4">
    <span class="text-sm font-medium text-slate-600">Filtrar por estado:</span>
    <div class="flex gap-2">
        @foreach(['', 'pending', 'processing', 'completed', 'error'] as $status)
            @php
                $label = match($status) {
                    ''           => 'Todos',
                    'pending'    => 'Pendiente',
                    'processing' => 'Procesando',
                    'completed'  => 'Completado',
                    'error'      => 'Error',
                };
                $active = request('status', '') === $status;
            @endphp
            <a href="{{ route('admin.editions.index', $status ? ['status' => $status] : []) }}"
               class="px-3 py-1 rounded-full text-xs font-medium transition-colors
                      {{ $active ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{-- Editions table --}}
<div class="bg-white rounded-lg shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">Archivo</th>
                    <th class="px-6 py-3 text-left font-semibold">Fecha pub.</th>
                    <th class="px-6 py-3 text-center font-semibold">Págs</th>
                    <th class="px-6 py-3 text-center font-semibold">Arts.</th>
                    <th class="px-6 py-3 text-center font-semibold">Estado</th>
                    <th class="px-6 py-3 text-center font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($editions as $edition)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-mono text-xs text-slate-700 break-all">{{ $edition->filename }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                            {{ $edition->publication_date->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            {{ $edition->total_pages ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center font-medium text-slate-700">
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
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('admin.editions.show', $edition) }}"
                                   class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                    Ver
                                </a>
                                @if(!$edition->isProcessing())
                                    <form method="POST"
                                          action="{{ route('admin.editions.destroy', $edition) }}"
                                          x-data="{}"
                                          @submit.prevent="if(confirm('¿Eliminar la edición {{ addslashes($edition->filename) }} y sus {{ $edition->total_articles }} artículos? Esta acción no se puede deshacer.')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-500 hover:text-red-700 text-xs font-medium">
                                            <span class="sr-only">Eliminar edición {{ $edition->filename }}</span>
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            <div class="text-2xl mb-2">📄</div>
                            <p>No hay ediciones con este estado.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($editions->hasPages())
        <div class="px-6 py-4 border-t border-slate-200">
            {{ $editions->links() }}
        </div>
    @endif
</div>

@endsection
