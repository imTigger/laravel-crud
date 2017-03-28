<?php

namespace Imtigger\LaravelCRUD;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;
use Kris\LaravelFormBuilder\Form;
use Kris\LaravelFormBuilder\FormBuilderTrait;
use Dimsav\Translatable\Translatable;
use Yajra\Datatables\Facades\Datatables;
use Rutorika\Sortable\SortableTrait;

/**
 * Generic CRUD Controller with override-able options by Tiger
 *
 * Interoperable with:
 * Datatables search, row-reorder - https://github.com/yajra/laravel-datatables
 * Entrust ACL - https://github.com/Zizaco/entrust
 * Translatable - https://github.com/dimsav/laravel-translatable
 * Sortable - https://github.com/boxfrommars/rutorika-sortable
 *
 * @property string $viewPrefix
 * @property string $routePrefix
 * @property string $permissionPrefix
 * @property string $entityName
 * @property \Eloquent $entityClass
 * @property string $formClass
 * @property bool $isEditable
 * @property bool $isViewable
 * @property bool $isDeletable
 */
abstract class CRUDController extends BaseController
{
    use FormBuilderTrait;

    protected $noPermissionRoute = 'admin';
    protected $isEditable = true;
    protected $isViewable = true;
    protected $isDeletable = true;
    protected $data = [];

    public function __construct() {
        if (!property_exists($this, 'viewPrefix')) throw new \Exception("viewPrefix not defined");
        if (!property_exists($this, 'routePrefix')) throw new \Exception("entityClass not defined");
        if (!property_exists($this, 'permissionPrefix')) throw new \Exception("permissionPrefix not defined");
        if (!property_exists($this, 'entityName')) throw new \Exception("entityName not defined");
        if (!property_exists($this, 'entityClass')) throw new \Exception("entityClass not defined");
        if (($this->isEditable || $this->isViewable || $this->isDeletable) && !property_exists($this, 'formClass')) throw new \Exception("formClass not defined");

        $this->data['viewPrefix'] = $this->viewPrefix;
        $this->data['routePrefix'] = $this->routePrefix;
        $this->data['permissionPrefix'] = $this->permissionPrefix;
        $this->data['entityName'] = $this->entityName;

        $this->data['isEditable'] = $this->isEditable;
        $this->data['isViewable'] = $this->isViewable;
        $this->data['isDeletable'] = $this->isDeletable;
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
        \Route::resource("{$prefix}", "{$controller}", ['as' => $prefix_of_prefix]);
        \Route::get("{$prefix}/ajax/list", ['as' => "{$as}.ajax.list", 'uses' => "{$controller}@ajaxList", 'laroute' => true]);
        \Route::post("{$prefix}/ajax/reorder", ['as' => "$as.ajax.reorder", 'uses' => "{$controller}@ajaxReorder", 'laroute' => true]);
    }

    /**
     * HTTP index handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index() {
        if (!$this->havePermission('read')) {
            return $this->permissionDeniedResponse();
        }

        $this->indexExtraData();

        return view("{$this->viewPrefix}.index", $this->data);
    }

    /**
     * Add data to index view using $this->data['key'] = $value;
     *
     * @return void
     */
    protected function indexExtraData() {

    }

    /**
     * HTTP show handler
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        if (!$this->havePermission('read') || !$this->isViewable) {
            return $this->permissionDeniedResponse();
        }

        $entity = ($this->entityClass)::findOrFail($id);

        $form = $this->showForm($entity, $id);
        $form->disableFields();

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = 'show';

        return view("{$this->viewPrefix}.show", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in show
     *
     * @param Model $entity
     * @param int $id
     * @return \Kris\LaravelFormBuilder\Form
     */
    protected function showForm($entity, $id) {
        return $this->form($this->formClass, [
            'method' => 'post',
            'url' => route("$this->routePrefix.show", $id),
            'class' => 'simple-form',
            'model' => $entity
        ]);
    }

    /**
     * HTTP create handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create() {
        if (!$this->havePermission('write')) {
            return $this->permissionDeniedResponse();
        }

        $form = $this->createForm();

        $this->data['form'] = $form;
        $this->data['action'] = 'create';

        return view("{$this->viewPrefix}.create", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in create
     *
     * @return Form
     */
    protected function createForm() {
        return $this->form($this->formClass, [
            'method' => 'post',
            'url' => route("$this->routePrefix.store"),
            'class' => 'simple-form'
        ]);
    }

