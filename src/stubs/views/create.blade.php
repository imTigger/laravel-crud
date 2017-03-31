@extends("{$viewPrefix}.form")

@section('page-title', trans('backend.action.create', ['name' => trans($entityName)]))

@push('breadcrumb')
<li class="breadcrumb-item">{{ trans('backend.action.create', ['name' => trans($entityName)]) }}</li>
@endpush

@push('actions')
<button type="submit" name="form_submit" class="btn-success btn">{{ trans('backend.button.create') }}</button>
@endpush