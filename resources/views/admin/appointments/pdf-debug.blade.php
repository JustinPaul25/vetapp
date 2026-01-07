<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Prescription - Debug View</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.4;
            padding: 20px;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
        }
        
        .debug-container {
            background-color: white;
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            padding: 12.7mm; /* 0.5 inch margin */
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .debug-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #ff6b6b;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            z-index: 1000;
        }
        
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* Global table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .noborder-table,
        .noborder-table td,
        .noborder-table th {
            border: none;
        }
        
        /* Header Section */
        .header-table {
            margin-top: 0;
            width: 100%;
            margin-bottom: 10px;
        }

        .header-table tr {
            display: flex;
            justify-content: center;
        }
        
        .header-table td {
            vertical-align: top;
            border: none;
            padding: 5px 5px 0px 5px;
        }
        
        .header-center {
            text-align: center;
            line-height: 1.2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .header-logo {
            width: 65px;
            height: auto;
        }
        
        .header-org-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .header-address {
            font-size: 10px;
        }
        
        .header-section-name {
            font-size: 8px;
            font-weight: bold;
            margin: 0;
        }
        
        .header-separator {
            border: none;
            border-top: 1px solid #1a4a75;
            margin: 12px 0 15px 0;
        }
        
        .prescription-logo {
            width: 27px;
            height: auto;
            display: block;
            margin-left: 15px;
            margin-right: auto;
            margin-bottom: 10px;
        }
        
        /* Patient Information Section */
        .patient-info-table {
            margin-top: 15px;
        }
        
        .patient-info-table td {
            border: none;
            padding: 2px 8px;
        }
        
        .patient-info-label {
            font-weight: bold;
        }
        
        .section-header {
            font-weight: bold;
            margin-top: 18px;
            border-bottom: 1px solid #1a4a75;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        
        /* Medicines Section */
        .medicines-table {
            margin-top: 12px;
        }
        
        .medicines-table th,
        .medicines-table td {
            border: none;
            text-align: left;
            vertical-align: top;
            padding: 4px 8px;
        }

        .medicines-table tbody tr + tr td {
            padding-top: 8px;
        }
        
        .medicines-table th {
            background-color: #EEE;
            font-weight: bold;
            text-align: left;
            vertical-align: top;
        }
        
        .medicines-table th .sub-header {
            font-weight: normal;
            font-size: 9px;
            font-style: normal;
            display: block;
            margin-top: 4px;
        }
        
        .medicines-table .col-counter {
            width: 5%;
            text-align: center;
            font-weight: bold;
        }
        
        .medicines-table .col-medicine {
            width: 31.67%;
        }
        
        .medicines-table .col-dosage {
            width: 31.67%;
        }
        
        .medicines-table .col-quantity {
            width: 31.67%;
        }
        
        .medicine-name {
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .medicine-route {
            font-size: 9px;
            color: #000;
        }
        
        .dosage-value {
            margin-bottom: 4px;
        }
        
        .dosage-frequency {
            font-size: 9px;
            color: #000;
        }
        
        .quantity-value {
            margin-bottom: 4px;
        }
        
        .quantity-time {
            font-size: 9px;
            color: #000;
        }
        
        /* Notes Section */
        .notes-table {
            margin-top: 60px;
        }
        
        .notes-table td {
            border: none;
            line-height: 1.6;
            padding: 8px 12px;
        }
        
        /* Footer Section */
        .footer-table {
            margin-top: 15px;
            font-size: 10px;
        }
        
        .footer-table td {
            border: none;
            vertical-align: top;
            line-height: 1.6;
            padding: 6px 8px;
        }
        
        .footer-left {
            width: 60%;
        }
        
        .footer-right {
            width: 40%;
            text-align: right;
        }
        
        .footer-warning {
            font-weight: bold;
        }
        
        .vet-name {
            font-weight: bold;
        }
        
        .underline-space {
            display: inline-block;
            border-bottom: 1px solid #1a4a75;
            min-width: 100px;
        }
    </style>
</head>
<body>
    <div class="debug-banner">
        🔍 DEBUG MODE - HTML Preview (A4 Size: 210mm × 297mm)
    </div>
    
    <div class="debug-container" style="margin-top: 50px;">
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            Prescription ID: {{ $prescription->id }}<br>
            Appointment ID: {{ $prescription->appointment_id }}<br>
            Container Width: 210mm (A4 width)<br>
            Container Padding: 12.7mm (0.5 inch margins)
        </div>
        
        <!-- Header Section -->
        <table class="header-table noborder-table">
            <tr>
                <td class="header-left">
                    <img src="{{ $base64PanaboLogo }}" alt="Panabo Logo" class="header-logo">
                </td>
                <td class="header-center">
                    <p class="header-org-name">City of Panabo</p>
                    <p class="header-org-name">City Mayor's Office</p>
                </td>
                <td class="header-right">
                    <img src="{{ $base64Logo }}" alt="Logo" class="header-logo">
                </td>
            </tr>
            <tr>
                <td class="header-center address-line">
                    <p class="header-address">Prk. 1 Along, National Highway, Brgy. Salvacion Panabo city</p>
                </td>
            </tr>
        </table>
        
        <!-- Prescription Logo -->
        <div style="text-align: left; margin-bottom: 10px;">
            <img src="{{ $base64PrescriptionLogo }}" alt="Prescription Logo" class="prescription-logo">
        </div>
        
        <!-- Patient Information Section -->
        <table class="patient-info-table">
            <tr>
                <td>
                    <span class="patient-info-label">Clients Name:</span>
                    {{ $prescription->appointment->user ? (trim(($prescription->appointment->user->first_name ?? '') . ' ' . ($prescription->appointment->user->last_name ?? '')) ?: $prescription->appointment->user->name) : 'N/A' }}
                </td>
                <td>
                    <span class="patient-info-label">Date:</span>
                    {{ $prescription->created_at->format('F d, Y') }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="patient-info-label">Patients Name:</span>
                    {{ $prescription->patient->pet_name ?? 'N/A' }}
                </td>
                <td>
                    <span class="patient-info-label">Pet Type:</span>
                    {{ $prescription->patient->petType->name ?? 'N/A' }} - {{ $prescription->patient->pet_breed ?? 'N/A' }}
                </td>
            </tr>
        </table>
        
        <!-- Medicines Section -->
        <table class="medicines-table">
            <tbody>
                @forelse($prescription->medicines as $index => $prescriptionMedicine)
                    <tr>
                        <td>
                            <div class="medicine-name">{{ $loop->iteration }}.) {{ $prescriptionMedicine->medicine->name }}</div>
                            <div class="medicine-route">{{ $prescriptionMedicine->instructions }}</div>
                        </td>
                        <td>
                            <div class="dosage-value">{{ $prescriptionMedicine->dosage }}</div>
                            <div class="dosage-frequency">
                                @php
                                    // Try to extract frequency from instructions if it contains frequency info
                                    $instructions = strtolower($prescriptionMedicine->instructions ?? '');
                                    $frequency = '';
                                    if (strpos($instructions, 'once') !== false || strpos($instructions, 'daily') !== false) {
                                        $frequency = 'Once a day';
                                    } elseif (strpos($instructions, 'twice') !== false) {
                                        $frequency = 'Twice a day';
                                    } elseif (strpos($instructions, 'three') !== false) {
                                        $frequency = 'Three times a day';
                                    } elseif (strpos($instructions, 'every') !== false) {
                                        // Extract "every X hours" pattern
                                        if (preg_match('/every\s+(\d+)\s+hours?/i', $instructions, $matches)) {
                                            $frequency = 'Every ' . $matches[1] . ' hours';
                                        } else {
                                            $frequency = 'As directed';
                                        }
                                    } else {
                                        $frequency = 'As directed';
                                    }
                                @endphp
                                {{ $frequency }}
                            </div>
                        </td>
                        <td>
                            <div class="quantity-value">{{ $prescriptionMedicine->quantity }}</div>
                            <div class="quantity-time">
                                @php
                                    // Try to extract time from instructions
                                    $instructions = $prescriptionMedicine->instructions ?? '';
                                    $time = '';
                                    if (preg_match('/(\d{1,2}(?::\d{2})?\s*(?:am|pm|AM|PM))/i', $instructions, $matches)) {
                                        $time = $matches[1];
                                    } elseif (preg_match('/(\d{1,2}:\d{2})/i', $instructions, $matches)) {
                                        $time = $matches[1];
                                    } else {
                                        $time = 'As directed';
                                    }
                                @endphp
                                {{ $time }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No medicines prescribed</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Notes Section -->
        @if($prescription->notes)
        <table class="notes-table">
            <tr>
                <td><strong>Reminder: </strong>{{ $prescription->notes }}</td>
            </tr>
        </table>
        @endif
        
        <!-- Footer Section -->
        <table class="footer-table noborder-table">
            <tr>
                <td class="footer-left">
                    <span class="footer-warning">DO NOT SELF-MEDICATE!</span><br>
                    This prescription is only intended for the patient specified above.<br>
                    Always consult a licensed veterinarian.<br>
                    Keep medicines out of reach of children.
                </td>
                <td class="footer-right">
                    @if($veterinarianName)
                        <span class="vet-name">{{ strtoupper($veterinarianName) }}</span><br>
                        Veterinarian III<br>
                        @if($veterinarianLicense)
                            PRC Lic. No: {{ $veterinarianLicense }}<br>
                        @else
                            PRC Lic. No: <span class="underline-space"></span><br>
                        @endif
                    @else
                        <span class="underline-space"></span><br>
                        Veterinarian<br>
                        PRC Lic. No: <span class="underline-space"></span><br>
                        PTR No: <span class="underline-space"></span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

