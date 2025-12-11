# Client/Customer Appointment Module Documentation

This document provides a complete guide to replicate the **Client/Customer Appointment Module** in a new project. This module allows clients/customers to view their appointments, book new appointments, and manage their appointment history.

## Table of Contents
1. [Overview](#overview)
2. [Features](#features)
3. [Database Structure](#database-structure)
4. [Models](#models)
5. [Controllers](#controllers)
6. [Routes](#routes)
7. [Views](#views)
8. [JavaScript Functionality](#javascript-functionality)
9. [Notifications](#notifications)
10. [Time Slot Management](#time-slot-management)
11. [Implementation Steps](#implementation-steps)
12. [Dependencies](#dependencies)

---

## Overview

The Client Appointment Module enables authenticated users (clients/customers) to:
- View all their appointments in a DataTables-powered table
- Book new appointments for their pets
- View detailed information about specific appointments
- Check time slot availability in real-time
- Receive email notifications when appointments are approved

**Appointment Status Flow:**
1. **Pending** - Client books appointment (`is_approved = false`)
2. **Approved** - Admin approves appointment (`is_approved = true`)
3. **Completed** - Prescription created (`is_completed = true`)

---

## Features

### Client Features
- ✅ View appointments list with DataTables (server-side processing)
- ✅ Book new appointments via modal form
- ✅ Real-time time slot availability checking
- ✅ View appointment details (read-only)
- ✅ Filter appointments by status (Pending/Approved)
- ✅ Search appointments by pet name, type, etc.

### Technical Features
- ✅ AJAX-powered appointment booking
- ✅ Dynamic time slot population based on selected date
- ✅ Date picker with minimum date validation (next day)
- ✅ Automatic notification to admins when appointment is booked
- ✅ Integration with Patient/Pet module
- ✅ Integration with Appointment Types

---

## Database Structure

### Appointments Table

The appointments table stores all appointment data. Key fields for client module:

```sql
CREATE TABLE `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_type_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(255) NULL,
  `symptoms` varchar(1825) NULL,
  `is_approved` boolean NULL,
  `is_completed` boolean NULL,
  `remarks` varchar(255) NULL,
  `pet_weight` varchar(100) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`appointment_type_id`) REFERENCES `appointment_types`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
```

**Key Fields for Client Module:**
- `patient_id` - Links to the pet/patient
- `user_id` - Links to the client/user who owns the pet
- `appointment_date` - Date of appointment
- `appointment_time` - Time in 24-hour format (H:i)
- `symptoms` - Optional symptoms description
- `is_approved` - Approval status (false = Pending, true = Approved)
- `is_completed` - Completion status (true when prescription exists)

### Appointment Types Table

```sql
CREATE TABLE `appointment_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

**Default Types:**
- Check-up
- Vaccination
- Dental

---

## Models

### Appointment Model

**File:** `app/Models/Entities/Appointment.php`

```php
<?php

namespace App\Models\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $date = ['appointment_date'];
    
    protected $guarded = [];
    
    public function appointment_type()
    {
        return $this->belongsTo('App\Models\Entities\AppointmentType');
    }

    public function patient()
    {
        return $this->belongsTo('App\Models\Entities\Patient');
    }

    public function prescription()
    {
        return $this->hasOne('App\Models\Entities\Prescription');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\Entities\User');
    }
}
```

### AppointmentType Model

**File:** `app/Models/Entities/AppointmentType.php`

```php
<?php

namespace App\Models\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentType extends Model
{
    use HasFactory;
}
```

### Related Models Required

- `Patient` - Pet/patient information (must have `user_id` relationship)
- `User` - User/client information
- `PetType` - Pet type information

---

## Controllers

### ClientController - Appointment Methods

**File:** `app/Http/Controllers/ClientController.php`

#### 1. appointments() - List Client Appointments

Displays all appointments for the authenticated user's pets.

```php
public function appointments(Request $request)
{
    $pets = Patient::where('user_id', auth()->user()->id)->get();
    $appointment_types = AppointmentType::get();

    if ($request->ajax()) {
        $keyword = $request->search['value'];
        
        $appointments = Appointment::select(
            'appointments.*',
            'appointment_types.name as appointment_type',
            'patients.pet_name',
            'pt.name as pet_type',
            DB::raw("IF(appointments.is_approved = 0, 'Pending', 'Approved') as status"),
        )
        ->join('appointment_types', 'appointments.appointment_type_id', 'appointment_types.id')
        ->leftJoin('patients', 'patients.id', 'appointments.patient_id')
        ->leftJoin('pet_types as pt', 'pt.id', 'patients.pet_type_id')
        ->where('patients.user_id', auth()->user()->id);
        
        if(!empty($keyword)) {
            $appointments->where('pet_types.name', 'LIKE', "$keyword%")
                ->orWhere(DB::raw("CONCAT(patients.owner_first_name, ' ', patients.owner_last_name)"), 'LIKE', "$keyword%")
                ->orwhereRaw("diseases.name",'LIKE', "$keyword%")
                ->orWhere('patients.pet_name', 'LIKE', "$keyword%");
        }

        $datatables = datatables()::of($appointments)
            ->addColumn('actions', function ($appointment) {
                $btn = '';
                $route = route('client.appointments.show', $appointment->id);
                $btn = '<a href="'.$route.'">
                            <button type="button" class="btn btn-sm font-weight-normal" data-toggle="tooltip" data-original-title="View Appointment">
                                <i class="fa fa-fw fa-eye"></i> View
                            </button>
                        </a>';
                return $btn;
            });
            
        return $datatables->rawColumns(['actions'])->make(true);
    }
    
    return view('clients.appointments.index', compact('pets', 'appointment_types'));
}
```

**Features:**
- Returns pets and appointment types for booking form
- Supports DataTables AJAX requests
- Filters appointments by authenticated user's pets
- Searchable by pet type, owner name, disease name, pet name
- Returns status as "Pending" or "Approved"

#### 2. bookAppointment() - Create New Appointment

Creates a new appointment from the client side.

```php
public function bookAppointment(Request $request)
{
    $appointmentDate = $request->appointment_date;
    $appointmentTime = $request->appointment_time;

    // Convert time from 12-hour format (h:i A) to 24-hour format (H:i)
    $time = Carbon::createFromFormat('h:i A', $appointmentTime);

    // Create appointment (initially not approved)
    $appointment = Appointment::create([
        'patient_id' => $request->pet_id,
        'appointment_type_id' => $request->appointment_type_id,
        'appointment_date' => $request->appointment_date,
        'symptoms' => $request->symptoms,
        'is_approved' => false, // Client appointments start as pending
        'appointment_time' => $time->format('H:i'), // Store in 24-hour format
        'user_id' => Auth::user()->id
    ]);

    // Notify Super Admins
    $users = User::select('users.*')
        ->leftJoin('user_types as ut', 'ut.id', 'users.user_type_id')
        ->whereIn('ut.name', [UserTypes::SUPER_ADMIN])
        ->get();
    
    $button_style = 'background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; font-size: 12px; border-radius: 15px;';

    $patient_owner_full_name = sprintf('%s %s', 
        $appointment->patient->owner_first_name ?? '', 
        $appointment->patient->owner_last_name ?? ''
    );
    
    $link = config('app.url').'/admin/appointments/'. $appointment->id; 
    $subject = sprintf("%s has submitted new appointment.", $patient_owner_full_name ?? '');
    $message = "Hi, new appointment has been submitted<br><br>".
            "Appointment Details.<br><br>".
            "Full Name: ".$patient_owner_full_name."<br>".
            "Mobile Number: ".$appointment->patient->owner_mobile_number."<br>".
            "Email Address: ".$appointment->patient->owner_email."<br>".
            "Pet Type: ".$appointment->patient->petType->name."<br>".
            "Breed: ".$appointment->patient->pet_breed."<br>".
            "Appointment Type: ".$appointment->appointment_type->name."<br>".
            "Appointment Date: ".$request->appointment_date."<br>".
            "<p style='text-align:center'><a href='".$link."' style='".$button_style."'>View Appointment<a>";

    foreach($users as $user) {
        Notification::send($user, new DefaultNotification($subject, $message, $link));
    }

    return redirect()->back()->with(['message' => "Appointment created successfully."]);
}
```

**Features:**
- Creates appointment with `is_approved = false` (pending status)
- Converts time from 12-hour format to 24-hour format for storage
- Links appointment to authenticated user via `user_id`
- Sends notification to all Super Admins
- Returns success message

**Note:** There's commented-out validation code for:
- Working hours (9:00 - 16:00)
- Lunch break (12:00 - 13:00)
- Time slot conflicts (30-minute windows)
- Daily appointment limits (max 10 per day)

#### 3. showAppointments() - View Single Appointment

Displays detailed information about a specific appointment.

```php
public function showAppointments($id)
{
    $appointment = Appointment::select(
            'appointments.*',
            'appointment_types.name as appointment_type',
            'patients.pet_name',
            'pt.name as pet_type',
            DB::raw("IF(appointments.is_approved = 0, 'Pending', 'Approved') as status"),
        )
        ->join('appointment_types', 'appointments.appointment_type_id', 'appointment_types.id')
        ->leftJoin('patients', 'patients.id', 'appointments.patient_id')
        ->leftJoin('pet_types as pt', 'pt.id', 'patients.pet_type_id')
        ->where('appointments.id', $id)
        ->first();
        
    return view('clients.appointments.show', compact('appointment'));
}
```

**Features:**
- Loads appointment with related data (type, patient, pet type)
- Calculates status (Pending/Approved)
- Returns read-only view

### PageController - Time Availability Helper

**File:** `app/Http/Controllers/PageController.php`

#### getAppointmentTimesBaseOnDate() - Get Unavailable Times

Returns JSON list of unavailable time slots for a selected date.

```php
public function getAppointmentTimesBaseOnDate(Request $request)
{
    // Get the selected date from the AJAX request
    $selectedDate = $request->input('selectedDate');
    
    // Query the database for appointments on the selected date
    $takenTimes = Appointment::where('appointment_date', $selectedDate)
        ->pluck('appointment_time')
        ->toArray();
   
    // Convert from 24-hour format (H:i) to 12-hour format (h:i A)
    $disabled_times = array_map(function ($time) {
        return Carbon::createFromFormat('H:i', $time)->format('h:i A');
    }, $takenTimes);

    // Return only the taken (disabled) times as a JSON response
    return response()->json([
        'disabledTimes' => $disabled_times,
    ]);
}
```

**Features:**
- Public route (no authentication required)
- Returns times in 12-hour format for frontend display
- Used by JavaScript to disable unavailable time slots

---

## Routes

**File:** `routes/web.php`

### Client Appointment Routes

```php
// Client appointment routes (protected by auth middleware)
Route::middleware(['auth'])->group(function () {
    // List appointments
    Route::get('/appointments', [ClientController::class, 'appointments'])
        ->name('appointments');
    
    // View single appointment
    Route::get('/appointments/{id}', [ClientController::class, 'showAppointments'])
        ->name('appointments.show');
    
    // Book new appointment
    Route::post('/appointments', [ClientController::class, 'bookAppointment'])
        ->name('appointments.book');
});
```

### Public Route for Time Availability

```php
// Public route for time slot availability (no auth required)
Route::get('/appointments/times-by-date', [PageController::class, 'getAppointmentTimesBaseOnDate'])
    ->name('appointments.times-by-date');
```

**Route Names:**
- `client.appointments` - List appointments
- `client.appointments.show` - View appointment details
- `client.appointments.book` - Book new appointment
- `appointments.times-by-date` - Get unavailable times (public)

---

## Views

### 1. Index View - Appointments List

**File:** `resources/views/clients/appointments/index.blade.php`

```blade
@extends('layouts.frontend')
@section('with_side_menu', true) 

@section('content')
<a href="#" class="btn btn-primary btn-book-appointment ml-3 float-right-button" 
   data-bs-toggle="modal" 
   data-bs-target="#appointmentModal">
   Book an Appointment
</a>

@include('components.page-title', [
    'page_title' => 'My Appointments'
])

<div class="content mt-3">
    <div class="row">
        @include('layouts.alert')
        <div class="block-content block-content-full table-container" 
             data-datatable-url="{{ route('client.appointments') }}">
            <table class="table data-table js-dataTable display nowrap">
                <thead>
                    <tr>
                        <th>@lang('Appointment Type')</th>
                        <th>@lang('Patient Type')</th>
                        <th>@lang('Pet Name')</th>
                        <th>@lang('Date')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
            </table>        
        </div>
    </div>
</div>

<!-- Appointment Booking Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" 
     aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Book an Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                        aria-label="Close"></button>
            </div>
            <form action="{{ route('client.appointments.book') }}" 
                  id="bookAppointmentForm" method="POST">
                @csrf
                <div class="modal-body mb-5">
                    <div class="row mt-3">
                        <div class="col-6">
                            <label for="pet_id">@lang('Pet')</label>
                            <select class="form-control" id="pet_id" name="pet_id" required>
                                <option value="">Select Your Pet</option>
                                @foreach($pets as $pet)
                                    <option value="{{ $pet->id }}">{{ $pet->pet_name }}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('pet_id'))
                                <div class="invalid-feedback">{{ $errors->first('pet_id') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <label for="appointment_type_id">@lang('Appointment Purpose')</label>
                            <select class="form-control" id="appointment_type_id" 
                                    name="appointment_type_id" required>
                                <option value="">Select Appointment Type</option>
                                @foreach($appointment_types as $appointment_type)
                                    <option value="{{ $appointment_type->id }}">
                                        {{ $appointment_type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($errors->has('appointment_type_id'))
                                <div class="invalid-feedback">
                                    {{ $errors->first('appointment_type_id') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <label for="appointment_date">@lang('Appointment Date')</label>
                            <input class="form-control" id="appointment_date" type="date" 
                                   name="appointment_date" value="{{ old('appointment_date') }}" 
                                   required/>
                            @if ($errors->has('appointment_date'))
                                <div class="invalid-feedback">
                                    {{ $errors->first('appointment_date') }}
                                </div>
                            @endif
                        </div>
                        <div class="col-6">
                            <label for="appointment_time">@lang('Appointment Time')</label>
                            <select id="appointment_time" name="appointment_time" 
                                    class="form-control" required>
                                <!-- Populated dynamically by JavaScript -->
                            </select>
                            @if ($errors->has('appointment_time'))
                                <div class="invalid-feedback">
                                    {{ $errors->first('appointment_time') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <label for="symptoms">@lang('Symptoms') (Optional)</label>
                            <textarea class="form-control" id="symptoms" name="symptoms" 
                                      rows="3" placeholder="Describe any symptoms..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" 
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js_after')
<script>
    (function ($) {
        var datatable_url = $('.table-container').data('datatable-url');
        var backendUsersTable = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: datatable_url,
            columns: [
                {data: 'appointment_type', name: 'appointment_type', orderable: true, searchable: false},
                {data: 'pet_type', name: 'pet_type', orderable: true, searchable: false},
                {data: 'pet_name', name: 'pet_name', orderable: true, searchable: false},
                {data: 'appointment_date', name: 'appointment_date', orderable: true, searchable: false},
                {data: 'status', name: 'status', orderable: true, searchable: false},
                {data: 'actions', name: 'actions', orderable: false, searchable: false}                
            ],
            order: [[ 3, "desc" ]], // Sort by date descending
            orderCellsTop: true,
            scrollY: 700,
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            initComplete: function() {
                $('.dataTables_filter input').unbind();
                $('.dataTables_filter input').bind('keyup', function(e) {
                    if(e.keyCode == 13) {
                        backendUsersTable.search(this.value).draw();
                    }
                });
            },
        });
    })(jQuery);
</script>

<!-- Time Slot Management Script -->
<script>
const availableTimes = [
    "09:00 AM", "09:30 AM", "10:00 AM", "10:30 AM",
    "11:00 AM", "11:30 AM",
    "01:00 PM", "01:30 PM", "02:00 PM", "02:30 PM",
    "03:00 PM", "03:30 PM", "04:00 PM", "04:30 PM"
];

function populateTimeOptions(disabledTimes = []) {
    const selectElement = document.getElementById('appointment_time');
    if(!selectElement) return;
    
    selectElement.innerHTML = '<option value="">Select Time</option>';
    
    availableTimes.forEach(time => {
        const option = document.createElement('option');
        option.value = time;
        option.textContent = time;
        
        if (disabledTimes.includes(time)) {
            option.disabled = true;
            option.textContent = `${time} (Unavailable)`;
        }
        
        selectElement.appendChild(option);
    });
}

// Initialize with all times available
populateTimeOptions();

// Date picker with flatpickr (or native date input)
if (document.getElementById('appointment_date')) {
    $('#appointment_date').flatpickr({
        minDate: "{{ now()->addDay()->format('Y-m-d') }}", // Minimum: tomorrow
        onChange: function(selectedDates, dateStr) {
            // Fetch unavailable times for selected date
            $.ajax({
                url: "{{ route('appointments.times-by-date') }}",
                method: "GET",
                data: { selectedDate: dateStr },
                success: function(response) {
                    populateTimeOptions(response.disabledTimes);
                },
                error: function() {
                    alert("There was an error fetching available times.");
                }
            });
        }
    });
}
</script>
@endsection
```

**Key Features:**
- DataTables integration for appointments list
- Modal form for booking appointments
- Dynamic time slot population
- Date picker with minimum date validation
- Real-time availability checking

### 2. Show View - Appointment Details

**File:** `resources/views/clients/appointments/show.blade.php`

```blade
@extends('layouts.frontend')
@section('with_side_menu', true) 

@section('content')
@include('components.page-title', [
    'page_title' => 'Appointment Details'
])

<div class="content mt-3">
    <div class="row">
        @include('layouts.alert')

        <div class="block-content block-content-full">
            <div class="row">
                <div class="col-6 mb-3">
                    <label for="appointment_type">@lang('Appointment Type')</label>
                    <input class="form-control" 
                            type="text" 
                            id="appointment_type" 
                            name="appointment_type" 
                            value="{{ old('appointment_type', $appointment->appointment_type) }}" 
                            readonly />
                </div>

                <div class="col-6 mb-3">
                    <label for="pet_name">@lang('Pet Name')</label>
                    <input class="form-control" 
                            type="text" 
                            id="pet_name" 
                            name="pet_name" 
                            value="{{ old('pet_name', $appointment->pet_name) }}" 
                            readonly />
                </div>
            </div>

            <div class="row">
                <div class="col-6 mb-3">
                    <label for="pet_type">@lang('Pet Type')</label>
                    <input class="form-control" 
                            type="text" 
                            id="pet_type" 
                            name="pet_type" 
                            value="{{ old('pet_type', $appointment->pet_type) }}" 
                            readonly />
                </div>

                <div class="col-6 mb-3">
                    <label for="status">@lang('Status')</label>
                    <input class="form-control" 
                            type="text" 
                            id="status" 
                            name="status" 
                            value="{{ old('status', $appointment->status) }}" 
                            readonly />
                </div>
            </div>

            <div class="row">
                <div class="col-6 mb-3">
                    <label for="appointment_date">@lang('Appointment Date')</label>
                    <input class="form-control" 
                            type="text" 
                            id="appointment_date" 
                            name="appointment_date" 
                            value="{{ old('appointment_date', \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y')) }}" 
                            readonly />
                </div>

                <div class="col-6 mb-3">
                    <label for="appointment_time">@lang('Appointment Time')</label>
                    <input class="form-control" 
                            type="text" 
                            id="appointment_time" 
                            name="appointment_time" 
                            value="{{ old('appointment_time', \Carbon\Carbon::createFromFormat('H:i', $appointment->appointment_time)->format('h:i A') ?? '') }}" 
                            readonly />
                </div>

                <div class="col-12 mb-3">
                    <label for="remarks">@lang('Remarks')</label>
                    <textarea class="form-control" 
                                id="remarks" 
                                name="remarks" 
                                rows="4" 
                                readonly>{{ old('remarks', $appointment->remarks ?? 'No remarks') }}</textarea>
                </div>
            </div>

            <div class="form-group text-right mt-3">
                <a href="{{ route('client.appointments') }}" class="btn btn-secondary">
                    @lang('Back to Appointments')
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
```

**Features:**
- Read-only display of appointment details
- Formatted date and time display
- Back button to appointments list

---

## JavaScript Functionality

### Time Slot Management

The client appointment module uses JavaScript to:
1. Populate time slots dynamically
2. Check availability when date is selected
3. Disable unavailable time slots

**Available Time Slots:**
```javascript
const availableTimes = [
    "09:00 AM", "09:30 AM", "10:00 AM", "10:30 AM",
    "11:00 AM", "11:30 AM",
    "01:00 PM", "01:30 PM", "02:00 PM", "02:30 PM",
    "03:00 PM", "03:30 PM", "04:00 PM", "04:30 PM"
];
```

**Time Slot Flow:**
1. User selects appointment date
2. JavaScript makes AJAX call to `/appointments/times-by-date`
3. Server returns list of unavailable times
4. JavaScript disables unavailable options in dropdown
5. User can only select available time slots

### DataTables Integration

The appointments list uses DataTables with:
- Server-side processing
- AJAX data loading
- Search functionality
- Sortable columns
- Action buttons for each row

---

## Notifications

### DefaultNotification

**File:** `app/Notifications/DefaultNotification.php`

Used to notify Super Admins when a client books an appointment.

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DefaultNotification extends Notification
{
    use Queueable;

    private $link;
    private $subject;
    private $message;

    public function __construct($subject, $message, $link)
    {
        $this->link = $link;
        $this->subject = $subject;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database', 'mail']; // Stored in database AND sent via email
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject($this->subject)
                    ->line(new \Illuminate\Support\HtmlString($this->message));
    }
    
    public function toArray($notifiable)
    {
        return [
            'link' => $this->link,
            'message' => $this->subject
        ];
    }
}
```

**Notification Flow:**
1. Client books appointment
2. System finds all Super Admin users
3. Sends `DefaultNotification` to each admin
4. Notification stored in database AND sent via email
5. Email contains appointment details and link to view

---

## Time Slot Management

### Available Time Slots

The system uses predefined 30-minute time slots:
- **Morning:** 09:00 AM - 11:30 AM (5 slots)
- **Afternoon:** 01:00 PM - 04:30 PM (8 slots)
- **Total:** 13 available slots per day

### Time Format

- **Storage:** 24-hour format (`H:i`) - e.g., "09:00", "13:30"
- **Display:** 12-hour format (`h:i A`) - e.g., "09:00 AM", "01:30 PM"

### Availability Checking

1. When user selects a date, AJAX request is made
2. Server queries appointments for that date
3. Returns list of already-booked times
4. Frontend disables those times in dropdown
5. User can only select available slots

---

## Implementation Steps

### Step 1: Database Setup

1. Ensure `appointments` table exists with required fields
2. Ensure `appointment_types` table exists
3. Ensure `patients` table has `user_id` foreign key
4. Run migrations:
   ```bash
   php artisan migrate
   ```

### Step 2: Models

1. Create/verify `Appointment` model with relationships
2. Create/verify `AppointmentType` model
3. Ensure `Patient` model has `user()` relationship
4. Ensure `User` model has `patients()` relationship

### Step 3: Constants & Seeders

1. Create `AppointmentTypes` constant class
2. Create `AppointmentTypesSeeder`
3. Run seeder:
   ```bash
   php artisan db:seed --class=AppointmentTypesSeeder
   ```

### Step 4: Controllers

1. Add appointment methods to `ClientController`:
   - `appointments()` - List appointments
   - `bookAppointment()` - Create appointment
   - `showAppointments()` - View details
2. Add time availability method to `PageController`:
   - `getAppointmentTimesBaseOnDate()` - Get unavailable times

### Step 5: Routes

1. Add client appointment routes (protected by `auth` middleware):
   ```php
   Route::get('/appointments', [ClientController::class, 'appointments'])
       ->name('appointments');
   Route::get('/appointments/{id}', [ClientController::class, 'showAppointments'])
       ->name('appointments.show');
   Route::post('/appointments', [ClientController::class, 'bookAppointment'])
       ->name('appointments.book');
   ```
2. Add public route for time availability:
   ```php
   Route::get('/appointments/times-by-date', [PageController::class, 'getAppointmentTimesBaseOnDate'])
       ->name('appointments.times-by-date');
   ```

### Step 6: Views

1. Create `resources/views/clients/appointments/index.blade.php`
2. Create `resources/views/clients/appointments/show.blade.php`
3. Ensure layout files exist (`layouts.frontend`)

### Step 7: JavaScript

1. Add DataTables initialization script
2. Add time slot management script
3. Add date picker (flatpickr or native)
4. Add AJAX call for time availability

### Step 8: Notifications

1. Create `DefaultNotification` class
2. Ensure notification table exists:
   ```bash
   php artisan notifications:table
   php artisan migrate
   ```
3. Configure email settings in `.env`

### Step 9: Dependencies

1. Install required packages:
   ```bash
   composer require yajra/laravel-datatables-oracle
   ```
2. Install frontend dependencies (if using npm):
   ```bash
   npm install flatpickr
   ```

### Step 10: Testing

1. Test appointment listing
2. Test appointment booking
3. Test time slot availability
4. Test notification sending
5. Test appointment details view

---

## Dependencies

### Backend Packages

1. **Laravel DataTables** (Yajra)
   ```bash
   composer require yajra/laravel-datatables-oracle
   ```

2. **Carbon** (usually included with Laravel)
   - For date/time manipulation

3. **Laravel Notifications** (built-in)
   - For email and database notifications

### Frontend Libraries

1. **jQuery** (required for DataTables)
2. **DataTables** (via Yajra package or CDN)
3. **Flatpickr** (optional, for date picker)
   ```bash
   npm install flatpickr
   ```

### Required Models

- `Appointment` - Appointment data
- `AppointmentType` - Appointment types
- `Patient` - Pet/patient information
- `User` - User/client information
- `PetType` - Pet type information

### Required Database Tables

- `appointments`
- `appointment_types`
- `patients`
- `users`
- `pet_types`
- `notifications` (for notification storage)

---

## Additional Notes

### Authentication

All client appointment routes must be protected by `auth` middleware to ensure only authenticated users can:
- View their appointments
- Book new appointments
- View appointment details

### Authorization

The `appointments()` and `showAppointments()` methods filter appointments by `user_id` to ensure users can only see their own appointments.

### Time Format Conversion

The system converts between formats:
- **Input:** 12-hour format (`h:i A`) from form
- **Storage:** 24-hour format (`H:i`) in database
- **Display:** 12-hour format (`h:i A`) in views

### Appointment Status

- **Pending:** `is_approved = false` (client-created appointments)
- **Approved:** `is_approved = true` (admin-approved)
- **Completed:** `is_completed = true` (prescription created)

### Integration Points

The client appointment module integrates with:
- **Patient Module:** For pet selection
- **Appointment Types:** For appointment purpose
- **Admin Module:** For appointment approval
- **Prescription Module:** For appointment completion

---

## File Structure Summary

```
app/
├── Http/
│   └── Controllers/
│       ├── ClientController.php (appointment methods)
│       └── PageController.php (time availability)
├── Models/
│   └── Entities/
│       ├── Appointment.php
│       └── AppointmentType.php
└── Notifications/
    └── DefaultNotification.php

database/
├── migrations/
│   ├── create_appointments_table.php
│   └── create_appointment_types_table.php
└── seeders/
    └── AppointmentTypesSeeder.php

resources/
└── views/
    └── clients/
        └── appointments/
            ├── index.blade.php
            └── show.blade.php

routes/
└── web.php (client appointment routes)
```

---

## End of Documentation

This document provides a complete guide to replicate the Client/Customer Appointment Module. Ensure all dependencies and related modules (Patients, Users, etc.) are properly set up before implementing this module.

For admin-side appointment management, refer to the full `APPOINTMENT_MODULE.md` documentation.
