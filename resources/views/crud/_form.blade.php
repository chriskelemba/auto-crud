@if($errors->any())
    <div class="errors">
        <strong>There were some problems with your input:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @foreach($fields as $field)
        <div class="field">
            <label for="{{ $field }}">{{ $field }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field, data_get($item, $field)) }}">
        </div>
    @endforeach

    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-ghost" href="{{ route($routeNamePrefix . $routeBase . '.index') }}">Cancel</a>
    </div>
</form>
