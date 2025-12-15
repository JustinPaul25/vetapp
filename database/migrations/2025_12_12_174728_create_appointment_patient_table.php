<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
            
            // Ensure unique combination
            $table->unique(['appointment_id', 'patient_id']);
        });
        
        // Migrate existing data from appointments.patient_id to pivot table
        DB::statement('
            INSERT INTO appointment_patient (appointment_id, patient_id, created_at, updated_at)
            SELECT id, patient_id, created_at, updated_at
            FROM appointments
            WHERE patient_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_patient');
    }
};
