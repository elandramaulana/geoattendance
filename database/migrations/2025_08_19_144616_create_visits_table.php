<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained()->onDelete('set null');
            $table->string('visit_type'); 
            $table->string('purpose');
            $table->string('location_name'); 
            $table->string('client_name')->nullable(); 
            $table->datetime('planned_start_time');
            $table->datetime('planned_end_time');
            $table->datetime('actual_start_time')->nullable();
            $table->datetime('actual_end_time')->nullable();
            $table->decimal('start_lat', 10, 8)->nullable();
            $table->decimal('start_lng', 11, 8)->nullable();
            $table->decimal('end_lat', 10, 8)->nullable();
            $table->decimal('end_lng', 11, 8)->nullable();
            $table->string('start_address')->nullable();
            $table->string('end_address')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employees')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};