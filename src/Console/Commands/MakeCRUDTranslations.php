<?php

namespace Imtigger\LaravelCRUD\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeCRUDTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud:trans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate translations strings from key for Laravel Translation Manager';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $rows = DB::table('ltm_translations')->where('status', '=', 0)->whereNull('value')->get();

        foreach ($rows as $row) {
            $value = substr($row->key, strrpos($row->key, '.') + 1);
            $value = ucwords(str_replace("_", " ", $value));
            $value = str_replace(['Id'], ['ID'], $value);

            DB::table('ltm_translations')
                ->where('id', $row->id)
                ->update(['value' => $value]);
        }

        $this->info(sizeof($rows) . ' rows updated');
    }
}
