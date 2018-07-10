<?php

namespace Imtigger\LaravelCRUD;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use Kris\LaravelFormBuilder\Form;
use Kris\LaravelFormBuilder\FormBuilderTrait;
use Dimsav\Translatable\Translatable;
use Yajra\DataTables\Facades\DataTables;
use Rutorika\Sortable\SortableTrait;

/**
 * Generic CRUD Controller with overridable options
 * With searchable and sortable datatables index page
 *
 * Interoperable with:
 * Laravel DataTables - https://github.com/yajra/laravel-datatables
 * DataTables - https://github.com/DataTables/DataTables
 * DataTables row-reorder - https://github.com/DataTables/RowReorder
 * Laravel Form Builder - https://github.com/kristijanhusak/laravel-form-builder
 * Laratrust ACL - https://github.com/santigarcor/laratrust
 * Translatable - https://github.com/dimsav/laravel-translatable
 * Sortable - https://github.com/boxfrommars/rutorika-sortable
 *
 * @property string $viewPrefix Prefix of blade view
 * @property string $routePrefix Prefix of route name
 * @property string $permissionPrefix Prefix of Laratrust permission name
 * @property string $entityName Entity Name
 * @property \Eloquent $entityClass Entity Class
 * @property string $formClass Laravel Form Builder Class
 * @property bool $isCreatable Enable create operation, default: true
 * @property bool $isEditable Enable edit operation, default: true
 * @property bool $isViewable Enable view operation, default: true
 * @property bool $isDeletable Enable delete operation, default: true
 * @property bool $isReorderable Enable reorder operation, default: false
 * @property array $rawColumns Columns that do not enable XSS protection by Laravel DataTables (7.0+)
 */
abstract class CRUDController extends BaseController
{
    use FormBuilderTrait;

    protected $isCreatable = true;
    protected $isEditable = true;
    protected $isViewable = true;
    protected $isDeletable = false;
    protected $isReorderable = false;
    protected $rawColumns = ['actions'];
    protected $with = [];
    protected $data = [];

    public function __construct() {
        if (!property_exists($this, 'viewPrefix')) throw new \Exception("viewPrefix not defined");
        if (!property_exists($this, 'routePrefix')) throw new \Exception("entityClass not defined");
        if (!property_exists($this, 'permissionPrefix')) throw new \Exception("permissionPrefix not defined");
        if (!property_exists($this, 'entityName')) throw new \Exception("entityName not defined");
        if (!property_exists($this, 'entityClass')) throw new \Exception("entityClass not defined");
        if (($this->isCreatable || $this->isEditable || $this->isViewable || $this->isDeletable) && !property_exists($this, 'formClass')) throw new \Exception("formClass not defined");
        if ($this->isReorderable && !in_array('Rutorika\Sortable\SortableTrait', class_uses($this->entityClass))) throw new \Exception("{$this->entityClass} must use SortableTrait trait when isReorderable = true");

        $this->data['viewPrefix'] = $this->viewPrefix;
        $this->data['routePrefix'] = $this->routePrefix;
        $this->data['permissionPrefix'] = $this->permissionPrefix;
        $this->data['entityName'] = $this->entityName;

        $this->data['isCreatable'] = $this->isCreatable;
        $this->data['isEditable'] = $this->isEditable;
        $this->data['isViewable'] = $this->isViewable;
        $this->data['isDeletable'] = $this->isDeletable;
        $this->data['isReorderable'] = $this->isReorderable;

        $this->middleware("permission:{$this->permissionPrefix}.read");
        $this->middleware("permission:{$this->permissionPrefix}.write")->only(['create', 'store', 'edit', 'update', 'delete', 'destroy']);
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
        \Route::post("{$prefix}/ajax/reorder", ['as' => "$as.ajax.reorder", 'uses' => "{$controller}@ajaxReorder", 'laroute' => true]);
        \Route::resource("{$prefix}", "{$controller}", ['as' => $prefix_of_prefix]);
    }

