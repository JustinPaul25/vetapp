<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Multi-step registration routes
Route::middleware(['guest'])->group(function () {
    Route::get('/register', [\App\Http\Controllers\Auth\RegistrationController::class, 'create'])->name('register');
    Route::post('/register/step1', [\App\Http\Controllers\Auth\RegistrationController::class, 'storeStep1'])->name('register.step1');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/register/verify-email', [\App\Http\Controllers\Auth\RegistrationController::class, 'showVerifyEmail'])->name('register.verify-email');
    Route::post('/register/verify-code', [\App\Http\Controllers\Auth\RegistrationController::class, 'verifyCode'])->name('register.verify-code');
    Route::post('/register/resend-verification', [\App\Http\Controllers\Auth\RegistrationController::class, 'resendVerificationEmail'])->name('register.resend-verification');
    Route::get('/register/address', [\App\Http\Controllers\Auth\RegistrationController::class, 'showAddress'])->name('register.address');
    Route::post('/register/address', [\App\Http\Controllers\Auth\RegistrationController::class, 'storeAddress'])->name('register.address.store');
    Route::get('/register/password', [\App\Http\Controllers\Auth\RegistrationController::class, 'showPassword'])->name('register.password');
    Route::post('/register/password', [\App\Http\Controllers\Auth\RegistrationController::class, 'storePassword'])->name('register.password.store');
    Route::get('/register/review', [\App\Http\Controllers\Auth\RegistrationController::class, 'showReview'])->name('register.review');
    Route::post('/register/finalize', [\App\Http\Controllers\Auth\RegistrationController::class, 'finalize'])->name('register.finalize');

    // General email verification routes (for non-registration flows)
    Route::post('/email/verify-code', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verifyCode'])->name('email.verify-code');
    Route::post('/email/resend-verification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resendVerificationCode'])->name('email.resend-verification');
});

// Public disease search route for landing page
Route::get('/search-diseases', [\App\Http\Controllers\DiseaseSearchController::class, 'search'])->name('diseases.search');

// Public prescription download route (signed URL for security)
Route::get('/prescriptions/{id}/download', [\App\Http\Controllers\Admin\AppointmentController::class, 'publicDownloadPrescription'])
    ->name('prescriptions.public.download')
    ->middleware('signed');

// Geocoding proxy route (to avoid CORS issues with Nominatim)
Route::get('/api/geocode/search', [\App\Http\Controllers\GeocodingController::class, 'search'])->name('geocode.search');

Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Client routes (authenticated users)
Route::middleware(['auth', 'verified'])->group(function () {
    // Appointment routes
    Route::prefix('appointments')->name('client.appointments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ClientController::class, 'appointments'])->name('index');
        Route::post('/', [\App\Http\Controllers\ClientController::class, 'bookAppointment'])->name('book');
        Route::get('/times/available', [\App\Http\Controllers\ClientController::class, 'getAvailableTimes'])->name('times.available');
        Route::patch('/{id}/reschedule', [\App\Http\Controllers\ClientController::class, 'rescheduleAppointment'])->name('reschedule');
        Route::delete('/{id}', [\App\Http\Controllers\ClientController::class, 'cancelAppointment'])->name('cancel');
        Route::get('/{id}', [\App\Http\Controllers\ClientController::class, 'showAppointments'])->name('show');
    });

    // Pet management routes
    Route::prefix('pets')->name('client.pets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ClientController::class, 'pets'])->name('index');
        Route::get('/create', [\App\Http\Controllers\ClientController::class, 'createPet'])->name('create');
        Route::post('/', [\App\Http\Controllers\ClientController::class, 'storePet'])->name('store');
        Route::get('/{pet}/edit', [\App\Http\Controllers\ClientController::class, 'editPet'])->name('edit');
        Route::put('/{pet}', [\App\Http\Controllers\ClientController::class, 'updatePet'])->name('update');
        Route::delete('/{pet}', [\App\Http\Controllers\ClientController::class, 'destroyPet'])->name('destroy');
    });
});

