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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_type_id')->constrained('appointment_types')->onUpdate('cascade');
            
            $table->string('symptoms', 1825)->nullable();
            
            $table->foreignId('patient_id')->constrained('patients')->onUpdate('cascade');
            
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            
            $table->date('appointment_date')->nullable(false);
            $table->string('appointment_time')->nullable();
            $table->boolean('is_approved')->nullable();
            $table->boolean('is_completed')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
