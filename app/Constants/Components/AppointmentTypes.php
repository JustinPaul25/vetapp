<?php 

namespace App\Constants\Components;

class AppointmentTypes 
{
    public const CHECK_UP = 'Check-up';
    public const VACCINATION = 'Vaccination';
    public const CASTRATION = 'Castration';
    public const MINOR_SURGERY = 'Minor Surgery';
    public const DEWORMING = 'Deworming';
    public const CONSULTATION = 'Consultation';
    
    public const ALL_TYPES = [
        self::CHECK_UP, 
        self::VACCINATION, 
        self::CASTRATION,
        self::MINOR_SURGERY,
        self::DEWORMING,
        self::CONSULTATION
    ];
}







