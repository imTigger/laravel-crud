@extends("{$viewPrefix}.form")

@section('page-title', trans('backend.action.edit', ['name' => trans($entityName)]))

@push('breadcrumb')
<li class="breadcrumb-item">{{ trans('backend.action.edit', ['name' => trans($entityName)]) }}</li>
@endpush

@push('actions')
<button type="submit" class="btn-success btn">{{ trans('backend.button.save') }}</button>
@endpush