// Admin-only routes
Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users/export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        
        // Prescription creation routes (admin-only)
        Route::prefix('appointments')->name('appointments.')->group(function () {
            Route::get('/{id}/prescription/create', [\App\Http\Controllers\Admin\AppointmentController::class, 'createPrescription'])->name('prescription.create');
            Route::post('/{id}/prescribe', [\App\Http\Controllers\Admin\AppointmentController::class, 'prescribe'])->name('prescribe');
        });
        
        // Settings routes
        Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::patch('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update.patch');
        Route::get('settings/api', [\App\Http\Controllers\Admin\SettingsController::class, 'getSettings'])->name('settings.api');
        Route::get('settings/metrics', [\App\Http\Controllers\Admin\SettingsController::class, 'getMetrics'])->name('settings.metrics');
    });

// Ably token endpoint for client-side authentication
Route::middleware(['auth', 'verified'])->get('/api/ably/token', [\App\Http\Controllers\AblyController::class, 'getToken'])->name('ably.token');

// Notification routes (accessible to all authenticated users - controller filters by user)
Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('markAsRead');
        Route::post('/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('markAllAsRead');
        Route::get('/api/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])->name('api.unreadCount');
        Route::get('/api/list', [\App\Http\Controllers\NotificationController::class, 'getNotifications'])->name('api.list');
    });

// Test route for Ably (remove in production)
Route::middleware(['auth', 'verified'])->get('/test/ably', function () {
    $ablyService = app(\App\Services\AblyService::class);
    $apiKey = config('services.ably.key');
    
    $status = [
        'configured' => !empty($apiKey),
        'api_key_set' => !empty($apiKey),
        'api_key_preview' => $apiKey ? substr($apiKey, 0, 20) . '...' : 'Not set',
    ];

    try {
        $testData = [
            'message' => 'Test message from web route',
            'timestamp' => now()->toDateTimeString(),
            'user_id' => auth()->id(),
        ];

        $result = $ablyService->publishToAdmins('test', $testData);
        $status['publish_success'] = $result;
        $status['message'] = $result ? '✅ Ably is working!' : '❌ Failed to publish';
    } catch (\Exception $e) {
        $status['publish_success'] = false;
        $status['error'] = $e->getMessage();
        $status['message'] = '❌ Error: ' . $e->getMessage();
    }

    return response()->json($status);
})->name('test.ably');

// Test route for Brevo Email (remove in production)
Route::middleware(['auth', 'verified'])->get('/test/brevo', function () {
    $status = [
        'mail_mailer' => config('mail.default'),
        'brevo_api_key_set' => !empty(config('services.brevo.key')),
        'brevo_api_key_preview' => config('services.brevo.key') ? substr(config('services.brevo.key'), 0, 20) . '...' : 'Not set',
        'mail_from_address' => config('mail.from.address'),
        'mail_from_name' => config('mail.from.name'),
        'queue_connection' => config('queue.default'),
    ];

    try {
        \Mail::raw('This is a test email from your Laravel application using Brevo API.', function ($message) {
            $message->to(auth()->user()->email)
                    ->subject('Test Email from VetApp (Brevo API)');
        });
        
        $status['email_sent'] = true;
        $status['message'] = '✅ Email sent successfully! Check your inbox (and spam folder).';
        $status['note'] = 'If using queue, make sure queue worker is running: php artisan queue:work';
    } catch (\Exception $e) {
        $status['email_sent'] = false;
        $status['error'] = $e->getMessage();
        $status['message'] = '❌ Error: ' . $e->getMessage();
    }

    return response()->json($status);
})->name('test.brevo');