    /**
     * HTTP store handler
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store()
    {
        if (!$this->havePermission('write')) {
            return $this->permissionDeniedResponse();
        }

        $form = $this->storeForm();

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $this->storeSave();

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.create_success', ['name' => trans($this->entityName)]));
    }

    protected function storeForm() {
        return $this->form($this->formClass);
    }

    /**
     * Trigger when store method
     *
     * @return Model
     */
    protected function storeSave() {
        /** @var Model|Translatable $entity */
        $entity = new $this->entityClass;

        $fillables = collect($entity->getFillable());

        // Fill non-translated attributes
        if (property_exists($this->entityClass, 'translatedAttributes')) {
            $translatedFillables = collect($entity->translatedAttributes);
            $fillables = $fillables->diff($translatedFillables);
        }

        foreach ($fillables As $fillable) {
            if (Input::get($fillable) != null) {
                $entity->$fillable = Input::get($fillable);
            }
        }

        // Fill translated attributes
        if (property_exists($this->entityClass, 'translatedAttributes')) {
            foreach ($entity->translatedAttributes As $translatedAttribute) {
                $locales = Config::get('translatable.locales');
                foreach ($locales As $locale) {
                    $entity->translateOrNew($locale)->$translatedAttribute = Input::get("{$translatedAttribute}.{$locale}");
                }
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
     */
    public function edit($id) {
        if (!$this->havePermission('write') || !$this->isEditable) {
            return $this->permissionDeniedResponse();
        }

        $entity = ($this->entityClass)::findOrFail($id);

        $form = $this->editForm($entity, $id);

        $this->data['entity'] = $entity;
        $this->data['form'] = $form;
        $this->data['action'] = 'edit';

        return view("{$this->viewPrefix}.edit", $this->data);
    }

    /**
     * Return LaravelFormBuilder Form used in edit
     *
     * @param Model $entity
     * @param int $id
     * @return Form
     */
    protected function editForm($entity, $id) {
        return $this->form($this->formClass, [
            'method' => 'patch',
            'url' => route("$this->routePrefix.update", $id),
            'class' => 'simple-form',
            'model' => $entity
        ]);
    }

    /**
     * HTTP update handler
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update($id) {
        if (!$this->havePermission('write') || !$this->isEditable) {
            return $this->permissionDeniedResponse();
        }

        $entity = ($this->entityClass)::findOrFail($id);

        $form = $this->form($this->formClass);

        if (!$form->isValid()) {
            return redirect()->back()->withErrors($form->getErrors())->withInput();
        }

        $this->updateSave($entity);

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.edit_success', ['name' => trans($this->entityName)]));
    }
    /**
     * Trigger when update method
     *
     * @param Model|Translatable $entity
     * @return Model|Translatable $entity
     */
    protected function updateSave($entity) {
        $fillables = collect($entity->getFillable());

        // Fill non-translated attributes
        if (property_exists($entity, 'translatedAttributes')) {
            $translatedFillables = collect($entity->translatedAttributes);
            $fillables = $fillables->diff($translatedFillables);
        }

        foreach ($fillables As $fillable) {
            if (Input::get($fillable) != null) {
                $entity->$fillable = Input::get($fillable);
            }
        }

        // Fill translated attributes
        if (property_exists($entity, 'translatedAttributes')) {
            foreach ($entity->translatedAttributes As $translatedAttribute) {
                $locales = Config::get('translatable.locales');
                foreach ($locales As $locale) {
                    $entity->translateOrNew($locale)->$translatedAttribute = Input::get("{$translatedAttribute}.{$locale}");
                }
            }
        }

        $entity->save();

        return $entity;
    }

    /**
     * HTTP destroy handler
     *
     * @param int $id
     * @return mixed
     */
    public function destroy($id) {
        if (!$this->havePermission('write') || !$this->isDeletable) {
            return redirect()->route($this->noPermissionRoute)->with('status', "Permission denied");
        }

        $entity = ($this->entityClass)::findOrFail($id);

        $entity->delete();

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.delete_success', ['name' => trans($this->entityName)]));
    }

    /**
     * HTTP ajax.list query
     *
     * @return \Eloquent
     */
    public function ajaxListQuery() {
        if (!property_exists($this->entityClass, 'translatedAttributes')) {
            $query = ($this->entityClass)::query();

        } else {
            /** @var Model|Translatable $entity */
            $entity = new $this->entityClass;
            $table = $entity->getTable();
            $translatedAttributes = $entity->translatedAttributes;
            $relationKey = $entity->getRelationKey();
            $foreignModelName = $entity->getTranslationModelName();
            /** @var Model $foreignEntity */
            $foreignEntity = new $foreignModelName();
            $foreignTable = $foreignEntity->getTable();

            $fields = ["{$table}.*"];
            foreach ($translatedAttributes As $translatedAttribute) {
                $fields[] = "{$foreignTable}.{$translatedAttribute} AS {$translatedAttribute}";
            }

            $query = ($this->entityClass)::select($fields)
                ->join($foreignTable, "{$foreignTable}.{$relationKey}", '=', "{$table}.id")
                ->where("{$foreignTable}.locale", 'en');
        }

        if (in_array('Rutorika\Sortable\SortableTrait', class_uses($this->entityClass))) {
            $query->orderBy('position');
        }

        return $query;
    }

    /**
     * Extra Datatable action field, append string after default actions
     *
     * @param $item
     * @return string
     */
    protected function ajaxListActions($item)
    {
        return
            ($this->isViewable ? '<a href="' . route("{$this->routePrefix}.show", [$item->id]) .'" class="btn btn-xs btn-success"><i class="glyphicon glyphicon-eye-open"></i> ' . trans('laravel-crud::ui.button.view') . '</a> ' : '') .
            ($this->isEditable ? '<a href="' . route("{$this->routePrefix}.edit", [$item->id]) .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> ' . trans('laravel-crud::ui.button.edit') . '</a> ' : '');
    }

    /**
     * Construct datatable object
     *
     * @param $items
     * @return \Yajra\Datatables\Engines\CollectionEngine
     */
    public function ajaxListDataTable($items) {
        $datatable = Datatables::of($items)
            ->addColumn('actions', function ($item) {
                return $this->ajaxListActions($item);
            });

        if (property_exists($this->entityClass, 'translatedAttributes')) {
            /** @var Model|Translatable $entity */
            $entity = new $this->entityClass;
            $table = $entity->getTable();
            $translatedAttributes = $entity->translatedAttributes;
            $relationKey = $entity->getRelationKey();
            $foreignModelName = $entity->getTranslationModelName();
            /** @var Model $foreignEntity */
            $foreignEntity = new $foreignModelName();
            $foreignTable = $foreignEntity->getTable();

            foreach ($translatedAttributes As $translatedAttribute) {
                $datatable->filterColumn($translatedAttribute, function ($query, $keyword) use ($foreignTable, $translatedAttribute) {
                    $query->where("{$foreignTable}.{$translatedAttribute}", 'LIKE', "%{$keyword}%");
                });
                $datatable->orderColumn('title', "{$foreignTable}.{$translatedAttribute} $1");
            }

            $datatable->editColumn('title', function ($item) {
                return "<a href='" . route('frontend.page', [$item->slug]) . "'>{$item->title}</a>";
            });
        }

        return $datatable;
    }

    /**
     * HTTP ajax.list handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxList() {
        if (!$this->havePermission('read')) {
            return $this->permissionDeniedResponse();
        }

        $items = $this->ajaxListQuery();

        return $this->ajaxListDataTable($items)->make(true);
    }

    /**
     * HTTP ajax.reorder handler
     *
     * @throws \Exception
     * @return void
     */
    public function ajaxReorder() {

        if (!in_array('Rutorika\Sortable\SortableTrait', class_uses($this->entityClass))) {
            throw new \Exception("$this->entityClass must use SortableTrait trait");
        }

        /** @var Model|SortableTrait $from */
        $origin = ($this->entityClass)::findOrFail(Input::get('id'));

        /** @var Model|SortableTrait $target */
        if (Input::get('before')) {
            $target = ($this->entityClass)::find(Input::get('before'));
            $origin->moveBefore($target);
        } else if (Input::get('after')) {
            $target = ($this->entityClass)::find(Input::get('after'));
            $origin->moveAfter($target);
        }

        return ['status' => 0];
    }

    /**
     * Return if user have permission
     *
     * @param string $action
     * @return bool
     */
    protected function havePermission($action) {
        return Auth::user()->can("{$this->permissionPrefix}.{$action}");
    }

    /**
     * Return permission denied response depend on request type
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function permissionDeniedResponse($message = "Permission denied") {
        if (Request::ajax()) {
            return response()->json(['error' => $message]);
        } else {
            return redirect()->route($this->noPermissionRoute)->with('status', $message);
        }
    }
}