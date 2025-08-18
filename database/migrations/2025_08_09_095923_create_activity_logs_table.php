<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('activity_type'); // clock_in, clock_out, overtime, visit, leave, etc
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // flexible data storage
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('location_address')->nullable();
            $table->timestamp('activity_time');
            $table->string('device_info')->nullable();
            $table->string('ip_address')->nullable();
            $table->enum('status', ['success', 'failed', 'pending'])->default('success');
            $table->timestamps();

            $table->index(['employee_id', 'activity_type']);
            $table->index(['employee_id', 'activity_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};