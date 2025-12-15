<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add indexes to improve performance for daily appointment limit queries.
     * These indexes help with:
     * - Filtering by appointment_date
     * - Filtering by is_canceled status
     * - Composite queries combining date and cancellation status
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Index on appointment_date for faster date filtering
            $table->index('appointment_date', 'appointments_appointment_date_index');
            
            // Index on is_canceled for faster filtering of non-canceled appointments
            $table->index('is_canceled', 'appointments_is_canceled_index');
            
            // Composite index for the common query pattern: date + cancellation status
            $table->index(['appointment_date', 'is_canceled'], 'appointments_date_canceled_index');
        });
        
        // Also add index on the pivot table for faster joins
        Schema::table('appointment_appointment_type', function (Blueprint $table) {
            // Index on appointment_type_id for faster filtering by type
            $table->index('appointment_type_id', 'appt_appt_type_type_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_appointment_date_index');
            $table->dropIndex('appointments_is_canceled_index');
            $table->dropIndex('appointments_date_canceled_index');
        });
        
        Schema::table('appointment_appointment_type', function (Blueprint $table) {
            $table->dropIndex('appt_appt_type_type_id_index');
        });
    }
};
