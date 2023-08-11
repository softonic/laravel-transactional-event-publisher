<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('domain_events_cursor');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::create('domain_events_cursor', static function (Blueprint $table) {
            $table->unsignedInteger('last_id')
                ->comment('ID from the last domain event emitted')
                ->primary();
        });
    }
};
