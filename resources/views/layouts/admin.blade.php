<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — El Heraldo Admin</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="bg-slate-50 font-sans text-slate-800" x-data="{ sidebarOpen: false }">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-800 flex-shrink-0 flex flex-col"
           :class="sidebarOpen ? 'block' : 'hidden md:flex'">

        <div class="p-4 border-b border-slate-700">
            <div class="text-white font-bold text-lg leading-tight">EL HERALDO</div>
            <div class="text-slate-400 text-xs uppercase tracking-widest">Panel Admin</div>
        </div>

        <nav class="flex-1 py-4" aria-label="Navegación admin">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('admin.editions.index') }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.editions.*') && !request()->routeIs('admin.editions.create') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Ediciones
            </a>

            <a href="{{ route('admin.editions.create') }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.editions.create') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Subir PDF
            </a>

            <div class="border-t border-slate-700 mt-4 pt-4 mx-4">
                <a href="{{ route('articles.index') }}" target="_blank"
                   class="flex items-center gap-3 px-0 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Ver portal público
                </a>
            </div>
        </nav>

        <!-- User info & logout -->
        <div class="border-t border-slate-700 p-4">
            <div class="text-slate-400 text-xs mb-2 truncate">{{ Auth::user()?->email }}</div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full text-left text-sm text-slate-400 hover:text-white transition-colors flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Top bar (mobile menu toggle) -->
        <div class="md:hidden bg-slate-800 text-white px-4 py-3 flex items-center gap-3">
            <button @click="sidebarOpen = !sidebarOpen" class="text-slate-300 hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="font-bold">El Heraldo Admin</span>
        </div>

        <main class="flex-1 overflow-auto p-6 md:p-8">

            <!-- Flash messages -->
            @if(session('success'))
                <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg mb-6" role="alert">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-lg mb-6" role="alert">
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
