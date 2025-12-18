<?php

use App\Models\PetType;
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
        // Delete the "Not Specified" pet type if it exists and has no patients
        $petType = PetType::where('name', 'Not Specified')->first();
        
        if ($petType) {
            // Check if any patients are using this pet type
            $patientsCount = $petType->patients()->count();
            
            if ($patientsCount === 0) {
                $petType->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create the "Not Specified" pet type if it doesn't exist
        PetType::firstOrCreate(['name' => 'Not Specified']);
    }
};
