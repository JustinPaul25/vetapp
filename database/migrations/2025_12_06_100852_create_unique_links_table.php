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
        Schema::create('unique_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 518)->unique()->nullable(false);
            $table->timestamp('date_expiry')->nullable(false);
            $table->timestamp('date_processed')->nullable(true);
            $table->foreignId('link_type_id')->constrained('unique_link_types');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unique_links');
    }
};