// Admin and Staff routes
Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsAdminOrStaff::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Export routes must come before resource routes to avoid route conflicts
        Route::get('patients/export', [\App\Http\Controllers\Admin\PatientController::class, 'export'])->name('patients.export');
        Route::resource('patients', \App\Http\Controllers\Admin\PatientController::class);
        Route::get('patients/{patient}/weight-history', [\App\Http\Controllers\Admin\PatientController::class, 'getWeightHistory'])->name('patients.weight-history');
        Route::post('patients/{patient}/weight-history', [\App\Http\Controllers\Admin\PatientController::class, 'storeWeightHistory'])->name('patients.weight-history.store');
        Route::get('medicines/export', [\App\Http\Controllers\Admin\MedicineController::class, 'export'])->name('medicines.export');
        Route::resource('medicines', \App\Http\Controllers\Admin\MedicineController::class);
        
        // Reference Data - accessible to both admin and staff
        Route::resource('pet_types', \App\Http\Controllers\Admin\PetTypeController::class);
        Route::resource('pet_breeds', \App\Http\Controllers\Admin\PetBreedController::class);
        Route::resource('pet_owners', \App\Http\Controllers\Admin\PetOwnerController::class);
        Route::resource('symptoms', \App\Http\Controllers\Admin\SymptomController::class);
        
        // Prescription routes (view only for staff, admin can also create via appointments route)
        Route::prefix('prescriptions')->name('prescriptions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PrescriptionController::class, 'index'])->name('all');
            Route::get('/export', [\App\Http\Controllers\Admin\PrescriptionController::class, 'export'])->name('export');
            Route::get('/{id}', [\App\Http\Controllers\Admin\PrescriptionController::class, 'show'])->name('show');
        });
        
        // Walk-in client routes (admin and staff)
        Route::post('walk_in_clients/search-pets', [\App\Http\Controllers\Admin\WalkInClientController::class, 'searchPets'])->name('walk_in_clients.search-pets');
        Route::post('walk_in_clients/lookup-by-email', [\App\Http\Controllers\Admin\WalkInClientController::class, 'lookupByEmail'])->name('walk_in_clients.lookup-by-email');
        Route::get('walk_in_clients/export', [\App\Http\Controllers\Admin\WalkInClientController::class, 'export'])->name('walk_in_clients.export');
        Route::resource('walk_in_clients', \App\Http\Controllers\Admin\WalkInClientController::class);
        
        // Appointment routes (admin and staff)
        Route::prefix('appointments')->name('appointments.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AppointmentController::class, 'index'])->name('index');
            // Removed create and store routes - only clients can create appointments
            Route::get('/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'show'])->name('show');
            Route::patch('/{id}/approve', [\App\Http\Controllers\Admin\AppointmentController::class, 'approve'])->name('approve');
            Route::patch('/{id}/reschedule', [\App\Http\Controllers\Admin\AppointmentController::class, 'reschedule'])->name('reschedule');
            // Prescription viewing routes (admin and staff can view/download prescriptions)
            Route::get('/{id}/prescription', [\App\Http\Controllers\Admin\AppointmentController::class, 'downloadPrescription'])->name('prescription');
            Route::get('/{id}/prescription/print', [\App\Http\Controllers\Admin\AppointmentController::class, 'printPrescription'])->name('prescription.print');
            Route::get('/{id}/prescription/debug', [\App\Http\Controllers\Admin\AppointmentController::class, 'debugPrescription'])->name('prescription.debug');
            
            // Disabled dates management routes
            Route::get('/disabled-dates', [\App\Http\Controllers\Admin\AppointmentController::class, 'getDisabledDates'])->name('disabled-dates.index');
            Route::post('/disabled-dates', [\App\Http\Controllers\Admin\AppointmentController::class, 'disableDate'])->name('disabled-dates.store');
            Route::delete('/disabled-dates/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'enableDate'])->name('disabled-dates.destroy');
        });
        
        // Disease routes - specific routes must come before resource routes
        Route::prefix('diseases')->name('diseases.')->group(function () {
            Route::get('/search', [\App\Http\Controllers\Admin\DiseaseController::class, 'search'])->name('search');
            Route::get('/search-by-symptoms', [\App\Http\Controllers\Admin\DiseaseController::class, 'searchBySymptoms'])->name('search-by-symptoms');
            Route::get('/statistics', [\App\Http\Controllers\Admin\DiseaseController::class, 'statistics'])->name('statistics');
            Route::get('/map', [\App\Http\Controllers\Admin\DiseaseController::class, 'map'])->name('map');
            Route::get('/training-data/medicines', [\App\Http\Controllers\Admin\DiseaseController::class, 'getMedicineTrainingData'])->name('training-data.medicines');
            Route::get('/training-data/symptoms', [\App\Http\Controllers\Admin\DiseaseController::class, 'getSymptomTrainingData'])->name('training-data.symptoms');
            Route::get('/{id}/medicines', [\App\Http\Controllers\Admin\DiseaseController::class, 'getMedicines'])->name('medicines');
        });
        Route::resource('diseases', \App\Http\Controllers\Admin\DiseaseController::class);
    });

require __DIR__.'/settings.php';
