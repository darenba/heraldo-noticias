@extends('layouts.app')

@section('title', $article->title . ' — Hemeroteca El Heraldo')
@section('description', \Illuminate\Support\Str::limit($article->body_excerpt ?? $article->body, 160))

@section('content')

{{-- Breadcrumb --}}
<nav aria-label="Ruta de navegación" class="mb-4 text-sm text-heraldo-sepia-700">
    <ol class="flex items-center gap-2">
        <li><a href="{{ route('articles.index') }}" class="hover:text-heraldo-gold transition-colors">Inicio</a></li>
        <li aria-hidden="true">›</li>
        @if($article->section)
            <li>
                <a href="{{ route('articles.index', ['section' => $article->section]) }}"
                   class="hover:text-heraldo-gold transition-colors">
                    {{ $article->section }}
                </a>
            </li>
            <li aria-hidden="true">›</li>
        @endif
        <li class="text-heraldo-sepia-900 font-medium truncate max-w-xs" aria-current="page">
            {{ \Illuminate\Support\Str::limit($article->title, 50) }}
        </li>
    </ol>
</nav>

<div class="max-w-3xl mx-auto">

    {{-- Article metadata --}}
    <header class="mb-6">
        <div class="flex flex-wrap items-center gap-3 mb-3 text-sm text-heraldo-sepia-700">
            @if($article->section)
                <a href="{{ route('articles.index', ['section' => $article->section]) }}"
                   class="inline-block px-2 py-0.5 text-xs font-semibold uppercase tracking-widest
                          bg-heraldo-sepia-200 text-heraldo-sepia-700 rounded
                          hover:bg-heraldo-sepia-300 transition-colors">
                    {{ $article->section }}
                </a>
            @endif
            <span>{{ $article->newspaper_name }}</span>
            <span aria-hidden="true">·</span>
            <time datetime="{{ $article->publication_date->toDateString() }}">
                {{ $article->publication_date->format('d \d\e F \d\e Y') }}
            </time>
            @if($article->page_number)
                <span aria-hidden="true">·</span>
                <span>Página {{ $article->page_number }}</span>
            @endif
        </div>

        <div class="border-t-2 border-heraldo-sepia-900 pt-4 mb-4"></div>

        <h1 class="text-4xl font-black font-serif text-heraldo-ink leading-tight mb-4">
            {{ $article->title }}
        </h1>

        <div class="border-b-2 border-heraldo-sepia-900 mb-6"></div>

        {{-- Tags --}}
        @if($article->tags->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-wide self-center">
                    Palabras clave:
                </span>
                @foreach($article->tags as $tag)
                    <a href="{{ route('articles.index', ['tag' => $tag->name]) }}"
                       class="inline-block px-3 py-1 text-xs font-medium
                              bg-heraldo-sepia-100 text-heraldo-sepia-700
                              border border-heraldo-sepia-200 rounded-full
                              hover:bg-heraldo-sepia-200 hover:text-heraldo-sepia-900
                              transition-colors duration-150">
                        {{ $tag->display_name }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    {{-- Article body --}}
    <article class="prose prose-lg max-w-none mb-8">
        <div class="text-lg leading-relaxed text-heraldo-sepia-900"
             style="font-family: 'Crimson Text', 'Times New Roman', serif; text-align: justify; font-size: 1.15rem; line-height: 1.8;">
            {!! nl2br(e($article->body)) !!}
        </div>
    </article>

    {{-- Word count --}}
    @if($article->word_count > 0)
        <p class="text-xs text-heraldo-sepia-700 mb-8 border-t border-heraldo-sepia-200 pt-4">
            {{ number_format($article->word_count) }} palabras · Extraído automáticamente del PDF
        </p>
    @endif

    {{-- Related articles --}}
    @if($related->isNotEmpty())
        <aside aria-label="Noticias relacionadas" class="border-t-2 border-heraldo-sepia-200 pt-6">
            <h2 class="text-lg font-bold font-serif text-heraldo-ink mb-4 uppercase tracking-wide">
                Noticias relacionadas
            </h2>
            <ul class="space-y-3">
                @foreach($related as $relatedArticle)
                    <li>
                        <a href="{{ route('articles.show', $relatedArticle) }}"
                           class="flex items-start gap-3 group">
                            <span class="text-heraldo-sepia-300 mt-1 flex-shrink-0">›</span>
                            <div>
                                <span class="font-serif font-bold text-heraldo-ink group-hover:text-heraldo-sepia-700 transition-colors leading-tight block">
                                    {{ $relatedArticle->title }}
                                </span>
                                <span class="text-xs text-heraldo-sepia-700">
                                    @if($relatedArticle->section){{ $relatedArticle->section }} · @endif
                                    {{ $relatedArticle->publication_date->format('d M Y') }}
                                </span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif

    {{-- Back button --}}
    <div class="mt-8 pt-6 border-t border-heraldo-sepia-200">
        <a href="javascript:history.back()"
           class="inline-flex items-center gap-2 text-sm font-medium text-heraldo-sepia-700
                  hover:text-heraldo-ink transition-colors">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a resultados
        </a>
    </div>
</div>

@endsection
