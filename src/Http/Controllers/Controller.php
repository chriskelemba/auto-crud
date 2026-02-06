<?php

namespace AutoCrud\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use AutoCrud\Services\CrudService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controller as BaseController;
use AutoCrud\Support\ResponseFormatter;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;

abstract class Controller extends BaseController
{
    protected CrudService $service;
    protected Model $model;
    protected array $with = [];
    protected ?string $orderBy = null;
    protected ?string $resourceName = null;
    protected ?string $resourceClass = null;
    protected array $rules = [];
    protected ?object $responseFormatter = null;

    public function __construct()
    {
        $this->initializeModel();
        $this->initializeResource();
        $this->service = new CrudService($this->model, $this->rules);
    }

    /**
     * Initialize the model instance
     */
    protected function initializeModel(): void
    {
        if (isset($this->model)) {
            if (is_string($this->model)) {
                $this->model = new $this->model;
            }
            return;
        }

        $controller = class_basename(static::class);
        $modelName = Str::replaceLast('Controller', '', $controller);
        
        // Get the full namespace of the controller
        $controllerNamespace = get_class($this);
        
        // Try to find the model in multiple locations
        $modelClass = $this->resolveModelClass($controllerNamespace, $modelName);

        if (!class_exists($modelClass)) {
            throw new \Exception("Model {$modelClass} not found. Please ensure the model exists or define a protected \$model property in your controller.");
        }

        $this->model = new $modelClass;
    }

