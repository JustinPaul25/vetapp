<?php 

namespace App\Constants\Components;

class PetBreeds 
{
    // Dog breeds
    public const DOG_BREEDS = [
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
    ];

    // Cat breeds
    public const CAT_BREEDS = [
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
    ];

    // Rabbit breeds
    public const RABBIT_BREEDS = [
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
    ];

    // Pig breeds
    public const PIG_BREEDS = [
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
    ];

    // Not Specified breeds (generic)
    public const NOT_SPECIFIED_BREEDS = [
        'Mixed Breed',
        'Unknown',
        'Other',
    ];

    /**
     * Get breeds for a specific pet type
     */
    public static function getBreedsForPetType(string $petType): array
    {
        return match($petType) {
            'Dog' => self::DOG_BREEDS,
            'Cat' => self::CAT_BREEDS,
            'Rabbit' => self::RABBIT_BREEDS,
            'Pig' => self::PIG_BREEDS,
            'Not Specified' => self::NOT_SPECIFIED_BREEDS,
            default => [],
        };
    }

    /**
     * Get all breeds as a flat array
     */
    public static function getAllBreeds(): array
    {
        return array_merge(
            self::DOG_BREEDS,
            self::CAT_BREEDS,
            self::RABBIT_BREEDS,
            self::PIG_BREEDS,
            self::NOT_SPECIFIED_BREEDS
        );
    }
}
