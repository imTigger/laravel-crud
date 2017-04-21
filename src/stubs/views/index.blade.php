@extends("{$viewPrefix}.layout")

@section('page-title', trans("{$entityName}"))

@section('actions')
    <a href="{{ route("{$routePrefix}.create") }}" class="btn btn-success navbar-btn"><i class="glyphicon glyphicon-plus"></i> {{ trans('backend.button.create') }}</a>
@endsection

@section('content')
    @include('admin.components.message')

    <table class="table table-striped table-bordered table-hover" id="dataTables" style="width: 100%">
        <thead>
        <tr>
            <th data-datatable-id="id" width="50">{{ trans('$TRANSLATION_PREFIX$.id') }}</th>
            <th data-datatable-id="name">{{ trans('$TRANSLATION_PREFIX$.name') }}</th>
            <th data-datatable-id="actions" data-datatable-searchable="false" data-datatable-sortable="false" data-datatable-class="dt-center" width="180">{{ trans('$TRANSLATION_PREFIX$.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
@endsection

@push('js')
<script>
    $().ready(function () {
        $('#dataTables').datatableify({
            ajaxLoadUrl: laroute.route('{!! "{$routePrefix}.ajax.list" !!}'),
            language: {
                "url": laroute.route('admin.datatables.language')
            }
        });
    });
</script>
@endpush