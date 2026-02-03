<?php

namespace AutoCrud\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use AutoCrud\Http\Controllers\Controller;

class CrudServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/autocrud.php', 'autocrud');
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'autocrud');
        $this->loadDynamicRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/autocrud.php' => config_path('autocrud.php'),
            ], 'autocrud-config');
        }
    }

    protected function loadDynamicRoutes()
    {
        $searchPaths = $this->getControllerSearchPaths();

        $registerControllers = function (string $routeType) use ($searchPaths) {
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

                    $this->registerCrudRoutes($class, $routeType);
                }
            }
        };

        if (config('autocrud.api.enabled', true)) {
            Route::prefix(config('autocrud.api.prefix', 'api'))
                ->middleware(config('autocrud.api.middleware', ['api']))
                ->group(function () use ($registerControllers) {
                    $registerControllers('api');
                });
        }

        if (config('autocrud.web.enabled', true)) {
            Route::prefix(config('autocrud.web.prefix', 'crud'))
                ->middleware(config('autocrud.web.middleware', ['web']))
                ->group(function () use ($registerControllers) {
                    $registerControllers('web');
                });
        }
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

    protected function registerCrudRoutes(string $controller, string $routeType)
    {
        $parts = explode('\\', $controller);
        $name = array_pop($parts);

        $base = preg_replace('/Controller$/i', '', $name);
        $base = Str::kebab(Str::plural($base));
        $namePrefix = $routeType === 'web'
            ? config('autocrud.web.route_name_prefix', 'web.')
            : config('autocrud.api.route_name_prefix', '');

        if ($routeType === 'web') {
            Route::get("$base", [$controller, 'index'])->name("{$namePrefix}{$base}.index");
            Route::get("$base/create", [$controller, 'create'])->name("{$namePrefix}{$base}.create");
            Route::post("$base", [$controller, 'store'])->name("{$namePrefix}{$base}.store");
            Route::get("$base/{id}", [$controller, 'show'])->name("{$namePrefix}{$base}.show");
            Route::get("$base/{id}/edit", [$controller, 'edit'])->name("{$namePrefix}{$base}.edit");
            Route::put("$base/{id}", [$controller, 'update'])->name("{$namePrefix}{$base}.update");
            Route::delete("$base/{id}", [$controller, 'destroy'])->name("{$namePrefix}{$base}.destroy");
        } else {
            // Main CRUD routes (API)
            Route::get("$base", [$controller, 'index'])->name("{$namePrefix}{$base}.index");
            Route::post("$base", [$controller, 'store'])->name("{$namePrefix}{$base}.store");
            Route::get("$base/{id}", [$controller, 'show'])->name("{$namePrefix}{$base}.show");
            Route::put("$base/{id}", [$controller, 'update'])->name("{$namePrefix}{$base}.update");
            Route::delete("$base/{id}", [$controller, 'destroy'])->name("{$namePrefix}{$base}.destroy");
        }

        // Soft delete routes
        Route::get("$base/trashed", [$controller, 'trashed'])->name("{$namePrefix}{$base}.trashed");
        Route::post("$base/{id}/restore", [$controller, 'restore'])->name("{$namePrefix}{$base}.restore");
        Route::delete("$base/{id}/force", [$controller, 'forceDelete'])->name("{$namePrefix}{$base}.forceDelete");

    }
}
