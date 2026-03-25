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
        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete()->cascadeOnUpdate();

            $table->string('vaccine_name');
            $table->date('administered_at');
            $table->date('next_due_at')->nullable();
            $table->string('batch_lot_number', 150)->nullable();
            $table->string('veterinarian', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 50)->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'administered_at']);
            $table->index(['user_id', 'administered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaccination_records');
    }
};
