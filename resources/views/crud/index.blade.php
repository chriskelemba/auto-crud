<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resourceLabel }}</title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; background: #f5f2ed; color: #1a1a1a; margin: 0; }
        .container { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .title { font-size: 28px; margin: 0; }
        .btn { display: inline-block; padding: 10px 14px; background: #2c3e50; color: #fff; text-decoration: none; border-radius: 6px; }
        .notice { background: #e6f4ea; border: 1px solid #b7e1c1; color: #0f5132; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #e6e2db; text-align: left; padding: 10px; vertical-align: top; }
        th { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #5a5248; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .link { color: #2c3e50; text-decoration: none; font-weight: 600; }
        .btn-danger { background: #7b2c2c; color: #fff; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; }
        form { margin: 0; }
        @media (max-width: 720px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { margin-bottom: 16px; background: #fff; padding: 10px; border: 1px solid #eee3d5; border-radius: 8px; }
            td { border: none; padding: 6px 0; }
            .actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1 class="title">{{ $resourceLabel }}</h1>
        <a class="btn" href="{{ route($routeNamePrefix . $routeBase . '.create') }}">Create New</a>
    </div>

    @if(session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if($items->isEmpty())
        <p>No records found.</p>
    @else
        <table>
            <thead>
            <tr>
                @foreach($fields as $field)
                    <th>{{ $field }}</th>
                @endforeach
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    @foreach($fields as $field)
                        <td>{{ data_get($item, $field) }}</td>
                    @endforeach
                    <td>
                        <div class="actions">
                            <a class="link" href="{{ route($routeNamePrefix . $routeBase . '.show', $item->getKey()) }}">View</a>
                            <a class="link" href="{{ route($routeNamePrefix . $routeBase . '.edit', $item->getKey()) }}">Edit</a>
                            <form method="POST" action="{{ route($routeNamePrefix . $routeBase . '.destroy', $item->getKey()) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" type="submit" onclick="return confirm('Delete this record?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
