<?php

namespace App\Console\Commands;

use App\Helpers\ImageOptimizer;
use Illuminate\Console\Command;

class OptimizeModuleImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:optimize-modules
                            {--folder=fotos_modulos : Carpeta dentro de public/images (ej. fotos_modulos, fotos_tejido)}
                            {--max=400 : Tamaño máximo del lado largo en píxeles}
                            {--dry-run : Solo listar archivos que se optimizarían, sin modificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimiza imágenes de módulos (redimensiona y comprime) para que carguen más rápido';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('PHP GD no está instalado. Instala la extensión GD para poder optimizar imágenes.');

            return self::FAILURE;
        }

        $folder = $this->option('folder');
        $maxSize = (int) $this->option('max');
        $dryRun = $this->option('dry-run');

        $basePath = public_path('images/' . $folder);
        if (! is_dir($basePath)) {
            $this->error("La carpeta no existe: {$basePath}");

            return self::FAILURE;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $files = [];
        foreach (new \DirectoryIterator($basePath) as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        if (empty($files)) {
            $this->warn("No hay imágenes en {$basePath}");

            return self::SUCCESS;
        }

        $this->info('Imágenes encontradas: ' . count($files));
        if ($dryRun) {
            $this->line('Modo dry-run: no se modificará ningún archivo.');
            foreach ($files as $path) {
                $this->line('  - ' . basename($path));
            }

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();
        $ok = 0;
        $fail = 0;

        foreach ($files as $path) {
            if (ImageOptimizer::optimizeFile($path, $maxSize)) {
                $ok++;
            } else {
                $fail++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo: {$ok} optimizadas, {$fail} sin cambios o error.");

        return self::SUCCESS;
    }
}
