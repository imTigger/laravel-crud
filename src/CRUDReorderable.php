<?php

namespace Imtigger\LaravelCRUD;

use Dimsav\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;

trait CRUDReorderable {

    public function ajaxReorder()
    {
        $changes = array_pluck(Input::all(), 'newData', 'oldData');

        $rows = ($this->entityClass)::whereIn('position', array_keys($changes))->get();
        foreach ($rows as $row) {
            $row->update(['position' => $changes[$row->position]]);
        };

        return ['status' => 0];
    }
}