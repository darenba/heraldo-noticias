@extends('layouts.app')

@section('title', $query ? "Búsqueda: {$query} — Hemeroteca El Heraldo" : 'Hemeroteca Digital El Heraldo')

@section('content')

{{-- Search section --}}
<section class="mb-6">
    <form method="GET" action="{{ route('articles.index') }}" x-ref="searchForm">
        <div class="flex gap-2 mb-4">
            <input type="search"
                   name="q"
                   value="{{ $query }}"
                   placeholder="Buscar noticias..."
                   aria-label="Buscar noticias"
                   class="flex-1 border border-heraldo-sepia-200 rounded px-4 py-3
                          text-heraldo-sepia-900 bg-white placeholder-heraldo-sepia-300
                          focus:outline-none focus:border-heraldo-gold focus:ring-1 focus:ring-heraldo-gold
                          text-base">
            <button type="submit"
                    class="bg-heraldo-sepia-900 text-heraldo-sepia-50 px-6 py-3 rounded
                           font-medium hover:bg-heraldo-ink transition-colors duration-150 whitespace-nowrap">
                Buscar
            </button>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 items-end"
             x-data="{
                 section: '{{ $filters['section'] ?? '' }}',
                 dateFrom: '{{ $filters['date_from'] ?? '' }}',
                 dateTo: '{{ $filters['date_to'] ?? '' }}',
                 apply() { $refs.searchForm.submit(); }
             }">

            @if($query)
                <input type="hidden" name="q" value="{{ $query }}">
            @endif

            <div class="flex flex-col gap-1">
                <label for="section" class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-wide">Sección</label>
                <select name="section"
                        id="section"
                        x-model="section"
                        @change="apply()"
                        class="border border-heraldo-sepia-200 rounded px-3 py-2 text-sm
                               text-heraldo-sepia-900 bg-white focus:outline-none focus:border-heraldo-gold">
                    <option value="">Todas</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec }}" {{ ($filters['section'] ?? '') === $sec ? 'selected' : '' }}>
                            {{ $sec }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="date_from" class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-wide">Desde</label>
                <input type="date"
                       name="date_from"
                       id="date_from"
                       value="{{ $filters['date_from'] ?? '' }}"
                       x-model="dateFrom"
                       @change="apply()"
                       class="border border-heraldo-sepia-200 rounded px-3 py-2 text-sm
                              text-heraldo-sepia-900 bg-white focus:outline-none focus:border-heraldo-gold">
            </div>

            <div class="flex flex-col gap-1">
                <label for="date_to" class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-wide">Hasta</label>
                <input type="date"
                       name="date_to"
                       id="date_to"
                       value="{{ $filters['date_to'] ?? '' }}"
                       x-model="dateTo"
                       @change="apply()"
                       class="border border-heraldo-sepia-200 rounded px-3 py-2 text-sm
                              text-heraldo-sepia-900 bg-white focus:outline-none focus:border-heraldo-gold">
            </div>

            @if(!empty($filters['tag']))
                <input type="hidden" name="tag" value="{{ $filters['tag'] }}">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-wide">Tag activo</label>
                    <div class="flex items-center gap-2">
                        <span class="inline-block px-2 py-1 text-xs bg-heraldo-sepia-200 text-heraldo-sepia-700 rounded">
                            {{ $filters['tag'] }}
                        </span>
                        <a href="{{ route('articles.index', array_merge(request()->except('tag'), [])) }}"
                           class="text-xs text-heraldo-red hover:underline">
                            ✕ Quitar
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </form>
</section>

{{-- Results header --}}
<div class="flex items-center justify-between mb-4 border-b border-heraldo-sepia-200 pb-3">
    <p class="text-sm text-heraldo-sepia-700" aria-live="polite">
        @if($articles->total() > 0)
            <span class="font-semibold text-heraldo-sepia-900">{{ number_format($articles->total()) }}</span>
            {{ $articles->total() === 1 ? 'noticia encontrada' : 'noticias encontradas' }}
            @if($query)
                para "<em>{{ $query }}</em>"
            @endif
        @else
            No se encontraron noticias
            @if($query)
                para "<em>{{ $query }}</em>"
            @endif
        @endif
    </p>
    <p class="text-xs text-heraldo-sepia-700">
        Página {{ $articles->currentPage() }} de {{ $articles->lastPage() }}
    </p>
</div>

{{-- Article list --}}
@if($articles->isEmpty())
    <div class="text-center py-16">
        <div class="text-4xl mb-4">📰</div>
        <h2 class="text-xl font-serif font-bold text-heraldo-sepia-900 mb-2">Sin resultados</h2>
        <p class="text-heraldo-sepia-700 mb-4">
            No encontramos noticias con los criterios seleccionados.
        </p>
        <a href="{{ route('articles.index') }}"
           class="inline-block bg-heraldo-sepia-900 text-heraldo-sepia-50 px-4 py-2 rounded
                  text-sm font-medium hover:bg-heraldo-ink transition-colors">
            Ver todas las noticias
        </a>
    </div>
@else
    <div class="space-y-4" aria-label="Lista de noticias">
        @foreach($articles as $article)
            <a href="{{ route('articles.show', $article) }}" class="block group">
                <article class="bg-heraldo-sepia-100 border border-heraldo-sepia-200 rounded-lg p-6
                                group-hover:shadow-md group-hover:border-heraldo-sepia-300
                                transition-all duration-200">
                    <header class="mb-2">
                        <div class="flex flex-wrap items-center gap-2 mb-1 text-sm text-heraldo-sepia-700">
                            @if($article->section)
                                <span class="inline-block px-2 py-0.5 text-xs font-semibold uppercase tracking-widest bg-heraldo-sepia-200 text-heraldo-sepia-700 rounded">
                                    {{ $article->section }}
                                </span>
                            @endif
                            <time datetime="{{ $article->publication_date->toDateString() }}">
                                {{ $article->publication_date->format('d M Y') }}
                            </time>
                            @if($article->page_number)
                                <span>· Pág. {{ $article->page_number }}</span>
                            @endif
                        </div>
                        <h2 class="text-xl font-bold font-serif text-heraldo-ink leading-tight
                                   group-hover:text-heraldo-sepia-700 transition-colors">
                            {{ $article->title }}
                        </h2>
                    </header>
                    <p class="text-base text-heraldo-sepia-900 leading-snug mb-3 line-clamp-3">
                        {{ \Illuminate\Support\Str::limit($article->body_excerpt ?? $article->body, 220) }}
                    </p>
                    @if($article->tags->isNotEmpty())
                        <footer class="flex flex-wrap gap-1" onclick="event.preventDefault()">
                            @foreach($article->tags->take(5) as $tag)
                                <a href="{{ route('articles.index', ['tag' => $tag->name]) }}"
                                   onclick="event.stopPropagation()"
                                   class="inline-block px-2 py-0.5 text-xs font-medium
                                          bg-heraldo-sepia-50 text-heraldo-sepia-700
                                          border border-heraldo-sepia-200 rounded-full
                                          hover:bg-heraldo-sepia-200 transition-colors">
                                    {{ $tag->display_name }}
                                </a>
                            @endforeach
                        </footer>
                    @endif
                </article>
            </a>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $articles->appends(request()->query())->links() }}
    </div>
@endif

@endsection
