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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('application_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('category'); // e.g., KRA1, KRA2
            $table->string('type'); // e.g., research-outputs, mentorship-services
            $table->json('data'); // Stores all unique fields for each type
            $table->text('validator_remarks')->nullable();
            $table->string('filename')->nullable();
            $table->json('google_drive_file_id')->nullable();
            $table->decimal('raw_score', 8, 2)
                ->nullable()
                ->comment('The raw, uncapped score before caps are applied.');;
            $table->decimal('score', 8, 2)->nullable();
            $table->string('status')->default('For Submission');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
