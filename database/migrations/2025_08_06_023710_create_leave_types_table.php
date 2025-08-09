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
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('code')->unique();
                $table->integer('max_days_per_year')->nullable();
                $table->boolean('is_paid')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

    public function down()
    {
        Schema::dropIfExists('leave_types');
    }
};
