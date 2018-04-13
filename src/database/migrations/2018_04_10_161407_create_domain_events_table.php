<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->increments('id');
            $table->text('message');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::drop('domain_events');
    }
}
