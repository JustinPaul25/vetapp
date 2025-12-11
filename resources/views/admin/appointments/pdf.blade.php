<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription #{{ str_pad($prescription->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
        }
        
        .prescription-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .prescription-header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .prescription-number {
            font-size: 12px;
            margin-bottom: 10px;
        }
        
        .info-section {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        
        .info-value {
            flex: 1;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        
        .symptoms {
            margin-bottom: 15px;
        }
        
        .diagnoses {
            margin-bottom: 15px;
        }
        
        .diagnosis-item {
            margin-left: 20px;
            margin-bottom: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        
        table td {
            border: 1px solid #000;
            padding: 8px;
        }
        
        .notes {
            margin-top: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        .footer {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding-top: 15px;
        }
        
        .warning {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            padding: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 40px;
            text-align: right;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-left: auto;
            margin-top: 50px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ $base64Logo }}" alt="Clinic Logo" class="logo">
        <div>
            <img src="{{ $base64PanaboLogo }}" alt="Panabo Logo" style="max-width: 100px; height: auto;">
        </div>
    </div>
    
    <div class="prescription-header">
        <img src="{{ $base64PrescriptionLogo }}" alt="Prescription" style="max-width: 80px; height: auto; margin-bottom: 10px;">
        <h1>PRESCRIPTION</h1>
        <div class="prescription-number">
            Prescription No: {{ str_pad($prescription->id, 6, '0', STR_PAD_LEFT) }}
        </div>
        <div>Date: {{ $prescription->created_at->format('F d, Y') }}</div>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Pet Name:</span>
            <span class="info-value">{{ $prescription->patient->pet_name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Species:</span>
            <span class="info-value">{{ $prescription->patient->petType->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Breed:</span>
            <span class="info-value">{{ $prescription->patient->pet_breed ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Weight:</span>
            <span class="info-value">{{ $prescription->pet_weight }} Kg</span>
        </div>
        <div class="info-row">
            <span class="info-label">Birth Date:</span>
            <span class="info-value">{{ $prescription->patient->pet_birth_date ? $prescription->patient->pet_birth_date->format('F d, Y') : 'N/A' }}</span>
        </div>
    </div>
    
    <div class="section-title">SYMPTOMS</div>
    <div class="symptoms">
        {{ $prescription->symptoms }}
    </div>
    
    <div class="section-title">TENTATIVE DIAGNOSIS</div>
    <div class="diagnoses">
        @forelse($prescription->diagnoses as $diagnosis)
            <div class="diagnosis-item">{{ $loop->iteration }}. {{ $diagnosis->disease->name }}</div>
        @empty
            <div class="diagnosis-item">No diagnosis recorded</div>
        @endforelse
    </div>
    
    <div class="section-title">MEDICINES</div>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Dosage</th>
                <th>Instructions</th>
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
    
    @if($prescription->notes)
    <div class="section-title">NOTES</div>
    <div class="notes">
        {{ $prescription->notes }}
    </div>
    @endif
    
    <div class="footer">
        <div class="warning">
            ⚠️ DO NOT SELF-MEDICATE. CONSULT YOUR VETERINARIAN.
        </div>
        
        <div class="signature-section">
            <div class="signature-line">
                <div style="text-align: center;">
                    <strong>Veterinarian Signature</strong><br>
                    License No: _________________
                </div>
            </div>
        </div>
    </div>
</body>
</html>
