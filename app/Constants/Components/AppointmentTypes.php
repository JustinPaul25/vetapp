<?php 

namespace App\Constants\Components;

class AppointmentTypes 
{
    public const CHECK_UP = 'Check-up';
    public const VACCINATION = 'Vaccination';
    public const DENTAL = 'Dental';
    
    public const ALL_TYPES = [self::CHECK_UP, self::VACCINATION, self::DENTAL];
}




