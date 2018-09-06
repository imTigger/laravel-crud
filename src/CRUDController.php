<?php

namespace Imtigger\LaravelCRUD;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use Kris\LaravelFormBuilder\Form;
use Kris\LaravelFormBuilder\FormBuilderTrait;
use Yajra\DataTables\Facades\DataTables;

/**
 * Generic CRUD Controller with overridable options
 * With searchable DataTables index page
 *
 * Interoperable with:
 * Laravel DataTables - https://github.com/yajra/laravel-datatables
 * DataTables - https://github.com/DataTables/DataTables
 * Laravel Form Builder - https://github.com/kristijanhusak/laravel-form-builder
 *
 * @property string $viewPrefix Prefix of blade view
 * @property string $routePrefix Prefix of route name
 * @property string $entityName Entity Name
 * @property \Eloquent $entityClass Entity Class
 * @property string $formClass Laravel Form Builder Class
 * @property bool $isCreatable Enable create operation, default: true
 * @property bool $isEditable Enable edit operation, default: true
 * @property bool $isViewable Enable view operation, default: true
 * @property bool $isDeletable Enable delete operation, default: false
 * @property array $rawColumns Columns that do not enable XSS protection by Laravel DataTables (7.0+)
 */
abstract class CRUDController extends BaseController
{
    use FormBuilderTrait;

    const ACTION_INDEX = 'read';
    const ACTION_SHOW = 'read';
    const ACTION_CREATE = 'write';
    const ACTION_STORE = 'write';
    const ACTION_EDIT = 'write';
    const ACTION_UPDATE = 'write';
    const ACTION_DELETE = 'write';

    protected $isCreatable = true;
    protected $isEditable = true;
    protected $isViewable = true;
    protected $isDeletable = false;
    protected $rawColumns = ['actions'];
    protected $with = [];
    protected $data = [];

    public function __construct() {
        if (!property_exists($this, 'viewPrefix')) throw new \Exception("viewPrefix not defined");
        if (!property_exists($this, 'routePrefix')) throw new \Exception("entityClass not defined");
        if (!property_exists($this, 'entityName')) throw new \Exception("entityName not defined");
        if (!property_exists($this, 'entityClass')) throw new \Exception("entityClass not defined");
        if (($this->isCreatable || $this->isEditable || $this->isViewable || $this->isDeletable) && !property_exists($this, 'formClass')) throw new \Exception("formClass not defined");
        
        $this->shareViewData();
    }

    /**
     * @param $prefix
     * @param $controller
     * @param $as
     *
     * Shortcut for creating group of named route
     */
    public static function routes($prefix, $controller, $as) {
        $prefix_of_prefix = substr(strrev(strstr(strrev($as), '.', false)), 0, -1);
        \Route::get("{$prefix}/delete/{id}", ['as' => "{$as}.delete", 'uses' => "{$controller}@delete", 'laroute' => true]);
        \Route::get("{$prefix}/ajax/list", ['as' => "{$as}.ajax.list", 'uses' => "{$controller}@ajaxList", 'laroute' => true]);
        \Route::resource("{$prefix}", "{$controller}", ['as' => $prefix_of_prefix]);
    }
    
    /**
     * Share data with views
     */
    public function shareViewData() {
        $this->data['viewPrefix'] = $this->viewPrefix;
        $this->data['routePrefix'] = $this->routePrefix;
        $this->data['entityName'] = $this->entityName;

        $this->data['isCreatable'] = $this->isCreatable;
        $this->data['isEditable'] = $this->isEditable;
        $this->data['isViewable'] = $this->isViewable;
        $this->data['isDeletable'] = $this->isDeletable;
    }

    /**
     * HTTP index handler
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function index() {
        if (!$this->havePermission(self::ACTION_INDEX, null)) {
            abort(403);
        }

        return view("{$this->viewPrefix}.index", $this->data);
    }

    /**
     * HTTP show handler
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function show($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isViewable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_SHOW, $entity)) {
            abort(403);
        }

        $form = $this->showForm($entity, $id);
        $form->disableFields();

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = 'show';

        return view("{$this->viewPrefix}.show", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in show
     * Override this method to modify the form displayed in show
     *
     * @param Model $entity
     * @param int $id
     * @return \Kris\LaravelFormBuilder\Form
     */
    protected function showForm($entity, $id) {
        return $this->form($this->formClass, [
            'method' => 'get',
            'url' => route("$this->routePrefix.show", $id),
            'model' => $entity
        ], ['entity' => $entity]);
    }

    /**
     * HTTP create handler
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function create() {
        if (!$this->isCreatable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_CREATE)) {
            abort(403);
        }

        $form = $this->createForm();

        $this->data['form'] = $form;
        $this->data['action'] = 'create';

        return view("{$this->viewPrefix}.create", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in create
     * Override this method to modify the form displayed in create
     *
     * @return Form
     */
    protected function createForm() {
        $form = $this->form($this->formClass, [
            'method' => 'post',
            'url' => route("$this->routePrefix.store")
        ]);

        return $form;
    }

    /**
     * HTTP store handler
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store()
    {
        if (!$this->isCreatable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_STORE)) {
            abort(403);
        }

        $form = $this->storeForm();

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $this->storeSave();

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.create_success', ['name' => trans($this->entityName)]));
    }

    /**
     * Return LaravelFormBuilder Form used in store validation
     * Override this method to modify the validation behavior in store
     *
     * @return Form
     */
    protected function storeForm() {
        $form = $this->form($this->formClass, [
            'method' => 'post'
        ]);

        return $form;
    }

