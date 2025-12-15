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
        Schema::dropIfExists('appointment_appointment_type');
        
        Schema::create('appointment_appointment_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('appointment_type_id')->constrained('appointment_types')->onDelete('cascade');
            $table->timestamps();
            
            // Ensure unique combination with shorter index name
            $table->unique(['appointment_id', 'appointment_type_id'], 'appt_appt_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_appointment_type');
    }
};
