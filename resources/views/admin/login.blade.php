<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — El Heraldo Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-4">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">

        {{-- Header --}}
        <div class="bg-slate-800 px-8 py-6 text-center">
            <div class="text-white font-black text-2xl tracking-tight mb-1">EL HERALDO</div>
            <div class="text-slate-400 text-xs uppercase tracking-widest">Hemeroteca Digital</div>
            <div class="text-slate-300 text-sm mt-2">Panel de Administración</div>
        </div>

        {{-- Form --}}
        <div class="px-8 py-8">

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm" role="alert">
                    {{ $errors->first('email') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}">
                @csrf

                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                        Correo electrónico
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email"
                           class="w-full border border-slate-300 rounded-lg px-4 py-3 text-sm
                                  text-slate-900 bg-white
                                  focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                                  @error('email') border-red-400 @enderror">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        Contraseña
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           class="w-full border border-slate-300 rounded-lg px-4 py-3 text-sm
                                  text-slate-900 bg-white
                                  focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>

                <button type="submit"
                        class="w-full bg-slate-800 text-white py-3 rounded-lg font-semibold text-sm
                               hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500
                               transition-colors duration-150">
                    Iniciar sesión
                </button>
            </form>
        </div>

        <div class="border-t border-slate-100 px-8 py-4 bg-slate-50 text-center">
            <p class="text-xs text-slate-500">
                Acceso restringido. Solo personal autorizado de El Heraldo.
            </p>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="{{ route('articles.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
            ← Ir al portal público
        </a>
    </div>
</div>

</body>
</html>
