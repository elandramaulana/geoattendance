<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->json('work_days'); // 1=monday, 7=sunday
            $table->integer('total_hours')->default(8);
            $table->integer('break_duration')->default(60); // minutes
            $table->boolean('is_flexible')->default(false);
            $table->integer('flexible_minutes')->default(0); // tolerance in minutes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('work_schedules');
    }
};
