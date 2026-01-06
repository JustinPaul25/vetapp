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
        // Ensure all existing appointments have their patient_id in the pivot table
        DB::statement('
            INSERT IGNORE INTO appointment_patient (appointment_id, patient_id, created_at, updated_at)
            SELECT id, patient_id, created_at, updated_at
            FROM appointments
            WHERE patient_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM appointment_patient ap 
                WHERE ap.appointment_id = appointments.id 
                AND ap.patient_id = appointments.patient_id
            )
        ');

        // Make patient_id nullable since we'll use the pivot table for multiple pets
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Before making patient_id required again, ensure all appointments have at least one patient
        // Set patient_id to the first patient from the pivot table for each appointment
        DB::statement('
            UPDATE appointments a
            INNER JOIN (
                SELECT appointment_id, MIN(patient_id) as first_patient_id
                FROM appointment_patient
                GROUP BY appointment_id
            ) ap ON a.id = ap.appointment_id
            SET a.patient_id = ap.first_patient_id
            WHERE a.patient_id IS NULL
        ');

        // Make patient_id required again
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable(false)->change();
        });
    }
};
