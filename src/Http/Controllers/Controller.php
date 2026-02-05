<?php

namespace AutoCrud\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use AutoCrud\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use ChrisKelemba\ResponseApi\JsonApi;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    protected CrudService $service;
    protected Model $model;
    protected array $with = [];
    protected ?string $orderBy = null;
    protected ?string $resourceName = null;
    protected ?string $resourceClass = null;
    protected array $rules = [];
    protected array $webFields = [];

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
    public function index(Request $request)
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

        if ($this->prefersJson($request)) {
            $query = JsonApi::applyQuery($query, $request, $this->routeBase(), [
                'allowed_sorts' => config('autocrud.api.allowed_sorts', []),
                'allowed_filters' => config('autocrud.api.allowed_filters', []),
                'allowed_includes' => config('autocrud.api.allowed_includes', []),
                'allowed_fields' => config('autocrud.api.allowed_fields', []),
            ]);

            $perPage = $request->input('page.size');
            $perPage = is_numeric($perPage)
                ? (int) $perPage
                : (int) $request->integer('per_page', (int) config('autocrud.api.per_page', 15));

            $pageNumber = $request->input('page.number');
            $pageNumber = is_numeric($pageNumber) ? (int) $pageNumber : null;

            $items = $query->paginate($perPage, ['*'], 'page', $pageNumber);
        } else {
            $items = $query->get();
        }

        if (!$this->prefersJson(request())) {
            return view('autocrud::crud.index', $this->viewData([
                'items' => $items,
            ]));
        }

        return $this->successResponse(
            $items,
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
            if ($this->prefersJson(request())) {
                return $this->errorResponse('Not found', 404);
            }

            abort(404);
        }

        if (!$this->prefersJson(request())) {
            return view('autocrud::crud.show', $this->viewData([
                'item' => $item,
            ]));
        }

        return $this->successResponse(
            $item,
            ucfirst($this->resourceName) . 's retrieved successfully.'
        );
    }

    /**
     * Show create form (web)
     */
    public function create()
    {
        return view('autocrud::crud.create', $this->viewData([
            'item' => $this->model->newInstance(),
        ]));
    }

    /**
     * Show edit form (web)
     */
    public function edit($id)
    {
        $item = $this->model->find($id);
        if (!$item) {
            abort(404);
        }

        return view('autocrud::crud.edit', $this->viewData([
            'item' => $item,
        ]));
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

            if (!$this->prefersJson($request)) {
                return redirect()
                    ->route($this->webRouteName('index'))
                    ->with('status', ucfirst($this->resourceName) . ' created successfully.');
            }

            return $this->successResponse(
                $item,
                ucfirst($this->resourceName) . ' created successfully.',
                201
            );
        } catch (QueryException $e) {
            if (!$this->prefersJson($request)) {
                return back()->withErrors(['error' => 'Failed to create record'])->withInput();
            }

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
                if ($this->prefersJson($request)) {
                    return $this->errorResponse('Not found', 404);
                }

                abort(404);
            }

            $data = $request->except(['_token', '_method']);
            
            if (empty($data)) {
                return $this->errorResponse('No data provided', 422);
            }
            
            $item->update($data);

            if (!$this->prefersJson($request)) {
                return redirect()
                    ->route($this->webRouteName('index'))
                    ->with('status', ucfirst($this->resourceName) . ' updated successfully.');
            }

            return $this->successResponse(
                $item,
                ucfirst($this->resourceName) . ' updated successfully.'
            );
        } catch (QueryException $e) {
            if (!$this->prefersJson($request)) {
                return back()->withErrors(['error' => 'Failed to update record'])->withInput();
            }

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
                if ($this->prefersJson($request)) {
                    return $this->errorResponse('Not found', 404);
                }

                abort(404);
            }

            $item->delete();

            if (!$this->prefersJson($request)) {
                return redirect()
                    ->route($this->webRouteName('index'))
                    ->with('status', ucfirst($this->resourceName) . ' deleted successfully.');
            }

            return $this->successResponse(
                null,
                ucfirst($this->resourceName) . ' deleted successfully.',
                204
            );
        } catch (QueryException $e) {
            if (!$this->prefersJson($request)) {
                return back()->withErrors(['error' => 'Failed to delete record']);
            }

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
                $items,
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
                $item,
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
                ucfirst($this->resourceName) . ' permanently deleted.',
                204
            );
        } catch (\BadMethodCallException $e) {
            return $this->errorResponse('Soft deletes not enabled for this model', 400);
        } catch (QueryException $e) {
            return $this->errorResponse('Failed to permanently delete record', 500, $e->getMessage());
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
        if ($code === 204) {
            return JsonApi::response(null, null, null, 204);
        }

        $document = JsonApi::document($data, $this->routeBase(), request());
        if (! isset($document['meta']) || ! is_array($document['meta'])) {
            $document['meta'] = [];
        }
        $document['meta']['message'] = $message;

        return JsonApi::response($document, null, null, $code);
    }

    /**
     * Send an error response
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null)
    {
        $detail = is_string($errors) ? $errors : null;
        $meta = is_array($errors) ? ['errors' => $errors] : [];

        $error = JsonApi::error($code, $message, $detail, null, null, $meta);

        return JsonApi::responseErrors([$error], $code);
    }

    protected function prefersJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->is('api/*');
    }

    protected function viewData(array $data = []): array
    {
        $fields = !empty($this->webFields) ? $this->webFields : $this->model->getFillable();
        $routeBase = $this->routeBase();

        return array_merge([
            'items' => collect(),
            'item' => null,
            'fields' => $fields,
            'resourceLabel' => Str::headline(Str::singular($routeBase)),
            'routeBase' => $routeBase,
            'routeNamePrefix' => config('autocrud.web.route_name_prefix', 'web.'),
        ], $data);
    }

    protected function routeBase(): string
    {
        $controller = class_basename(static::class);
        $base = Str::replaceLast('Controller', '', $controller);
        return Str::kebab(Str::plural($base));
    }

    protected function webRouteName(string $action): string
    {
        return config('autocrud.web.route_name_prefix', 'web.')
            . $this->routeBase()
            . '.'
            . $action;
    }
}
