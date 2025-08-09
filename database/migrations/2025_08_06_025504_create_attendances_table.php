<?php
// database/migrations/2024_01_01_000002_create_attendances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('office_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 8)->nullable();
            $table->decimal('clock_in_lng', 11, 8)->nullable();
            $table->decimal('clock_out_lat', 10, 8)->nullable();
            $table->decimal('clock_out_lng', 11, 8)->nullable();
            $table->string('clock_in_address')->nullable();
            $table->string('clock_out_address')->nullable();
            $table->integer('work_duration')->nullable(); // in minutes
            $table->integer('overtime_duration')->nullable(); // in minutes
            $table->enum('status', ['present', 'late', 'absent', 'holiday', 'leave'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
};