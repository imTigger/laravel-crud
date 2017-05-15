@extends("{$viewPrefix}.layout")

@section('page-title', trans("{$entityName}"))

@section('actions')
    <a href="{{ route("{$routePrefix}.create") }}" class="btn btn-success navbar-btn"><i class="glyphicon glyphicon-plus"></i> {{ trans('backend.button.create') }}</a>
@endsection

@section('content')
    @include('admin.components.message')

    <table class="table table-striped table-bordered table-hover search-header" id="dataTables" style="width: 100%" data-ajax="{{ route("{$routePrefix}.ajax.list") }}" data-processing="true" data-server-side="true">
        <thead>
        <tr>
            <th data-data="id" width="50">{{ trans('$TRANSLATION_PREFIX$.id') }}</th>
            <th data-data="name">{{ trans('$TRANSLATION_PREFIX$.name') }}</th>
            <th data-data="actions" data-searchable="false" data-sortable="false" data-class-name="text-center" width="180">{{ trans('$TRANSLATION_PREFIX$.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
@endsection

@push('js')
<script>
    $().ready(function () {
        $('#dataTables').dataTable();
    });
</script>
@endpush