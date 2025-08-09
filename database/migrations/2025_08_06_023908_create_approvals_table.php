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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type'); // polymorphic
            $table->unsignedBigInteger('approvable_id'); // polymorphic
            $table->foreignId('employee_id')->constrained()->onDelete('cascade'); // requester
            $table->foreignId('approver_id')->constrained('employees')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('approvals');
    }
};