    /**
     * Resolve the model class from various possible locations
     */
    protected function resolveModelClass(string $controllerNamespace, string $modelName): string
    {
        if (preg_match('/^(.+)\\\\Controllers\\\\/', $controllerNamespace, $matches)) {
            $baseNamespace = $matches[1];

            $modelClass = "{$baseNamespace}\\{$modelName}";
            if (class_exists($modelClass)) {
                return $modelClass;
            }

            $modelClass = "{$baseNamespace}\\Models\\{$modelName}";
            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        $modelClass = "App\\Models\\{$modelName}";
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        $modelClass = "App\\{$modelName}";
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        return "App\\Models\\{$modelName}";
    }

    /**
     * Initialize resource class for transformation
     */
    protected function initializeResource(): void
    {
        $this->resourceName = $this->resourceName ?? Str::camel(class_basename($this->model));
        
        $modelBaseName = class_basename($this->model);
        $resourceName = "{$modelBaseName}Resource";

        $modelNamespace = get_class($this->model);

        if (preg_match('/^(.+)\\\\(?:Models\\\\)?/', $modelNamespace, $matches)) {
            $baseNamespace = rtrim($matches[1], '\\');
            $possibleResource = "{$baseNamespace}\\Resources\\{$resourceName}";
            
            if (class_exists($possibleResource)) {
                $this->resourceClass = $possibleResource;
                return;
            }
        }

        $possibleResource = "App\\Http\\Resources\\{$resourceName}";
        if (class_exists($possibleResource)) {
            $this->resourceClass = $possibleResource;
            return;
        }

        $this->resourceClass = null;
    }

    /**
     * Transform data using resource class if available
     */
    protected function transform($data)
    {
        if ($this->resourceClass) {
            $resource = $this->resourceClass;
            if ($data instanceof PaginatorContract) {
                return $resource::collection($data);
            }
            return $data instanceof \Illuminate\Support\Collection
                ? $resource::collection($data)
                : new $resource($data);
        }

        return $data;
    }

    /* -------------------------------------------------------------------------
     |  CRUD Operations
     |------------------------------------------------------------------------*/

    /**
     * Display a listing of the resource
     */
    public function index()
    {
        $query = $this->model->newQuery();

        // Eager load relations
        if (!empty($this->with)) {
            $query->with($this->with);
        }

        // Apply ordering if specified
        if ($this->orderBy) {
            $query->orderBy($this->orderBy, 'desc');
        }

        $perPage = (int) config('autocrud.api.per_page', 10);
        $items = $query->paginate($perPage);

        return $this->successResponse(
            [$this->resourceName => $this->transform($items)],
            ucfirst($this->resourceName) . 's fetched successfully.'
        );
    }

    /**
     * Display the specified resource
     */
    public function show($id)
    {
        $query = $this->model->newQuery();

        if (!empty($this->with)) {
            $query->with($this->with);
        }

        $item = $query->find($id);

        if (!$item) {
            return $this->errorResponse('Not found', 404);
        }

        return $this->successResponse(
            [$this->resourceName => $this->transform($item)],
            ucfirst($this->resourceName) . 's retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource
     */
    public function store(Request $request)
    {
        try {
            $data = $request->except(['_token', '_method']);
            
            if (empty($data)) {
                return $this->errorResponse('No data provided', 422);
            }
            
            $item = $this->model->create($data);

            return $this->successResponse(
                [$this->resourceName => $this->transform($item)],
                ucfirst($this->resourceName) . ' created successfully.',
                201
            );
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to create record', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified resource
     */
    public function update(Request $request, $id)
    {
        try {
            $item = $this->model->find($id);

            if (!$item) {
                return $this->errorResponse('Not found', 404);
            }

            $data = $request->except(['_token', '_method']);
            
            if (empty($data)) {
                return $this->errorResponse('No data provided', 422);
            }
            
            $item->update($data);

            return $this->successResponse(
                [$this->resourceName => $this->transform($item)],
                ucfirst($this->resourceName) . ' updated successfully.'
            );
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to update record', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = $this->model->find($id);

            if (!$item) {
                return $this->errorResponse('Not found', 404);
            }

            $item->delete();

            return $this->successResponse(
                null,
                ucfirst($this->resourceName) . ' deleted successfully.'
            );
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to delete record', 500, $e->getMessage());
        }
    }

    /* -------------------------------------------------------------------------
     |  SOFT DELETE Operations
     |------------------------------------------------------------------------*/

    /**
     * Display a listing of trashed (soft deleted) resources
     */
    public function trashed()
    {
        try {
            $query = $this->model->onlyTrashed();

            if (!empty($this->with)) {
                $query->with($this->with);
            }

            if ($this->orderBy) {
                $query->orderBy($this->orderBy, 'desc');
            }

            $items = $query->get();

            return $this->successResponse(
                [$this->resourceName => $this->transform($items)],
                'Trashed ' . $this->resourceName . ' fetched successfully.'
            );
        } catch (\BadMethodCallException $e) {
            return $this->errorResponse('Soft deletes not enabled for this model', 400);
        }
    }

    /**
     * Restore a soft deleted resource
     */
    public function restore($id)
    {
        try {
            $item = $this->model->onlyTrashed()->find($id);

            if (!$item) {
                return $this->errorResponse('Trashed item not found', 404);
            }

            $item->restore();

            return $this->successResponse(
                [$this->resourceName => $this->transform($item)],
                ucfirst($this->resourceName) . ' restored successfully.'
            );
        } catch (\BadMethodCallException $e) {
            return $this->errorResponse('Soft deletes not enabled for this model', 400);
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to restore record', 500, $e->getMessage());
        }
    }

    /**
     * Permanently delete a soft deleted resource
     */
    public function forceDelete($id)
    {
        try {
            $item = $this->model->withTrashed()->find($id);

            if (!$item) {
                return $this->errorResponse('Not found', 404);
            }

            $item->forceDelete();

            return $this->successResponse(
                null,
                ucfirst($this->resourceName) . ' permanently deleted.'
            );
        } catch (\BadMethodCallException $e) {
            return $this->errorResponse('Soft deletes not enabled for this model', 400);
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to permanently delete record', 500, $e->getMessage());
        }
    }

    /* -------------------------------------------------------------------------
     |  FILE HANDLING
     |------------------------------------------------------------------------*/

    /**
     * Upload a single file
     */
    public function uploadFile(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB
            ]);

            $file = $request->file('file');
            $model = $this->model->newInstance();

            $model->fill([
                'filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientMimeType(),
                'content' => file_get_contents($file->getRealPath()),
            ]);

            $model->save();

            return $this->successResponse(
                [$this->resourceName => $this->transform($model)],
                'File uploaded successfully.'
            );
        } catch (\Throwable $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return $this->errorResponse('File upload failed', 500, $e->getMessage());
        }
    }

    /**
     * Update an existing file
     */
    public function updateFile(Request $request, $id)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB
            ]);

