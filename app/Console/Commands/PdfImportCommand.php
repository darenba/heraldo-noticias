<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PdfExtractionJob;
use App\Services\EditionService;
use Illuminate\Console\Command;

class PdfImportCommand extends Command
{
    protected $signature = 'pdf:import
                            {path : Ruta al archivo PDF}
                            {--date= : Fecha de publicación en formato YYYY-MM-DD}';

    protected $description = 'Importa un archivo PDF de El Heraldo y extrae sus noticias';

    public function handle(EditionService $editionService): int
    {
        $path = $this->argument('path');
        $date = $this->option('date');

        // Validate file exists
        if (! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo: {$path}");

            return self::FAILURE;
        }

        // Validate it's a PDF
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $this->error("El archivo debe ser un PDF. Extensión detectada: {$extension}");

            return self::FAILURE;
        }

        $filename = basename($path);
        $this->info("Procesando: {$filename}");

        // Try to extract date from filename if not provided
        if (empty($date)) {
            $date = $editionService->extractDateFromFilename($filename);
            if ($date) {
                $this->line("  Fecha extraída del nombre de archivo: {$date}");
            }
        }

        // Ask interactively if date still not found
        if (empty($date)) {
            $date = $this->ask('Fecha de publicación (YYYY-MM-DD)');

            if (empty($date)) {
                $this->warn('No se especificó fecha. Usando la fecha de hoy.');
                $date = now()->toDateString();
            }
        }

        // Validate date format
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("Formato de fecha inválido: {$date}. Use YYYY-MM-DD.");

            return self::FAILURE;
        }

        // Check for duplicate
        $fileHash = hash_file('sha256', $path);
        $existing = $editionService->findByHash($fileHash);

        if ($existing !== null) {
            $this->warn("Este PDF ya fue importado anteriormente.");
            $this->line("  Edición ID: {$existing->id}");
            $this->line("  Estado: {$existing->status}");
            $this->line("  Artículos: {$existing->total_articles}");

            if (! $this->confirm('¿Desea reprocesar de todas formas?')) {
                $this->info('Importación cancelada.');

                return self::SUCCESS;
            }
        }

        // Create edition from local path
        $this->line('  Copiando archivo a storage local...');

        try {
            $edition = $editionService->createFromLocalPath($path, $date);
            $this->info("  Edición creada con ID: {$edition->id}");
        } catch (\Exception $e) {
            $this->error("Error al crear la edición: " . $e->getMessage());

            return self::FAILURE;
        }

        // Run extraction synchronously (CLI mode)
        $this->line('  Iniciando extracción de texto...');
        $this->line('  (Esto puede tardar varios minutos para PDFs de 20+ páginas)');

        try {
            PdfExtractionJob::dispatchSync($edition);

            // Reload edition to get updated counts
            $edition->refresh();

            $this->newLine();
            $this->info('✓ Extracción completada exitosamente.');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Edición ID', $edition->id],
                    ['Archivo', $edition->filename],
                    ['Fecha publicación', $edition->publication_date->format('d/m/Y')],
                    ['Páginas procesadas', $edition->total_pages],
                    ['Artículos extraídos', $edition->total_articles],
                    ['Estado', $edition->status],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ La extracción falló: ' . $e->getMessage());
            $this->line('  Revise los logs para más detalles.');

            return self::FAILURE;
        }
    }
}
