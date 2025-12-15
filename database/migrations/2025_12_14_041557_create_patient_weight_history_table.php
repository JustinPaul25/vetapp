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
        Schema::create('patient_weight_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('weight', 8, 2)->nullable(false); // Weight in kg, supports up to 999999.99
            $table->dateTime('recorded_at')->nullable(false); // When the weight was recorded
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->onDelete('set null')->onUpdate('cascade');
            $table->text('notes')->nullable(); // Optional notes about the weight recording
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_weight_history');
    }
};
