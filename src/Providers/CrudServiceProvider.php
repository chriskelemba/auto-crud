<?php

namespace AutoCrud\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use AutoCrud\Http\Controllers\Controller;

class CrudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadDynamicRoutes();
    }

    protected function loadDynamicRoutes()
    {
        $searchPaths = $this->getControllerSearchPaths();

        Route::prefix('api')->middleware('api')->group(function () use ($searchPaths) {
            foreach ($searchPaths as $searchPath) {
                if (!File::exists($searchPath['path'])) {
                    continue;
                }

                $files = File::allFiles($searchPath['path']);

                foreach ($files as $file) {
                    // Skip non-Controller files
                    if (!Str::endsWith($file->getFilename(), 'Controller.php')) {
                        continue;
                    }

                    $class = $this->classFromFile($file, $searchPath);

                    if (!class_exists($class)) {
                        continue;
                    }

                    if (!is_subclass_of($class, Controller::class)) {
                        continue;
                    }

                    $this->registerCrudRoutes($class);
                }
            }
        });
    }

    protected function getControllerSearchPaths(): array
    {
        $paths = [];

        $paths[] = [
            'path' => app_path('Http/Controllers'),
            'namespace' => 'App\\Http\\Controllers',
            'base' => app_path('Http/Controllers'),
        ];

        $modulesPath = app_path('Modules');
        if (File::exists($modulesPath)) {
            $modules = File::directories($modulesPath);
            
            foreach ($modules as $module) {
                $controllersPath = $module . '/Controllers';
                
                if (File::exists($controllersPath)) {
                    $moduleName = basename($module);
                    $paths[] = [
                        'path' => $controllersPath,
                        'namespace' => "App\\Modules\\{$moduleName}\\Controllers",
                        'base' => $controllersPath,
                    ];
                }
            }
        }

        $customPaths = config('autocrud.controller_paths', []);
        foreach ($customPaths as $customPath) {
            if (File::exists($customPath['path'])) {
                $paths[] = $customPath;
            }
        }

        return $paths;
    }

    protected function classFromFile($file, array $searchPath)
    {
        $relative = str_replace($searchPath['base'], '', $file->getPathname());
        $relative = trim(str_replace(['/', '.php'], ['\\', ''], $relative), '\\');

        return $searchPath['namespace'] . ($relative ? '\\' . $relative : '');
    }

    protected function registerCrudRoutes(string $controller)
    {
        $parts = explode('\\', $controller);
        $name = array_pop($parts);

        $base = preg_replace('/Controller$/i', '', $name);
        $base = Str::kebab(Str::plural($base));

        // Main CRUD routes
        Route::get("$base", [$controller, 'index'])->name("$base.index");
        Route::post("$base", [$controller, 'store'])->name("$base.store");
        Route::get("$base/{id}", [$controller, 'show'])->name("$base.show");
        Route::put("$base/{id}", [$controller, 'update'])->name("$base.update");
        Route::delete("$base/{id}", [$controller, 'destroy'])->name("$base.destroy");

        // Soft delete routes
        Route::get("$base/trashed", [$controller, 'trashed'])->name("$base.trashed");
        Route::post("$base/{id}/restore", [$controller, 'restore'])->name("$base.restore");
        Route::delete("$base/{id}/force", [$controller, 'forceDelete'])->name("$base.forceDelete");

        // File handling routes
        Route::post("$base/upload", [$controller, 'uploadFile'])->name("$base.upload");
        Route::post("$base/{id}/upload", [$controller, 'updateFile'])->name("$base.updateFile");
        Route::post("$base/uploads/multiple", [$controller, 'uploadMultipleFiles'])->name("$base.uploadMultiple");
        Route::get("$base/download/{id}", [$controller, 'downloadFile'])->name("$base.download");
        Route::delete("$base/delete-file/{id}", [$controller, 'deleteFile'])->name("$base.deleteFile");
    }
}