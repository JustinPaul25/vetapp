<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'enable_knn_prediction',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable or disable KNN machine learning predictions for disease diagnosis and medicine recommendations',
            ],
            [
                'key' => 'enable_logistic_regression_prediction',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable or disable Logistic Regression machine learning predictions for disease diagnosis and medicine recommendations',
            ],
            [
                'key' => 'enable_neural_network_prediction',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable or disable Neural Network (Deep Learning) machine learning predictions for disease diagnosis and medicine recommendations',
            ],
            [
                'key' => 'veterinarian_name',
                'value' => '',
                'type' => 'string',
                'description' => 'Name of the veterinarian that will appear on prescription documents',
            ],
            [
                'key' => 'veterinarian_license_number',
                'value' => '',
                'type' => 'string',
                'description' => 'License number of the veterinarian that will appear on prescription documents',
            ],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                ]
            );
        }
    }
}
