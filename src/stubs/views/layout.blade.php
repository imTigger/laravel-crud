@extends('admin.layout')

@prepend('breadcrumb')
<li class="breadcrumb-item">{{ trans($entityName) }}</li>
@endprepend