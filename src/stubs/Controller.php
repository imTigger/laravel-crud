<?php

namespace App\Http\Controllers\Admin;

use App\Forms\$FORM_NAME$;
use App\Models\$MODEL_NAME$;
use Imtigger\LaravelCRUD\CRUDController;
use Kris\LaravelFormBuilder\FormBuilderTrait;

class $CONTROLLER_NAME$ extends CRUDController
{
    use FormBuilderTrait;

    protected $viewPrefix = '$VIEW_PREFIX$';
    protected $routePrefix = '$ROUTE_PREFIX$';
    protected $entityName = '$ENTITY_NAME$';
    protected $entityClass = $MODEL_NAME$::class;
    protected $formClass = $FORM_NAME$::class;
}