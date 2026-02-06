<?php

namespace AutoCrud\Support;

use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseFormatter
{
    public function success($data = null, string $message = 'Success', int $code = 200)
    {
        [$payload, $meta, $links] = $this->normalize($data, $message);

        $response = [
            'data' => $payload,
            'meta' => $meta,
        ];

        if (!empty($links)) {
            $response['links'] = $links;
        }

        return response()->json($response, $code);
    }

    public function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        $error = [
            'status' => (string) $code,
            'detail' => $message,
        ];

        if ($errors !== null) {
            $error['meta'] = ['errors' => $errors];
        }

        return response()->json(['errors' => [$error]], $code);
    }

    private function normalize($data, string $message): array
    {
        $payload = null;
        $meta = ['message' => $message];
        $links = [];

        if ($data instanceof JsonResource) {
            $payload = $data->resolve();
        } elseif ($data instanceof PaginatorContract) {
            $payload = $data->items();
        } else {
            $payload = $data;
        }

        $paginator = $this->extractPaginator($data);
        if ($paginator) {
            $meta['pagination'] = [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ];
            $links = [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        }

        return [$payload, $meta, $links];
    }

    private function extractPaginator($data)
    {
        if ($data instanceof PaginatorContract) {
            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                if ($value instanceof PaginatorContract) {
                    return $value;
                }
                if ($value instanceof JsonResource) {
                    $resource = $value->resource;
                    if ($resource instanceof PaginatorContract) {
                        return $resource;
                    }
                }
            }
        }

        if ($data instanceof JsonResource && $data->resource instanceof PaginatorContract) {
            return $data->resource;
        }

        return null;
    }
}
