@extends("{$viewPrefix}.layout")

@section('content')
    {!! form_start($form) !!}

    {!! form_rest($form) !!}

    <div class="form-footer">
        @stack('actions')
    </div>

    {!! form_end($form) !!}
@endsection