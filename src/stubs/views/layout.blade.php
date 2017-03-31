@extends('admin.layout')

@push('breadcrumb')
<li class="breadcrumb-item">{{ trans($entityName) }}</li>
@endpush