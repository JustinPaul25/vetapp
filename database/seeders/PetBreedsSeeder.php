<?php

namespace Database\Seeders;

use App\Models\PetBreed;
use App\Models\PetType;
use Illuminate\Database\Seeder;

class PetBreedsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get pet types by name
        $petTypes = PetType::pluck('id', 'name')->toArray();

        // Dog breeds
        if (isset($petTypes['Dog'])) {
            $dogBreeds = [
                'Golden Retriever',
                'Labrador Retriever',
                'German Shepherd',
                'Bulldog',
                'Beagle',
                'Poodle',
                'Rottweiler',
                'Yorkshire Terrier',
                'Dachshund',
                'Siberian Husky',
                'Shih Tzu',
                'Boxer',
                'French Bulldog',
                'Great Dane',
                'Border Collie',
                'Australian Shepherd',
                'Cocker Spaniel',
                'Chihuahua',
                'Pomeranian',
                'Maltese',
                'Boston Terrier',
                'Jack Russell Terrier',
                'Doberman Pinscher',
                'Saint Bernard',
                'Basset Hound',
                'Bloodhound',
                'English Mastiff',
                'Newfoundland',
                'Bernese Mountain Dog',
                'Alaskan Malamute',
                'Aspin',
                'Mixed Breed',
            ];

            foreach ($dogBreeds as $breed) {
                PetBreed::firstOrCreate([
                    'name' => $breed,
                    'pet_type_id' => $petTypes['Dog'],
                ]);
            }
        }

        // Cat breeds
        if (isset($petTypes['Cat'])) {
            $catBreeds = [
                'Persian',
                'Maine Coon',
                'British Shorthair',
                'Ragdoll',
                'Bengal',
                'Siamese',
                'American Shorthair',
                'Scottish Fold',
                'Sphynx',
                'Abyssinian',
                'Russian Blue',
                'Norwegian Forest Cat',
                'Turkish Angora',
                'Oriental Shorthair',
                'Himalayan',
                'Birman',
                'Exotic Shorthair',
                'Devon Rex',
                'Cornish Rex',
                'Manx',
                'American Curl',
                'Somali',
                'Tonkinese',
                'Chartreux',
                'Burmese',
                'Puspin',
                'Mixed Breed',
            ];

            foreach ($catBreeds as $breed) {
                PetBreed::firstOrCreate([
                    'name' => $breed,
                    'pet_type_id' => $petTypes['Cat'],
                ]);
            }
        }

        // Rabbit breeds
        if (isset($petTypes['Rabbit'])) {
            $rabbitBreeds = [
                'Holland Lop',
                'Mini Rex',
                'Netherland Dwarf',
                'Lionhead',
                'Flemish Giant',
                'Angora',
                'Californian',
                'New Zealand',
                'English Lop',
                'French Lop',
                'Rex',
                'Mini Lop',
                'Dutch',
                'Polish',
                'American',
                'Belgian Hare',
                'English Spot',
                'Harlequin',
                'Jersey Wooly',
                'Lop Eared',
                'Mixed Breed',
            ];

            foreach ($rabbitBreeds as $breed) {
                PetBreed::firstOrCreate([
                    'name' => $breed,
                    'pet_type_id' => $petTypes['Rabbit'],
                ]);
            }
        }

        // Pig breeds
        if (isset($petTypes['Pig'])) {
            $pigBreeds = [
                'Vietnamese Pot-Bellied',
                'Juliana',
                'Kunekune',
                'Yorkshire',
                'Hampshire',
                'Berkshire',
                'Duroc',
                'Landrace',
                'Large White',
                'Tamworth',
                'Gloucestershire Old Spot',
                'Hereford',
                'American Guinea Hog',
                'Ossabaw Island',
                'Mulefoot',
                'Native Pig',
                'Mixed Breed',
            ];

            foreach ($pigBreeds as $breed) {
                PetBreed::firstOrCreate([
                    'name' => $breed,
                    'pet_type_id' => $petTypes['Pig'],
                ]);
            }
        }

        // Bird breeds (if exists)
        if (isset($petTypes['Bird'])) {
            $birdBreeds = [
                'Budgerigar',
                'Cockatiel',
                'Lovebird',
                'Canary',
                'Finch',
                'Parrot',
                'Cockatoo',
                'Macaw',
                'African Grey',
                'Conure',
                'Parakeet',
                'Dove',
                'Pigeon',
                'Mixed Breed',
            ];

            foreach ($birdBreeds as $breed) {
                PetBreed::firstOrCreate([
                    'name' => $breed,
                    'pet_type_id' => $petTypes['Bird'],
                ]);
            }
        }

        // For any other pet types, add generic breeds
        $genericBreeds = ['Mixed Breed', 'Unknown', 'Other'];
        
        foreach ($petTypes as $typeName => $typeId) {
            if (!in_array($typeName, ['Dog', 'Cat', 'Rabbit', 'Pig', 'Bird'])) {
                foreach ($genericBreeds as $breed) {
                    PetBreed::firstOrCreate([
                        'name' => $breed,
                        'pet_type_id' => $typeId,
                    ]);
                }
            }
        }
    }
}