            $model = $this->model->findOrFail($id);
            $file = $request->file('file');

            $model->fill([
                'filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientMimeType(),
                'content' => file_get_contents($file->getRealPath()),
            ]);

            $model->save();

            return $this->successResponse(
                [$this->resourceName => $this->transform($model)],
                'File updated successfully.'
            );
        } catch (\Throwable $e) {
            Log::error('File update error: ' . $e->getMessage());
            return $this->errorResponse('File update failed', 500, $e->getMessage());
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(Request $request)
    {
        try {
            $request->validate([
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|max:10240', // 10MB per file
            ]);

            $files = $request->file('files');

            $uploadedFiles = [];
            $errors = [];

            foreach ($files as $index => $file) {
                try {
                    $newModel = $this->model->newInstance();
                    
                    $newModel->fill([
                        'filename' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getClientMimeType(),
                        'content' => file_get_contents($file->getRealPath()),
                    ]);

                    $newModel->save();
                    $uploadedFiles[] = $this->transform($newModel);
                    
                } catch (\Throwable $e) {
                    Log::error("Failed to upload file at index {$index}: " . $e->getMessage());
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $response = [
                $this->resourceName => $uploadedFiles,
                'total_uploaded' => count($uploadedFiles),
                'total_requested' => count($files),
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['total_failed'] = count($errors);
            }

            $message = count($uploadedFiles) . ' file(s) uploaded successfully';
            if (!empty($errors)) {
                $message .= ', ' . count($errors) . ' failed';
            }

            return $this->successResponse($response, $message, empty($errors) ? 200 : 207);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Multi-file upload error: ' . $e->getMessage());
            return $this->errorResponse('Multi-file upload failed', 500, $e->getMessage());
        }
    }

    /**
     * Download a file
     */
    public function downloadFile($id)
    {
        try {
            $item = $this->model->find($id);
            
            if (!$item || !isset($item->content)) {
                return $this->errorResponse('File not found', 404);
            }

            return response($item->content)
                ->header('Content-Type', $item->file_type)
                ->header('Content-Disposition', 'attachment; filename="' . $item->filename . '"');
        } catch (\Throwable $e) {
            return $this->errorResponse('Download failed', 500, $e->getMessage());
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile($id)
    {
        try {
            $item = $this->model->find($id);
            
            if (!$item) {
                return $this->errorResponse('File not found', 404);
            }

            $item->delete();
            
            return $this->successResponse(null, 'File deleted successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Delete failed', 500, $e->getMessage());
        }
    }

    /* -------------------------------------------------------------------------
     |  RESPONSE HELPERS
     |------------------------------------------------------------------------*/

    /**
     * Send a success response
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200)
    {
        return $this->getResponseFormatter()->success($data, $message, $code);
    }

    /**
     * Send an error response
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null)
    {
        return $this->getResponseFormatter()->error($message, $code, $errors);
    }

    protected function getResponseFormatter(): object
    {
        if ($this->responseFormatter) {
            return $this->responseFormatter;
        }

        $class = config('autocrud.response_formatter', ResponseFormatter::class);

        if (is_string($class) && class_exists($class)) {
            return $this->responseFormatter = app($class);
        }

        return $this->responseFormatter = app(ResponseFormatter::class);
    }
}