    /**
     * Trigger when store method
     * Override this method to add additinal operations
     *
     * @return Model
     */
    protected function storeSave() {
        /** @var Model $entity */
        $entity = new $this->entityClass;

        $fillables = collect($entity->getFillable());

        foreach ($fillables As $fillable) {
            if (Input::exists($fillable)) {
                $entity->$fillable = Input::get($fillable);
            }
        }

        $entity->save();

        return $entity;
    }

    /**
     * HTTP edit handler
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function edit($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isEditable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_EDIT, $entity)) {
            abort(403);
        }

        $form = $this->editForm($entity);

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = 'edit';

        return view("{$this->viewPrefix}.edit", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in edit
     * Override this method to modify the form displayed in store
     *
     * @param Model $entity
     * @param int $id
     * @return Form
     */
    protected function editForm($entity) {
        $form = $this->form($this->formClass, [
            'method' => 'patch',
            'url' => route("$this->routePrefix.update", $entity->id),
            'model' => $entity
        ], ['entity' => $entity]);

        return $form;
    }

    /**
     * HTTP update handler
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function update($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isEditable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_UPDATE, $entity)) {
            abort(403);
        }

        $form = $this->updateForm($entity);

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $this->updateSave($entity);

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.edit_success', ['name' => trans($this->entityName)]));
    }

    /**
     * Return LaravelFormBuilder Form used in update validation
     * Override this method to modify the validation behavior in update
     *
     * @return Form
     */
    protected function updateForm($entity) {
        $form = $this->form($this->formClass, [
            'method' => 'patch',
            'model' => $entity
        ], ['entity' => $entity]);

        return $form;
    }

    /**
     * Trigger when update method
     * Override this method to add additinal operations
     *
     * @param Model $entity
     * @return Model $entity
     */
    protected function updateSave($entity) {
        $fillables = collect($entity->getFillable());

        foreach ($fillables As $fillable) {
            if (Input::exists($fillable)) {
                $entity->$fillable = Input::get($fillable);
            }
        }

        $entity->save();

        return $entity;
    }

    /**
     * HTTP delete handler
     *
     * @param int $id
     * @return mixed
     * @throws \Exception
     */
    public function delete($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isDeletable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_DELETE, $entity)) {
            abort(403);
        }

        $form = $this->deleteForm($entity, $id);
        $form->disableFields();

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = 'show';

        return view("{$this->viewPrefix}.delete", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in delete
     * Override this method to modify the form displayed in delete
     *
     * @param Model $entity
     * @param int $id
     * @return \Kris\LaravelFormBuilder\Form
     */
    protected function deleteForm($entity, $id) {
        return $this->form($this->formClass, [
            'method' => 'delete',
            'url' => route("$this->routePrefix.destroy", $id),
            'model' => $entity
        ], ['entity' => $entity]);
    }

    /**
     * HTTP destroy handler
     *
     * @param int $id
     * @return mixed
     * @throws \Exception
     */
    public function destroy($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isDeletable) {
            abort(404);
        }

        if (!$this->havePermission(self::ACTION_DELETE, $entity)) {
            abort(403);
        }

        $this->destroySave($entity);

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.delete_success', ['name' => trans($this->entityName)]));
    }

    /**
     * Trigger when destroy method
     * Override this method to add additional operations
     *
     * @param Model $entity
     * @throws \Exception
     */
    protected function destroySave($entity) {
        $entity->delete();
    }

    /**
     * HTTP ajax.list query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function ajaxListQuery() {
        $query = ($this->entityClass)::query();

        // Add 'with' relations
        if (is_array($this->with) && !empty($this->with)) {
            foreach ($this->with as $relation) {
                $query->with($relation);
            }
        }

        return $query;
    }

    /**
     * Extra DataTables action field, append string after default actions
     *
     * @param $item
     * @return string
     */
    protected function ajaxListActions($item)
    {
        return
            ($this->isViewable ? '<a href="' . route("{$this->routePrefix}.show", [$item->id]) .'" class="btn btn-xs btn-success"><i class="glyphicon glyphicon-eye-open"></i> ' . trans('laravel-crud::ui.button.view') . '</a> ' : '') .
            ($this->isEditable ? '<a href="' . route("{$this->routePrefix}.edit", [$item->id]) .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> ' . trans('laravel-crud::ui.button.edit') . '</a> ' : '') .
            ($this->isDeletable ? '<a href="' . route("{$this->routePrefix}.delete", [$item->id]) .'" class="btn btn-xs btn-danger"><i class="glyphicon glyphicon-trash"></i> ' . trans('laravel-crud::ui.button.delete') . '</a> ' : '');
    }

    /**
     * Construct DataTable object
     *
     * @param $items
     * @return \Yajra\DataTables\DataTableAbstract
     * @throws \Exception
     */
    protected function ajaxListDataTable($items) {
        /** @var \Yajra\DataTables\DataTableAbstract $datatable */
        $datatable = DataTables::of($items)
            ->addColumn('actions', function ($item) {
                return $this->ajaxListActions($item);
            });

        // Set rawColumns
        if (is_array($this->rawColumns) && !empty($this->rawColumns)) {
            $datatable->rawColumns($this->rawColumns);
        }

        return $datatable;
    }

    /**
     * HTTP ajax.list handler
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function ajaxList() {
        if (!$this->havePermission(self::ACTION_INDEX, null)) {
            abort(403);
        }

        $items = $this->ajaxListQuery();

        return $this->ajaxListDataTable($items)->make(true);
    }

    /**
     * Check if user have permission
     * Override this method to add checking
     *
     * @param string $action
     * @param Model $entity
     * @return bool
     * @throws \Exception
     */
    protected function havePermission($action, $entity = null) {
        if (property_exists($this, 'permissionPrefix')) throw new \Exception('Controller defined permissionPrefix but do not have any checking. Perhaps we should add "use UseLaratustPermission"?');
        
        return true;
    }
}