<?php

namespace Imtigger\LaravelCRUD\Console\Commands;

use Artisan;
use DB;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeCRUD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud {name} {--softdelete} {--sortable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CRUD Controller/Model/Migration/View';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*
        $modelNamespace = $this->ask("Model Namespace", 'App\\Model');
        $controllerNamespace = $this->ask("Controller Namespace", 'App\\Http\\Controllers');

        $makeController = $this->confirm("Make Controller?", true);
        $makeModel = $this->confirm("Make Model?", true);
        $makeView = $this->confirm("Make View?", true);
        $makeMigration = $this->confirm("Make Migration?", true);
        */

        $this->name = strtolower($this->argument('name'));
        $this->nameSingular = str_singular($this->name);
        $this->namePlural = str_plural($this->name);

        $this->modelNamespace = 'App\\Models';
        $this->formNamespace = 'App\\Forms';
        $this->controllerNamespace = 'App\\Http\\Controllers\\Admin';

        $this->urlName = $this->nameSingular;
        $this->viewPrefix = 'admin.' . $this->nameSingular;
        $this->routePrefix = 'admin.' . $this->nameSingular;
        $this->permissionPrefix = $this->nameSingular;
        $this->translationPrefix = 'backend.' . $this->nameSingular . '.label';

        $this->controllerName = ucfirst($this->nameSingular) . 'Controller';
        $this->modelName = ucfirst($this->nameSingular);
        $this->formName = ucfirst($this->nameSingular) . 'Form';
        $this->migrationName = 'Create' . $this->namePlural . 'Table';
        $this->tableName = strtolower($this->namePlural);
        $this->entityName = 'backend.entity.' . strtolower($this->name);

        $this->compileView($this->nameSingular);
        $this->compileController($this->nameSingular);
        $this->compileModel($this->nameSingular);
        $this->compileForm($this->nameSingular);
        $this->compileMigration($this->nameSingular);

        Artisan::call('laroute:generate');

        $this->info("Now add route to config/web.php:");
        $this->info("\\Imtigger\\LaravelCRUD\\CRUDController::routes('/{$this->urlName}', '\\{$this->controllerNamespace}\\{$this->controllerName}', '{$this->viewPrefix}');");
    }


    protected function getControllerPath($name)
    {
        $name = str_replace_first($this->laravel->getNamespace(), '', $name);
        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function getModelPath($name)
    {
        $name = str_replace_first($this->laravel->getNamespace(), '', $name);
        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function getFormPath($name)
    {
        $name = str_replace_first($this->laravel->getNamespace(), '', $name);
        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function getViewPath($modelName)
    {
        return base_path() . '/resources/views/admin/' . strtolower($modelName);
    }

    protected function getMigrationPath($modelName)
    {
        $name = strtolower(str_plural($modelName));
        return base_path() . '/database/migrations/' . date('Y_m_d_His') . '_create_' . $name . '_table.php';
    }

    protected function compileView($name)
    {
        $viewDirectoryPath = $this->getViewPath($name);
        \File::makeDirectory($viewDirectoryPath, 0755, true, true);

        foreach(['layout.blade.php', 'form.blade.php', 'index.blade.php', 'create.blade.php', 'edit.blade.php', 'show.blade.php'] As $filename) {
            $content = $this->getStubContent("views/{$filename}");
            // TODO: Replace tokens
            file_put_contents("{$viewDirectoryPath}/{$filename}", $content);
        }

        $this->info("Creating View: {$viewDirectoryPath}");
    }

    protected function compileController($name)
    {
        $this->controllerPath = $this->getControllerPath($this->controllerNamespace . '/' . $this->controllerName);

        $content = $this->getStubContent("Controller.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->controllerPath}", $content);

        $this->info("Creating Controller: {$this->controllerPath}");
    }

    protected function compileModel($name)
    {
        $this->modelPath = $this->getModelPath($this->modelNamespace . '/' . $this->modelName);

        $content = $this->getStubContent("Model.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->modelPath}", $content);

        $this->info("Creating Model: {$this->modelPath}");
    }

    protected function compileForm($name)
    {
        $this->formPath = $this->getFormPath($this->formNamespace . '/' . $this->formName);

        $content = $this->getStubContent("Form.php");
        $content = $content = $this->replaceTokens($content);
        file_put_contents("{$this->formPath}", $content);

        $this->info("Creating Form: {$this->formPath}");
    }

    protected function compileMigration($name)
    {
        $this->migrationPath = $this->getMigrationPath($name);

        $content = $this->getStubContent("migration.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->migrationPath}", $content);

        $this->info("Creating Migration: {$this->migrationPath}");
    }

    protected function getStubContent($path)
    {
        return $this->fs->get(__DIR__ . '/../../stubs/' . $path);
    }

    protected function replaceTokens($content)
    {
        $map = [
            '$CONTROLLER_NAME$' => $this->controllerName,
            '$MODEL_NAME$' => $this->modelName,
            '$FORM_NAME$' => $this->formName,
            '$MIGRATION_NAME$' => $this->migrationName,
            '$TABLE_NAME$' => $this->tableName,
            '$VIEW_PREFIX$' => $this->viewPrefix,
            '$ROUTE_PREFIX$' => $this->routePrefix,
            '$PERMISSION_PREFIX$' => $this->permissionPrefix,
            '$TRANSLATION_PREFIX$' => $this->translationPrefix,
            '$ENTITY_NAME$' => $this->entityName
        ];

        return strtr($content, $map);
    }
}
