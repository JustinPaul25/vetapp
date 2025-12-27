<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Public disease search route for landing page
Route::get('/search-diseases', [\App\Http\Controllers\DiseaseSearchController::class, 'search'])->name('diseases.search');

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
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        Route::resource('pet_types', \App\Http\Controllers\Admin\PetTypeController::class);
        Route::resource('pet_breeds', \App\Http\Controllers\Admin\PetBreedController::class);
        Route::resource('pet_owners', \App\Http\Controllers\Admin\PetOwnerController::class);
        Route::post('walk_in_clients/search-pets', [\App\Http\Controllers\Admin\WalkInClientController::class, 'searchPets'])->name('walk_in_clients.search-pets');
        Route::post('walk_in_clients/lookup-by-email', [\App\Http\Controllers\Admin\WalkInClientController::class, 'lookupByEmail'])->name('walk_in_clients.lookup-by-email');
        Route::resource('walk_in_clients', \App\Http\Controllers\Admin\WalkInClientController::class);
        
        // Settings routes
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('index');
            Route::patch('/', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('update');
            Route::get('/api', [\App\Http\Controllers\Admin\SettingsController::class, 'getSettings'])->name('api');
        });
    });

// Ably token endpoint for client-side authentication
Route::middleware(['auth', 'verified'])->get('/api/ably/token', [\App\Http\Controllers\AblyController::class, 'getToken'])->name('ably.token');

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

// Admin and Staff routes
Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsAdminOrStaff::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('patients', \App\Http\Controllers\Admin\PatientController::class);
        Route::get('patients/{patient}/weight-history', [\App\Http\Controllers\Admin\PatientController::class, 'getWeightHistory'])->name('patients.weight-history');
        Route::post('patients/{patient}/weight-history', [\App\Http\Controllers\Admin\PatientController::class, 'storeWeightHistory'])->name('patients.weight-history.store');
        Route::resource('medicines', \App\Http\Controllers\Admin\MedicineController::class);
        
        // Prescription routes
        Route::prefix('prescriptions')->name('prescriptions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PrescriptionController::class, 'index'])->name('all');
        });
        
        // Appointment routes
        Route::prefix('appointments')->name('appointments.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AppointmentController::class, 'index'])->name('index');
            // Removed create and store routes - only clients can create appointments
            Route::get('/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'show'])->name('show');
            Route::patch('/{id}/approve', [\App\Http\Controllers\Admin\AppointmentController::class, 'approve'])->name('approve');
            Route::get('/{id}/prescription/create', [\App\Http\Controllers\Admin\AppointmentController::class, 'createPrescription'])->name('prescription.create');
            Route::post('/{id}/prescribe', [\App\Http\Controllers\Admin\AppointmentController::class, 'prescribe'])->name('prescribe');
            Route::get('/{id}/prescription', [\App\Http\Controllers\Admin\AppointmentController::class, 'downloadPrescription'])->name('prescription');
            Route::get('/{id}/prescription/debug', [\App\Http\Controllers\Admin\AppointmentController::class, 'debugPrescription'])->name('prescription.debug');
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
