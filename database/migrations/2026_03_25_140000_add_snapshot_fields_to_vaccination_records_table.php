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
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->string('owner_name', 255)->nullable()->after('patient_id');
            $table->string('pet_name', 150)->nullable()->after('owner_name');
            $table->string('pet_sex', 50)->nullable()->after('pet_name');
            $table->date('pet_date_of_birth')->nullable()->after('pet_sex');
            $table->string('pet_breed', 150)->nullable()->after('pet_date_of_birth');
            $table->string('pet_color', 150)->nullable()->after('pet_breed');
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->string('vaccine_name')->nullable()->change();
            $table->date('administered_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->dropColumn([
                'owner_name',
                'pet_name',
                'pet_sex',
                'pet_date_of_birth',
                'pet_breed',
                'pet_color',
            ]);
        });

        Schema::table('vaccination_records', function (Blueprint $table) {
            $table->string('vaccine_name')->nullable(false)->change();
            $table->date('administered_at')->nullable(false)->change();
        });
    }
};
