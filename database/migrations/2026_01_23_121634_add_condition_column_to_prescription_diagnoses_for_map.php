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
        Schema::table('prescription_diagnoses', function (Blueprint $table) {
            if (!Schema::hasColumn('prescription_diagnoses', 'condition')) {
                $table->string('condition')->nullable()->after('disease_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescription_diagnoses', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};
