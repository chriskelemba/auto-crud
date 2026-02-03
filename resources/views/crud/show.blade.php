<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resourceLabel }} Details</title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; background: #f5f2ed; color: #1a1a1a; margin: 0; }
        .container { max-width: 720px; margin: 0 auto; padding: 32px 20px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #eee3d5; }
        .title { margin: 0 0 12px; font-size: 26px; }
        .row { display: flex; justify-content: space-between; gap: 16px; padding: 8px 0; border-bottom: 1px solid #f1ece3; }
        .row:last-child { border-bottom: none; }
        .label { color: #6a6258; font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; }
        .value { font-weight: 600; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 14px; background: #2c3e50; color: #fff; text-decoration: none; border-radius: 6px; }
        .btn-ghost { background: transparent; color: #2c3e50; border: 1px solid #2c3e50; }
        .btn-danger { background: #7b2c2c; color: #fff; border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
        form { margin: 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1 class="title">{{ $resourceLabel }} Details</h1>

        @foreach($fields as $field)
            <div class="row">
                <div class="label">{{ $field }}</div>
                <div class="value">{{ data_get($item, $field) }}</div>
            </div>
        @endforeach

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route($routeNamePrefix . $routeBase . '.index') }}">Back</a>
            <a class="btn" href="{{ route($routeNamePrefix . $routeBase . '.edit', $item->getKey()) }}">Edit</a>
            <form method="POST" action="{{ route($routeNamePrefix . $routeBase . '.destroy', $item->getKey()) }}">
                @csrf
                @method('DELETE')
                <button class="btn-danger" type="submit" onclick="return confirm('Delete this record?')">Delete</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
