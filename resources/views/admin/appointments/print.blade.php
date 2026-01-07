<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Prescription - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: A5 landscape;
            margin: 0.3in 0.25in;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
            padding: 0.3in 0.25in;
            width: 8.27in;
            max-width: 8.27in;
            margin: 0 auto;
        }
        
        /* Print styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
                width: 8.27in;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A5 landscape;
                margin: 0.3in 0.25in;
            }
        }
        
        /* Screen styles - show print button when not printing */
        @media screen {
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 24px;
                background-color: #1a4a75;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            
            .print-button:hover {
                background-color: #0f3557;
            }
        }
        
        @media print {
            .print-button {
                display: none;
            }
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
            width: 100%;
        }
        
        .header-table td {
            vertical-align: top;
            border: none;
            padding: 8px 6px;
        }
        
        .header-left {
            width: 15%;
            text-align: left;
        }
        
        .header-center {
            width: 70%;
            text-align: center;
            line-height: 1.2;
            padding: 0 10px;
        }
        
        .header-right {
            width: 15%;
            text-align: right;
        }
        
        .header-logo {
            width: 65px;
            height: auto;
            max-width: 100%;
        }
        
        .header-org-name {
            font-size: 20px;
            font-weight: bold;
            margin: 0;
        }

        .header-address {
            font-size: 14px;
        }
        
        .header-section-name {
            font-size: 12px;
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
            margin-top: 10px;
            width: 100%;
        }
        
        .patient-info-table td {
            border: none;
            padding: 4px 5px;
        }
        
        .patient-info-label {
            font-weight: bold;
        }
        
        .section-header {
            font-weight: bold;
            margin-top: 12px;
            border-bottom: 1px solid #1a4a75;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        
        /* Medicines Section */
        .medicines-table {
            margin-top: 8px;
            width: 100%;
        }
        
        .medicines-table th,
        .medicines-table td {
            border: none;
            text-align: left;
            vertical-align: top;
            padding: 5px 4px;
            word-wrap: break-word;
        }

        .medicines-table tbody tr + tr td {
            padding-top: 5px;
        }
        
        .medicines-table th {
            background-color: #EEE;
            font-weight: bold;
            text-align: left;
            vertical-align: top;
        }
        
        .medicines-table th .sub-header {
            font-weight: normal;
            font-size: 10px;
            font-style: normal;
            display: block;
            margin-top: 3px;
        }
        
        .medicines-table .col-medicine {
            width: 33.33%;
        }
        
        .medicines-table .col-dosage {
            width: 33.33%;
        }
        
        .medicines-table .col-quantity {
            width: 33.33%;
        }
        
        .medicine-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .medicine-route {
            font-size: 10px;
            color: #000;
        }
        
        .dosage-value {
            margin-bottom: 3px;
        }
        
        .dosage-frequency {
            font-size: 10px;
            color: #000;
        }
        
        .quantity-value {
            margin-bottom: 3px;
        }
        
        .quantity-time {
            font-size: 10px;
            color: #000;
        }
        
        /* Notes Section */
        .notes-table {
            margin-top: 40px;
            width: 100%;
        }
        
        .notes-table td {
            border: none;
            line-height: 1.4;
            padding: 8px 6px;
        }
        
        /* Footer Section */
        .footer-table {
            margin-top: 10px;
            font-size: 11px;
            width: 100%;
        }
        
        .footer-table td {
            border: none;
            vertical-align: top;
            line-height: 1.4;
            padding: 6px 4px;
        }
        
        .footer-left {
            width: 60%;
        }
        
        .footer-right {
            width: 40%;
            text-align: right;
            padding-right: 0;
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
    <button class="print-button" onclick="window.print()">Print</button>
    
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
            <td colspan="3" class="header-center address-line" style="text-align: center; padding-top: 5px;">
                <p class="header-address">Prk. 1 Along, National Highway, Brgy. Salvacion Panabo city</p>
            </td>
        </tr>
    </table>
    
    <!-- Prescription Logo -->
    <div style="text-align: left; margin-bottom: 10px;">
        <img src="{{ $base64PrescriptionLogo }}" alt="Prescription Logo" class="prescription-logo">
    </div>
    
    <!-- Patient Information Section -->
    <table class="patient-info-table noborder-table">
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
    <table class="medicines-table noborder-table">
        <thead>
            <tr>
                <th class="col-medicine">Medicine</th>
                <th class="col-dosage">Dosage</th>
                <th class="col-quantity">Quantity</th>
            </tr>
        </thead>
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
                    <td colspan="3" style="text-align: center;">No medicines prescribed</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <!-- Notes Section -->
    @if($prescription->notes)
    <table class="notes-table noborder-table">
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
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 250);
        // };
    </script>
</body>
</html>
