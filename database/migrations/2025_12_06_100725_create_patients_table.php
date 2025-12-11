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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_type_id')->constrained('pet_types')->onUpdate('cascade');
            
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            
            $table->string('pet_name', 100)->nullable();
            $table->string('pet_breed', 100)->nullable(false);
            $table->string('pet_gender', 100)->nullable();
            $table->date('pet_birth_date')->nullable();
            $table->string('microchip_number', 100)->nullable();
            $table->string('pet_allergies')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
