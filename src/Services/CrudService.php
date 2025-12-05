<?php

namespace AutoCrud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class CrudService
{
    protected Model $model;
    protected array $rules;

    public function __construct(Model $model, array $rules = [])
    {
        $this->model = $model;
        $this->rules = $rules;
    }

    public function all(array $with = [], $orderBy = null): Collection
    {
        $query = $this->model->with($with);

        if (is_array($orderBy)) {
            foreach ($orderBy as $column => $direction) {
                $query->orderBy($column, $direction);
            }
        } elseif (is_string($orderBy)) {
            $query->orderBy($orderBy, 'desc');
        } else {
            $query->orderBy($this->model->getKeyName(), 'desc');
        }

        return $query->get();
    }

    public function find($id, array $with = []): ?Model
    {
        return $this->model->with($with)->find($id);
    }

    protected function validate(array $data): array
    {
        if (empty($this->rules)) {
            return $data;
        }

        $validator = Validator::make($data, $this->rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public function create(array $data): Model|\Illuminate\Support\Collection
    {
        $validated = $this->validate($data);

        foreach ($validated as $key => $value) {
            if (is_array($value)) {
                $collection = collect();

                foreach ($value as $singleValue) {
                    $recordData = $validated;
                    $recordData[$key] = $singleValue;

                    $collection->push($this->model->create($recordData));
                }

                return $collection;
            }
        }

        return $this->model->create($validated);
    }

    public function update($id, array $data): Model|\Illuminate\Support\Collection|null
    {
        $validated = $this->validate($data);

        foreach ($validated as $key => $value) {
            if (is_array($value)) {
                $collection = collect();

                foreach ($value as $index => $singleValue) {
                    $recordData = $validated;
                    $recordData[$key] = $singleValue;

                    $targetId = is_array($id) ? ($id[$index] ?? null) : $id;

                    if (!$targetId) {
                        continue;
                    }

                    $record = $this->model->find($targetId);
                    if ($record) {
                        $record->update($recordData);
                        $collection->push($record);
                    }
                }

                return $collection;
            }
        }

        $record = $this->model->find($id);
        if (!$record) {
            return null;
        }

        $record->update($validated);
        return $record;
    }

    public function delete($id): bool
    {
        $record = $this->model->find($id);
        if (!$record) {
            return false;
        }
        return (bool) $record->delete();
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getModelClass(): string
    {
        return get_class($this->model);
    }
}