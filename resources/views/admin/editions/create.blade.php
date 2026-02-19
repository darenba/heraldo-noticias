@extends('layouts.admin')

@section('title', 'Subir PDF')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Subir Nueva Edición PDF</h1>
    <p class="text-slate-500 text-sm mt-1">Carga un PDF de El Heraldo para extraer sus noticias automáticamente</p>
</div>

<div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm p-8"
         x-data="{
             fileName: '',
             fileSize: 0,
             sizeWarning: false,
             extractedDate: '',

             handleFile(event) {
                 const file = event.target.files[0];
                 if (!file) return;
                 this.fileName = file.name;
                 this.fileSize = file.size;
                 this.sizeWarning = file.size > 45 * 1024 * 1024;

                 // Try to extract date from filename EH[YYYY-MM-DD]-...
                 const match = file.name.match(/EH(\d{4}-\d{2}-\d{2})/i);
                 if (match) {
                     this.extractedDate = match[1];
                     this.$refs.dateInput.value = match[1];
                 }
             },

             get fileSizeMB() {
                 return (this.fileSize / (1024 * 1024)).toFixed(1);
             },

             handleDrop(event) {
                 const file = event.dataTransfer.files[0];
                 if (file && file.type === 'application/pdf') {
                     this.$refs.fileInput.files = event.dataTransfer.files;
                     this.handleFile({ target: { files: [file] } });
                 }
             }
         }">

        <form method="POST"
              action="{{ route('admin.editions.store') }}"
              enctype="multipart/form-data">
            @csrf

            {{-- File upload zone --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Archivo PDF <span class="text-red-500">*</span>
                </label>

                <div class="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center
                            hover:border-blue-400 hover:bg-blue-50 transition-colors cursor-pointer"
                     @dragover.prevent
                     @drop.prevent="handleDrop($event)"
                     @click="$refs.fileInput.click()">

                    <template x-if="!fileName">
                        <div>
                            <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-slate-600 font-medium">
                                Arrastra el PDF aquí o <span class="text-blue-600">haz clic para seleccionar</span>
                            </p>
                            <p class="text-xs text-slate-400 mt-1">Solo archivos PDF · Máximo 50 MB</p>
                        </div>
                    </template>

                    <template x-if="fileName">
                        <div>
                            <svg class="mx-auto h-12 w-12 text-green-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm font-medium text-slate-800" x-text="fileName"></p>
                            <p class="text-xs text-slate-500 mt-1" x-text="fileSizeMB + ' MB'"></p>
                        </div>
                    </template>
                </div>

                <input type="file"
                       name="file"
                       id="file"
                       accept=".pdf,application/pdf"
                       class="hidden"
                       x-ref="fileInput"
                       @change="handleFile($event)"
                       required>

                {{-- Size warning --}}
                <template x-if="sizeWarning">
                    <div class="mt-2 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                        ⚠️ El archivo supera 45 MB. El límite del servidor es 50 MB.
                    </div>
                </template>

                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Publication date --}}
            <div class="mb-8">
                <label for="publication_date" class="block text-sm font-medium text-slate-700 mb-1">
                    Fecha de publicación
                    <span class="text-slate-400 font-normal">(opcional — se extrae del nombre del archivo)</span>
                </label>
                <input type="date"
                       id="publication_date"
                       name="publication_date"
                       value="{{ old('publication_date') }}"
                       x-ref="dateInput"
                       class="border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-900
                              focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">

                <template x-if="extractedDate">
                    <p class="mt-1 text-xs text-green-600">
                        ✓ Fecha extraída del nombre del archivo: <span x-text="extractedDate"></span>
                    </p>
                </template>

                @error('publication_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-4">
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold text-sm
                               hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500
                               transition-colors duration-150">
                    Subir y procesar
                </button>
                <a href="{{ route('admin.editions.index') }}"
                   class="text-sm text-slate-600 hover:text-slate-800 transition-colors">
                    Cancelar
                </a>
            </div>
        </form>

        {{-- Info box --}}
        <div class="mt-8 border-t border-slate-200 pt-6">
            <h3 class="text-sm font-medium text-slate-700 mb-2">¿Cómo funciona?</h3>
            <ol class="text-sm text-slate-600 space-y-1 list-decimal list-inside">
                <li>Sube el PDF — se procesa durante la solicitud</li>
                <li>El sistema extrae el texto página por página (PDF parser)</li>
                <li>Claude AI clasifica cada artículo con título, sección y palabras clave</li>
                <li>Si Claude no está disponible, se usa segmentación heurística como respaldo</li>
                <li>Los artículos quedan disponibles en el portal de búsqueda</li>
            </ol>
            <p class="text-xs text-slate-400 mt-3">
                Tiempo estimado: 30-60 segundos para una edición de 28 páginas.
            </p>
        </div>
    </div>
</div>

@endsection
