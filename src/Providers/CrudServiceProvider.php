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

        $base = preg_replace('/Controller$/i', '', $name);
        $base = Str::kebab(Str::plural($base));

        Route::get("$base", [$controller, 'index']);
        Route::post("$base", [$controller, 'store']);
        Route::get("$base/{id}", [$controller, 'show']);
        Route::put("$base/{id}", [$controller, 'update']);
        Route::delete("$base/{id}", [$controller, 'destroy']);

        Route::get("$base/trashed", [$controller, 'trashed']);
        Route::post("$base/{id}/restore", [$controller, 'restore']);
        Route::delete("$base/{id}/force", [$controller, 'forceDelete']);

        Route::post("$base/upload", [$controller, 'uploadFile']);
        Route::post("$base/{id}/upload", [$controller, 'updateFile']);
        Route::post("$base/uploads/multiple", [$controller, 'uploadMultipleFiles']);
        Route::get("$base/download/{id}", [$controller, 'downloadFile']);
        Route::delete("$base/delete-file/{id}", [$controller, 'deleteFile']);
    }
}