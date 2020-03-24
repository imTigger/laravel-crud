<?php

namespace Imtigger\LaravelCRUD;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
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

    const ACTION_SHOW = 'show';
    const ACTION_CREATE = 'create';
    const ACTION_STORE = 'store';
    const ACTION_EDIT = 'edit';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    const ACTION_TYPE_INDEX = 'read';
    const ACTION_TYPE_SHOW = 'read';
    const ACTION_TYPE_CREATE = 'write';
    const ACTION_TYPE_STORE = 'write';
    const ACTION_TYPE_EDIT = 'write';
    const ACTION_TYPE_UPDATE = 'write';
    const ACTION_TYPE_DELETE = 'write';

    protected $showButtonIconClass = 'glyphicon glyphicon-eye-open';
    protected $editButtonIconClass = 'glyphicon glyphicon-edit';
    protected $deleteButtonIconClass = 'glyphicon glyphicon-trash';

    protected $showButtonClass = 'btn btn-xs btn-success';
    protected $editButtonClass = 'btn btn-xs btn-primary';
    protected $deleteButtonClass = 'btn btn-xs btn-danger';

    protected $showButtonText = 'laravel-crud::ui.button.view';
    protected $editButtonText = 'laravel-crud::ui.button.edit';
    protected $deleteButtonText = 'laravel-crud::ui.button.delete';

    protected $isCreatable = true;
    protected $isEditable = true;
    protected $isViewable = true;
    protected $isDeletable = false;
    protected $rawColumns = ['actions'];
    protected $makeHiddenColumns = [];
    protected $makeVisibleColumns = [];
    protected $with = [];
    protected $fillable = [];
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
     * Shortcut for creating group of named route
     *
     * @param $prefix
     * @param $controller
     * @param $as
     */
    public static function routes($prefix, $controller, $as) {
        $prefix_of_prefix = substr(strrev(strstr(strrev($as), '.', false)), 0, -1);
        \Route::get("{$prefix}/delete/{id}", ['as' => "{$as}.delete", 'uses' => "{$controller}@delete"]);
        \Route::get("{$prefix}/ajax/list", ['as' => "{$as}.ajax.list", 'uses' => "{$controller}@ajaxList"]);
        \Route::post("{$prefix}/ajax/reorder", ['as' => "{$as}.ajax.reorder", 'uses' => "{$controller}@ajaxReorder"]);
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
        if (!$this->havePermission(static::ACTION_TYPE_INDEX, null)) {
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

        if (!$this->havePermission(static::ACTION_TYPE_SHOW, $entity)) {
            abort(403);
        }

        $form = $this->showForm($entity, $id);
        $form->disableFields();

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = static::ACTION_SHOW;

        return $this->showView();
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
            'url' => route("{$this->routePrefix}.show", $id),
            'model' => $entity
        ], ['entity' => $entity, 'action' => static::ACTION_SHOW]);
    }

    /**
     * Return show view
     * Override this method to change view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function showView() {
        return view("{$this->viewPrefix}.show", $this->data);
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

        if (!$this->havePermission(static::ACTION_TYPE_CREATE)) {
            abort(403);
        }

        $form = $this->createForm();

        $this->data['form'] = $form;
        $this->data['action'] = static::ACTION_CREATE;

        return $this->createView();
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
            'url' => route("{$this->routePrefix}.store")
        ], ['action' => static::ACTION_CREATE]);

        return $form;
    }

    /**
     * Return create view
     * Override this method to change view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function createView() {
        return view("{$this->viewPrefix}.create", $this->data);
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

        if (!$this->havePermission(static::ACTION_TYPE_STORE)) {
            abort(403);
        }

        $form = $this->storeForm();

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $entity = $this->storeSave();

        return $this->storeResponse($entity);
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
        ], ['action' => static::ACTION_STORE]);

        return $form;
    }

    /**
     * Return store response
     * @param $entity
     * @return \Illuminate\Http\Response
     */
    protected function storeResponse($entity) {
        return redirect()->route("{$this->routePrefix}.index")->with('status', trans('laravel-crud::ui.message.create_success', ['name' => trans($this->entityName)]));
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

        $fillables = isset($this->fillable) && is_array($this->fillable) && !empty($this->fillable) ? $this->fillable : $entity->getFillable();

        foreach ($fillables As $fillable) {
            if (Request::exists($fillable)) {
                $entity->$fillable = Request::input($fillable);
            }
        }

        $this->storeSavePreprocess($entity);

        $entity->save();

        return $entity;
    }

    /**
     * Override this method to add extra processing before calling save()
     *
     * @param Model $entity
     */
    protected function storeSavePreprocess(&$entity) {
        $this->savePreprocess($entity);
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

        if (!$this->havePermission(static::ACTION_TYPE_EDIT, $entity)) {
            abort(403);
        }

        $form = $this->editForm($entity);

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = static::ACTION_EDIT;

        return $this->editView($entity);
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
            'url' => route("{$this->routePrefix}.update", $entity->id),
            'model' => $entity
        ], ['entity' => $entity, 'action' => static::ACTION_EDIT]);

        return $form;
    }

    /**
     * Return edit view
     * Override this method to change view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function editView($entity) {
        return view("{$this->viewPrefix}.edit", $this->data);
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

        if (!$this->havePermission(static::ACTION_TYPE_UPDATE, $entity)) {
            abort(403);
        }

        $form = $this->updateForm($entity);

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $entity = $this->updateSave($entity);

        return $this->updateResponse($entity);
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
        ], ['entity' => $entity, 'action' => static::ACTION_UPDATE]);

        return $form;
    }

    /**
     * Return store response
     * @param $entity
     * @return \Illuminate\Http\Response
     */
    protected function updateResponse($entity) {
        return redirect()->route("{$this->routePrefix}.index")->with('status', trans('laravel-crud::ui.message.edit_success', ['name' => trans($this->entityName)]));;
    }

    /**
     * Trigger when update method
     * Override this method to add additinal operations
     *
     * @param Model $entity
     * @return Model $entity
     */
    protected function updateSave($entity) {
        $fillables = isset($this->fillable) && is_array($this->fillable) && !empty($this->fillable) ? $this->fillable : $entity->getFillable();

        foreach ($fillables As $fillable) {
            if (Request::exists($fillable)) {
                $entity->$fillable = Request::input($fillable);
            }
        }

        $this->updateSavePreprocess($entity);

        $entity->save();

        return $entity;
    }

    /**
     * Override this method to add extra processing before calling save()
     *
     * @param Model $entity
     */
    protected function updateSavePreprocess(&$entity) {
        $this->savePreprocess($entity);
    }

    /**
     * Override this method to add extra processing before calling save()
     *
     * @param Model $entity
     */
    protected function savePreprocess(&$entity) {

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

        if (!$this->havePermission(static::ACTION_TYPE_DELETE, $entity)) {
            abort(403);
        }

        $form = $this->deleteForm($entity, $id);
        $form->disableFields();

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = static::ACTION_DELETE;

        return $this->deleteView($entity);
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
            'url' => route("{$this->routePrefix}.destroy", $id),
            'model' => $entity
        ], ['entity' => $entity, 'action' => static::ACTION_DELETE]);
    }

    /**
     * Return delete view
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    protected function deleteView($entity) {
        return view("{$this->viewPrefix}.delete", $this->data);
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

        if (!$this->havePermission(static::ACTION_TYPE_DELETE, $entity)) {
            abort(403);
        }

        $this->destroySave($entity);

        return $this->destroyResponse($entity);
    }

    /**
     * Return destroy response
     * @param $entity
     * @return \Illuminate\Http\Response
     */
    protected function destroyResponse($entity) {
        return redirect()->route("{$this->routePrefix}.index")->with('status', trans('laravel-crud::ui.message.delete_success', ['name' => trans($this->entityName)]));
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
     * Ovrrride to modify query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function ajaxListQuery() {
        $query = ($this->entityClass)::select((new $this->entityClass)->getTable() . '.*');

        // Add 'with' relations
        if (is_array($this->with) && !empty($this->with)) {
            foreach ($this->with as $relation) {
                $query->with($relation);
            }
        }

        return $query;
    }

    /**
     * Extra DataTables action field
     * Override this method to append extra actions after default actions
     *
     * @param $item
     * @return string
     */
    protected function ajaxListActions($item)
    {
        return
            ($this->isViewable ? '<a href="' . route("{$this->routePrefix}.show", [$item->id]) .'" class="' . $this->showButtonClass . '"><i class="' . $this->showButtonIconClass . '"></i> ' . trans($this->showButtonText) . '</a> ' : '') .
            ($this->isEditable ? '<a href="' . route("{$this->routePrefix}.edit", [$item->id]) .'" class="' . $this->editButtonClass . '"><i class="' . $this->editButtonIconClass . '"></i> ' . trans($this->editButtonText) . '</a> ' : '') .
            ($this->isDeletable ? '<a href="' . route("{$this->routePrefix}.delete", [$item->id]) .'" class="' . $this->deleteButtonClass . '"><i class="' . $this->deleteButtonIconClass . '"></i> ' . trans($this->deleteButtonText) . '</a> ' : '');
    }

    /**
     * Construct DataTable object
     * Override this method to modify datatable object
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

        // Set makeHidden
        if (is_array($this->makeHiddenColumns) && !empty($this->makeHiddenColumns)) {
            $datatable->makeHidden($this->makeHiddenColumns);
        }

        // Set makeVisible
        if (is_array($this->makeVisibleColumns) && !empty($this->makeVisibleColumns)) {
            $datatable->makeVisible($this->makeVisibleColumns);
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
        if (!$this->havePermission(static::ACTION_TYPE_INDEX, null)) {
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
