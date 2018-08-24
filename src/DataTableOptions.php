<?php
namespace App\Models;


trait DataTableOptions
{
    static function dataTableOptions($name = 'name') {
        $items = static::get();

        $options = [];
        foreach ($items As $item) {
            $options[] = ['key' => $item->id, 'value' => $item->$name];
        }

        return $options;
    }
}