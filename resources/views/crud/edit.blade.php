<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit {{ $resourceLabel }}</title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; background: #f5f2ed; color: #1a1a1a; margin: 0; }
        .container { max-width: 720px; margin: 0 auto; padding: 32px 20px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #eee3d5; }
        .title { margin: 0 0 12px; font-size: 26px; }
        .field { margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #6a6258; }
        input { padding: 10px; border-radius: 6px; border: 1px solid #d9d1c7; }
        .actions { display: flex; gap: 10px; margin-top: 18px; }
        .btn { display: inline-block; padding: 10px 14px; background: #2c3e50; color: #fff; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; }
        .btn-ghost { background: transparent; color: #2c3e50; border: 1px solid #2c3e50; }
        .errors { background: #fbeaea; border: 1px solid #f2b8b8; color: #7b2c2c; padding: 12px; border-radius: 6px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1 class="title">Edit {{ $resourceLabel }}</h1>
        @include('autocrud::crud._form', [
            'action' => route($routeNamePrefix . $routeBase . '.update', $item->getKey()),
            'method' => 'PUT',
            'item' => $item,
            'fields' => $fields,
            'routeBase' => $routeBase,
            'routeNamePrefix' => $routeNamePrefix,
        ])
    </div>
</div>
</body>
</html>
