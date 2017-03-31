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
    protected $signature = 'make:crud {name}';

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
        $this->name = $this->argument('name');
        $this->nameNormalized = str_singular($this->name);
        $this->nameSingular = str_singular($this->name);
        $this->namePlural = str_plural($this->name);

        $this->modelNamespace = 'App\\Models';
        $this->formNamespace = 'App\\Forms';
        $this->controllerNamespace = 'App\\Http\\Controllers\\Admin';

        $this->urlName = snake_case($this->nameNormalized);
        $this->viewPrefix = 'admin.' . snake_case($this->nameNormalized);
        $this->routePrefix = 'admin.' . snake_case($this->nameNormalized);
        $this->permissionPrefix = snake_case($this->nameNormalized);
        $this->translationPrefix = 'backend.' . snake_case($this->nameNormalized) . '.label';

        $this->controllerName = studly_case($this->nameNormalized) . 'Controller';
        $this->modelName = studly_case($this->nameNormalized);
        $this->formName = studly_case($this->nameNormalized) . 'Form';
        $this->migrationName = 'Create' . studly_case($this->namePlural) . 'Table';
        $this->tableName = snake_case($this->namePlural);
        $this->entityName = 'backend.entity.' . snake_case($this->name);

        $this->compileView($this->nameNormalized);
        $this->compileController($this->nameNormalized);
        $this->compileModel($this->nameNormalized);
        $this->compileForm($this->nameNormalized);
        $this->compileMigration($this->nameNormalized);

        $this->info("Now add route to config/web.php and run 'laroute:generate'");
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

    protected function getViewPath($name)
    {
        return base_path() . '/resources/views/admin/' . snake_case($name);
    }

    protected function getMigrationPath($name)
    {
        $name = snake_case(str_plural($name));
        return base_path() . '/database/migrations/' . date('Y_m_d_His') . '_create_' . $name . '_table.php';
    }

    protected function compileView($name)
    {
        $viewDirectoryPath = $this->getViewPath($name);
        \File::makeDirectory($viewDirectoryPath, 0755, true, true);

        foreach(['layout.blade.php', 'form.blade.php', 'index.blade.php', 'create.blade.php', 'edit.blade.php', 'show.blade.php'] As $filename) {
            $content = $this->getStubContent("views/{$filename}");
            $content = $this->replaceTokens($content);
            file_put_contents("{$viewDirectoryPath}/{$filename}", $content);
            $this->info("Created View: {$viewDirectoryPath}/{$filename}");
        }
    }

    protected function compileController($name)
    {
        $this->controllerPath = $this->getControllerPath($this->controllerNamespace . '/' . $this->controllerName);

        $content = $this->getStubContent("Controller.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->controllerPath}", $content);

        $this->info("Created Controller: {$this->controllerPath}");
    }

    protected function compileModel($name)
    {
        $this->modelPath = $this->getModelPath($this->modelNamespace . '/' . $this->modelName);

        $content = $this->getStubContent("Model.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->modelPath}", $content);

        $this->info("Created Model: {$this->modelPath}");
    }

    protected function compileForm($name)
    {
        $this->formPath = $this->getFormPath($this->formNamespace . '/' . $this->formName);

        $content = $this->getStubContent("Form.php");
        $content = $content = $this->replaceTokens($content);
        file_put_contents("{$this->formPath}", $content);

        $this->info("Created Form: {$this->formPath}");
    }

    protected function compileMigration($name)
    {
        $this->migrationPath = $this->getMigrationPath($name);

        $content = $this->getStubContent("migration.php");
        $content = $this->replaceTokens($content);
        file_put_contents("{$this->migrationPath}", $content);

        $this->info("Created Migration: {$this->migrationPath}");
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
