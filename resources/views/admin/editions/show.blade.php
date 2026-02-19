@extends('layouts.admin')

@section('title', $edition->filename)

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.editions.index') }}"
       class="text-slate-500 hover:text-slate-700 transition-colors">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-bold text-slate-800 font-mono">{{ $edition->filename }}</h1>
        <p class="text-slate-500 text-sm">{{ $edition->publication_date->format('d \d\e F \d\e Y') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Edition info + job status --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Status card with live polling --}}
        <div class="bg-white rounded-lg shadow-sm p-6"
             x-data="{
                 status: '{{ $edition->status }}',
                 pageCurrent: {{ $job?->page_current ?? 0 }},
                 pageTotal: {{ $job?->page_total ?? 0 }},
                 articlesExtracted: {{ $job?->articles_extracted ?? 0 }},
                 pollTimer: null,

                 init() {
                     if (['processing', 'pending'].includes(this.status)) {
                         this.startPolling();
                     }
                 },

                 startPolling() {
                     this.pollTimer = setInterval(() => this.fetchStatus(), 5000);
                 },

                 async fetchStatus() {
                     try {
                         const res = await fetch('{{ route('admin.editions.status', $edition) }}');
                         const data = await res.json();
                         this.status = data.status;
                         this.pageCurrent = data.page_current || 0;
                         this.pageTotal = data.page_total || 0;
                         this.articlesExtracted = data.articles_extracted || 0;
                         if (['completed', 'error'].includes(data.status)) {
                             clearInterval(this.pollTimer);
                             setTimeout(() => window.location.reload(), 2000);
                         }
                     } catch(e) {
                         console.error('Poll failed:', e);
                     }
                 },

                 get progressPercent() {
                     if (!this.pageTotal) return 0;
                     return Math.min(100, Math.round((this.pageCurrent / this.pageTotal) * 100));
                 },

                 destroy() {
                     if (this.pollTimer) clearInterval(this.pollTimer);
                 }
             }"
             @destroy="destroy()">

            <h2 class="font-semibold text-slate-800 mb-4">Estado de Extracción</h2>

            <div class="flex items-center gap-3 mb-4">
                @php
                    $badge = match($edition->status) {
                        'completed'  => 'bg-green-100 text-green-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'pending'    => 'bg-yellow-100 text-yellow-800',
                        'error'      => 'bg-red-100 text-red-800',
                        default      => 'bg-gray-100 text-gray-800',
                    };
                @endphp
                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $badge }}" x-text="
                    status === 'completed' ? '✓ Completado' :
                    status === 'processing' ? '⏳ Procesando' :
                    status === 'pending' ? '⏸ Pendiente' :
                    status === 'error' ? '✗ Error' : status
                ">
                    {{ match($edition->status) {
                        'completed' => '✓ Completado',
                        'processing' => '⏳ Procesando',
                        'pending' => '⏸ Pendiente',
                        'error' => '✗ Error',
                        default => $edition->status,
                    } }}
                </span>
            </div>

            {{-- Progress bar (visible during processing) --}}
            <template x-if="status === 'processing' || status === 'pending'">
                <div class="mb-4">
                    <div class="flex justify-between text-xs text-slate-600 mb-1">
                        <span>Página <span x-text="pageCurrent"></span> de <span x-text="pageTotal || '?'"></span></span>
                        <span><span x-text="articlesExtracted"></span> artículos extraídos</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                             :style="'width: ' + progressPercent + '%'"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Actualizando cada 5 segundos...</p>
                </div>
            </template>
        </div>

        {{-- Processing log --}}
        @if($edition->processing_log)
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="font-semibold text-slate-800 mb-4">Log de Procesamiento</h2>
                <details class="group">
                    <summary class="text-sm text-slate-600 cursor-pointer hover:text-slate-800 list-none flex items-center gap-2">
                        <svg class="h-4 w-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Ver detalles técnicos
                    </summary>
                    <pre class="mt-3 text-xs bg-slate-50 border border-slate-200 rounded p-4 overflow-auto max-h-64 text-slate-700">{{ json_encode($edition->processing_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        @endif
    </div>

    {{-- Edition metadata --}}
    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-4">Detalles</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500 text-xs uppercase tracking-wide">Periódico</dt>
                    <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->newspaper_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 text-xs uppercase tracking-wide">Fecha publicación</dt>
                    <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->publication_date->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 text-xs uppercase tracking-wide">Páginas</dt>
                    <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->total_pages ?? 'Pendiente' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 text-xs uppercase tracking-wide">Artículos extraídos</dt>
                    <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->total_articles }}</dd>
                </div>
                @if($edition->processed_at)
                    <div>
                        <dt class="text-slate-500 text-xs uppercase tracking-wide">Procesado el</dt>
                        <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->processed_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-500 text-xs uppercase tracking-wide">Importado el</dt>
                    <dd class="font-medium text-slate-800 mt-0.5">{{ $edition->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        @if(!$edition->isProcessing())
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="font-semibold text-slate-800 mb-4">Acciones</h2>
                @if($edition->total_articles > 0)
                    <a href="{{ route('articles.index') }}"
                       target="_blank"
                       class="block w-full text-center bg-slate-800 text-white py-2 rounded-lg text-sm font-medium
                              hover:bg-slate-900 transition-colors mb-3">
                        Ver artículos en portal
                    </a>
                @endif
                <form method="POST"
                      action="{{ route('admin.editions.destroy', $edition) }}"
                      @submit.prevent="if(confirm('¿Eliminar esta edición y sus {{ $edition->total_articles }} artículos?')) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="block w-full text-center border border-red-300 text-red-600 py-2 rounded-lg text-sm font-medium
                                   hover:bg-red-50 transition-colors">
                        Eliminar edición
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

@endsection
