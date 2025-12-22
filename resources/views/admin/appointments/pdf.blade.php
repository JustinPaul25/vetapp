<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Prescription</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            margin: 0.5in;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1a4a75;
            line-height: 1.4;
            padding: 10px;
        }
        
        /* Print color adjustment */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
            margin-bottom: 10px;
        }
        
        .header-table td {
            vertical-align: top;
            border: none;
            padding: 5px;
        }
        
        .header-left {
            width: 20%;
            text-align: left;
        }
        
        .header-center {
            width: 60%;
            text-align: center;
            line-height: 1.2;
        }
        
        .header-right {
            width: 20%;
            text-align: right;
        }
        
        .header-logo {
            width: 70px;
            height: auto;
        }
        
        .header-org-name {
            font-size: 14px;
            margin: 0;
        }
        
        .header-section-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }
        
        .header-separator {
            border: none;
            border-top: 1px solid #1a4a75;
            margin: 12px 0 15px 0;
        }
        
        /* Patient Information Section */
        .patient-info-table {
            margin-top: 15px;
        }
        
        .patient-info-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
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
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .medicines-table th {
            background-color: #EEE;
            font-weight: bold;
        }
        
        .medicines-table .col-product {
            width: 100px;
        }
        
        .medicines-table .col-instructions {
            width: 150px;
        }
        
        /* Notes Section */
        .notes-table {
            margin-top: 12px;
        }
        
        .notes-table td {
            padding: 10px;
            border: 1px solid #ddd;
            line-height: 1.6;
        }
        
        /* Footer Section */
        .footer-table {
            margin-top: 35px;
            font-size: 10px;
        }
        
        .footer-table td {
            border: none;
            vertical-align: top;
            padding: 8px 5px;
            line-height: 1.6;
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
    <!-- Header Section -->
    <table class="header-table noborder-table">
        <tr>
            <td class="header-left">
                <img src="{{ $base64PanaboLogo }}" alt="Panabo Logo" class="header-logo">
            </td>
            <td class="header-center">
                <p class="header-org-name">City of Panabo</p>
                <p class="header-org-name">City Mayor's Office</p>
                <p class="header-section-name">CITY VETERINARY SECTION</p>
                <p>Prk. 1 Along, National Highway, Brgy. Salvacion Panabo city</p>
            </td>
            <td class="header-right">
                <img src="{{ $base64Logo }}" alt="Logo" class="header-logo">
            </td>
        </tr>
    </table>
    
    <hr class="header-separator">
    
    <!-- Patient Information Section -->
    <table class="patient-info-table">
        <tr>
            <td>
                <span class="patient-info-label">Prescription No.:</span>
                {{ str_pad($prescription->id, 6, '0', STR_PAD_LEFT) }}
            </td>
            <td>
                <span class="patient-info-label">Prescription Date:</span>
                {{ $prescription->created_at->format('F d, Y') }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="patient-info-label">Pet Owner:</span>
                {{ $prescription->appointment->user ? trim(($prescription->appointment->user->first_name ?? '') . ' ' . ($prescription->appointment->user->last_name ?? '')) : 'N/A' }}
            </td>
            <td>
                <span class="patient-info-label">Contact:</span>
                {{ $prescription->appointment->user->mobile_number ?? 'N/A' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="patient-info-label">Pet Name:</span>
                {{ $prescription->patient->pet_name ?? 'N/A' }}
            </td>
            <td>
                <span class="patient-info-label">Breed:</span>
                {{ $prescription->patient->petType->name ?? 'N/A' }} - {{ $prescription->patient->pet_breed ?? 'N/A' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="patient-info-label">Pet Current Weight:</span>
                {{ $prescription->pet_weight }}
            </td>
            <td>
                <span class="patient-info-label">Pet Birthdate:</span>
                {{ $prescription->patient->pet_birth_date ? $prescription->patient->pet_birth_date->format('F d, Y') : 'N/A' }}
            </td>
        </tr>
    </table>
    
    <!-- Medicines Section -->
    <div class="section-header">Medicines</div>
    <table class="medicines-table">
        <thead>
            <tr>
                <th class="col-product">Product</th>
                <th>Dosage</th>
                <th class="col-instructions">Instructions</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prescription->medicines as $prescriptionMedicine)
                <tr>
                    <td>{{ $prescriptionMedicine->medicine->name }}</td>
                    <td>{{ $prescriptionMedicine->dosage }}</td>
                    <td>{{ $prescriptionMedicine->instructions }}</td>
                    <td>{{ $prescriptionMedicine->quantity }}</td>
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
    <div class="section-header">Notes</div>
    <table class="notes-table">
        <tr>
            <td>{{ $prescription->notes }}</td>
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
</body>
</html>
