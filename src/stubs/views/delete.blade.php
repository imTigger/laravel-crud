@extends("{$viewPrefix}.form")

@section('page-title', trans('backend.action.delete', ['name' => trans($entityName)]))

@push('actions')
<button type="submit" class="btn-danger btn">{{ trans('backend.button.delete') }}</button>
@endpush