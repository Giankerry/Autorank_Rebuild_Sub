<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('applicant_current_rank')->nullable();
            $table->string('status')->default('Draft');
            $table->string('evaluation_cycle')->nullable();
            $table->decimal('kra1_score', 8, 2)->nullable();
            $table->decimal('kra2_score', 8, 2)->nullable();
            $table->decimal('kra3_score', 8, 2)->nullable();
            $table->decimal('kra4_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();
            $table->string('highest_attainable_rank')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
