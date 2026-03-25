<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow partial data (e.g. Excel imports) without placeholder strings.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('pet_breed', 100)->nullable()->change();
        });

        Schema::table('prescription_medicines', function (Blueprint $table) {
            $table->string('dosage')->nullable()->change();
            $table->string('instructions', 1825)->nullable()->change();
            $table->string('quantity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('pet_breed', 100)->nullable(false)->change();
        });

        Schema::table('prescription_medicines', function (Blueprint $table) {
            $table->string('dosage')->nullable(false)->change();
            $table->string('instructions', 1825)->nullable(false)->change();
            $table->string('quantity')->nullable(false)->change();
        });
    }
};
