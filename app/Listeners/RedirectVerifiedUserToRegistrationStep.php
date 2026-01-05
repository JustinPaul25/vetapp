<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Session;

class RedirectVerifiedUserToRegistrationStep
{
    /**
     * Handle the event.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;
        
        // Check if user is in registration flow (no client role assigned yet)
        if (!$user->hasRole('client') && !$user->hasAnyRole(['admin', 'staff', 'walk_in_client'])) {
            // Store in session that we should redirect to address step
            Session::put('registration.verified', true);
            Session::put('registration.redirect_to', route('register.address'));
        }
    }
}