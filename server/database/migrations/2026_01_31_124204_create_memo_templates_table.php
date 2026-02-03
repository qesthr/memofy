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
        Schema::create('memo_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->foreignId('signature_id')->nullable()->constrained('user_signatures')->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('priority')->default('Medium');
            $table->text('content')->nullable();
            $table->json('template_data')->nullable(); // For any extra fields
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_templates');
    }
};
