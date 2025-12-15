<?php

namespace Database\Seeders;

use App\Models\AppointmentType;
use App\Constants\Components\AppointmentTypes;
use Illuminate\Database\Seeder;

class AppointmentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = AppointmentTypes::ALL_TYPES;

        foreach ($data as $item) {
            $appointment_type = AppointmentType::where('name', $item)->first();
            if (empty($appointment_type)) {
                AppointmentType::create([
                    'name' => $item,
                ]);
                echo sprintf("Appointment Type - %s has been added \n", $item);
            } else {
                $appointment_type->name = $item;
                $appointment_type->save();
            }
        }
    }
}







