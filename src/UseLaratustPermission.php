<?php

namespace Imtigger\LaravelCRUD;

use Illuminate\Support\Facades\Auth;

trait UseLaratustPermission {

    protected function havePermission($action, $entity = null)
    {
        return Auth::user()->can("{$this->permissionPrefix}.{$action}");
    }
}