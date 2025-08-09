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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('office_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_schedule_id')->constrained()->onDelete('cascade');
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('employment_status', ['permanent', 'contract', 'internship', 'freelance'])->default('permanent');
            $table->decimal('salary', 15, 2)->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
