<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hemeroteca Digital El Heraldo')</title>
    <meta name="description" content="@yield('description', 'Archivo histórico digital del periódico El Heraldo. Busca y lee noticias por fecha, sección y palabras clave.')">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Crimson+Text:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN for development) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        heraldo: {
                            'sepia-50':  '#fdf8f0',
                            'sepia-100': '#f5ead8',
                            'sepia-200': '#e8d5b7',
                            'sepia-300': '#d4b896',
                            'sepia-700': '#6b4c2a',
                            'sepia-900': '#2c1a0e',
                            'ink':       '#1a1208',
                            'gold':      '#b8860b',
                            'red':       '#8b1a1a',
                        },
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'Georgia', 'serif'],
                        body:  ['Crimson Text', 'Times New Roman', 'serif'],
                        sans:  ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="bg-heraldo-sepia-50 font-sans text-heraldo-sepia-900 min-h-screen flex flex-col">

    <!-- Skip link for accessibility -->
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 bg-heraldo-gold text-white px-4 py-2 rounded z-50">
        Saltar al contenido
    </a>

    <!-- Header -->
    <header class="border-b-2 border-heraldo-sepia-200 bg-heraldo-sepia-50">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <a href="{{ route('articles.index') }}" class="flex items-center gap-3 no-underline">
                    <div>
                        <div class="text-2xl font-black font-serif text-heraldo-ink tracking-tight leading-none">
                            EL HERALDO
                        </div>
                        <div class="text-xs font-medium text-heraldo-sepia-700 uppercase tracking-widest mt-0.5">
                            Hemeroteca Digital
                        </div>
                    </div>
                </a>
                <div class="text-sm text-heraldo-sepia-700 font-sans hidden sm:block">
                    {{ now()->translatedFormat('d \d\e F \d\e Y') }}
                </div>
            </div>
        </div>
        <!-- Decorative rule -->
        <div class="border-t border-heraldo-sepia-300 mx-4"></div>
    </header>

    <!-- Main content -->
    <main id="main" class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded mb-4" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t-2 border-heraldo-sepia-200 bg-heraldo-sepia-100 mt-auto">
        <div class="max-w-4xl mx-auto px-4 py-6 text-center">
            <p class="text-sm text-heraldo-sepia-700">
                &copy; {{ date('Y') }} <span class="font-semibold">El Heraldo</span> — Hemeroteca Digital.
                Todos los contenidos son propiedad del periódico El Heraldo.
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
