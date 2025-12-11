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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onUpdate('cascade');
            
            $table->foreignId('appointment_id')->constrained('appointments')->onUpdate('cascade');
            
            $table->foreignId('disease_id')->nullable()->constrained('diseases')->onUpdate('cascade');
            
            $table->string('pet_weight')->nullable(false);
            $table->string('symptoms')->nullable(false);
            $table->text('notes')->nullable(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
