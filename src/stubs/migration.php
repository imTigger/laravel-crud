<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class $MIGRATION_NAME$ extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('$TABLE_NAME$', function (Blueprint $table) {
$MIGRATION_CONTENT$           
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('$TABLE_NAME$');
    }
}
