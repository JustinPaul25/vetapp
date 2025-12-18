<?php

namespace Database\Seeders;

use App\Models\PetType;
use App\Constants\Components\PetTypes;
use Illuminate\Database\Seeder;

class PetTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = PetTypes::ALL_TYPES;

        foreach ($data as $item) {
            $pet_type = PetType::where('name', $item)->first();
            if (empty($pet_type)) {
                PetType::create([
                    'name' => $item,
                ]);
                echo sprintf("Pet Type - %s has been added \n", $item);
            } else {
                $pet_type->name = $item;
                $pet_type->save();
            }
        }
    }
}












