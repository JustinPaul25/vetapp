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
        // Get foreign key constraint names
        $fks = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'appointment_patient' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
            AND CONSTRAINT_NAME != 'PRIMARY'
        ");
        
        // Drop foreign key constraints first
        foreach ($fks as $fk) {
            $constraintName = $fk->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE appointment_patient DROP FOREIGN KEY `{$constraintName}`");
        }
        
        // Now drop the unique index
        DB::statement('ALTER TABLE appointment_patient DROP INDEX appointment_patient_appointment_id_patient_id_unique');
        
        // Add appointment_type_id column
        Schema::table('appointment_patient', function (Blueprint $table) {
            $table->foreignId('appointment_type_id')->nullable()->after('patient_id')->constrained('appointment_types')->onDelete('cascade')->onUpdate('cascade');
        });
        
        // Add new unique constraint that includes appointment_type_id
        Schema::table('appointment_patient', function (Blueprint $table) {
            // This allows a pet to have multiple appointment types in the same appointment
            $table->unique(['appointment_id', 'patient_id', 'appointment_type_id'], 'appointment_patient_type_unique');
        });
        
        // Re-add foreign key constraints
        Schema::table('appointment_patient', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade')->onUpdate('cascade');
        });
        
        // For existing records, set appointment_type_id from the appointment's appointment_type_id
        DB::statement('
            UPDATE appointment_patient ap
            INNER JOIN appointments a ON ap.appointment_id = a.id
            SET ap.appointment_type_id = a.appointment_type_id
            WHERE ap.appointment_type_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_patient', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('appointment_patient_type_unique');
            
            // Drop the appointment_type_id column
            $table->dropForeign(['appointment_type_id']);
            $table->dropColumn('appointment_type_id');
            
            // Restore the original unique constraint
            $table->unique(['appointment_id', 'patient_id']);
        });
    }
};