    /**
     * HTTP index handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index() {
        return view("{$this->viewPrefix}.index", $this->data);
    }

    /**
     * HTTP show handler
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isViewable) {
            abort(404);
        }

        if (!$this->havePermission('read', $entity)) {
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
            'method' => 'post',
            'url' => route("$this->routePrefix.show", $id),
            'model' => $entity
        ]);
    }

    /**
     * HTTP create handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create() {
        if (!$this->isCreatable) {
            abort(404);
        }

        if (!$this->havePermission('write')) {
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
     */
    public function store()
    {
        if (!$this->isCreatable) {
            abort(404);
        }

        if (!$this->havePermission('write')) {
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
        /** @var Model|Translatable $entity */
        $entity = new $this->entityClass;

        $fillables = collect($entity->getFillable());

        // Fill non-translated attributes
        if (property_exists($this->entityClass, 'translatedAttributes')) {
            $translatedFillables = collect($entity->translatedAttributes);
            $fillables = $fillables->diff($translatedFillables);
        }

        foreach ($fillables As $fillable) {
            if (Input::exists($fillable)) {
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
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isEditable) {
            abort(404);
        }

        if (!$this->havePermission('write', $entity)) {
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
            'url' => route("$this->routePrefix.update", $entity->id)
            'model' => $entity
        ]);

        return $form;
    }

    /**
     * HTTP update handler
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isEditable) {
            abort(404);
        }

        if (!$this->havePermission('write', $entity)) {
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
        ]);

        return $form;
    }

    /**
     * Trigger when update method
     * Override this method to add additinal operations
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
            if (Input::exists($fillable)) {
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
     * HTTP delete handler
     *
     * @param int $id
     * @return mixed
     */
    public function delete($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isDeletable) {
            abort(404);
        }

        if (!$this->havePermission('write', $entity)) {
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
        ]);
    }

    /**
     * HTTP destroy handler
     *
     * @param int $id
     * @return mixed
     */
    public function destroy($id) {
        $entity = ($this->entityClass)::findOrFail($id);

        if (!$this->isDeletable) {
            abort(404);
        }

        if (!$this->havePermission('write', $entity)) {
            abort(403);
        }

        $this->destroySave($entity);

        return redirect()->route("$this->routePrefix.index")->with('status', trans('laravel-crud::ui.message.delete_success', ['name' => trans($this->entityName)]));
    }

    /**
     * Trigger when destroy method
     * Override this method to add additinal operations
     *
     * @param Model|Translatable $entity
     */
    protected function destroySave($entity) {
        $entity->delete();
    }

    /**
     * HTTP ajax.list query builder
     *
     * @return \Eloquent
     */
    protected function ajaxListQuery() {
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
                ->where("{$foreignTable}.locale", Config::get('translatable.locale'));
        }

        // Add orderBy
        if (in_array('Rutorika\Sortable\SortableTrait', class_uses($this->entityClass))) {
            $query->orderBy('position');
        }

        // Add 'with' relations
        if (is_array($this->with) && !empty($this->with)) {
            foreach ($this->with as $relation) {
                $query->with($relation);
            }
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
            ($this->isEditable ? '<a href="' . route("{$this->routePrefix}.edit", [$item->id]) .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> ' . trans('laravel-crud::ui.button.edit') . '</a> ' : '') .
            ($this->isDeletable ? '<a href="' . route("{$this->routePrefix}.delete", [$item->id]) .'" class="btn btn-xs btn-danger"><i class="glyphicon glyphicon-trash"></i> ' . trans('laravel-crud::ui.button.delete') . '</a> ' : '');
    }

    /**
     * Construct datatable object
     *
     * @param $items
     * @return \Yajra\DataTables\DataTableAbstract
     */
    protected function ajaxListDataTable($items) {
        $datatable = DataTables::of($items)
            ->addColumn('actions', function ($item) {
                return $this->ajaxListActions($item);
            });

        // Set rawColumns
        if (!empty($this->rawColumns) && method_exists($datatable, 'rawColumns')) {
            $datatable->rawColumns($this->rawColumns);
        }

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
                $datatable->orderColumn($translatedAttribute, "{$foreignTable}.{$translatedAttribute} $1");
            }
        }

        return $datatable;
    }

    /**
     * HTTP ajax.list handler
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxList() {
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
        if (!$this->isReorderable) {
            abort(404);
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
     * Additional check after return if user have permission
     * Override this method to add checkings
     *
     * @param string $action
     * @param Model $entity
     * @return bool
     */
    protected function havePermission($action, $entity = null) {
        return true;
    }
}