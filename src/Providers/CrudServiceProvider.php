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
        $basePath = app_path('Http/Controllers');

        $files = File::allFiles($basePath);

        Route::prefix('api')->middleware('api')->group(function () use ($files, $basePath) {

            foreach ($files as $file) {

                $class = $this->classFromFile($file, $basePath);

                if (!class_exists($class)) {
                    continue;
                }

                if (!is_subclass_of($class, Controller::class)) {
                    continue;
                }

                $this->registerCrudRoutes($class);
            }
        });
    }

    protected function classFromFile($file, $basePath)
    {
        $relative = str_replace($basePath, '', $file->getPathname());

        $relative = trim(str_replace(['/', '.php'], ['\\', ''], $relative), '\\');

        return 'App\\Http\\Controllers\\' . $relative;
    }

    protected function registerCrudRoutes(string $controller)
    {
        $parts = explode('\\', $controller);

        $name = array_pop($parts);

        array_shift($parts); array_shift($parts); array_shift($parts);

        $segments = collect($parts)->map(fn ($p) => strtolower($p)); 

        $base = preg_replace('/Controller$/i', '', $name);

        $base = Str::kebab($base);

        $segments->push($base);

        $route = $segments->implode('/');

        Route::get("$route", [$controller, 'index']);
        Route::post("$route", [$controller, 'store']);
        Route::get("$route/{id}", [$controller, 'show']);
        Route::put("$route/{id}", [$controller, 'update']);
        Route::delete("$route/{id}", [$controller, 'destroy']);

        Route::get("$route/trashed", [$controller, 'trashed']);
        Route::post("$route/{id}/restore", [$controller, 'restore']);
        Route::delete("$route/{id}/force", [$controller, 'forceDelete']);

        Route::post("$route/upload", [$controller, 'uploadFile']);
        Route::post("$route/{id}/upload", [$controller, 'updateFile']);
        Route::post("$route/uploads/multiple", [$controller, 'uploadMultipleFiles']);
        Route::get("$route/download/{id}", [$controller, 'downloadFile']);
        Route::delete("$route/delete-file/{id}", [$controller, 'deleteFile']);
    }
}