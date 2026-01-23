<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        
        /* Header Section */
        .header-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 5px;
        }
        
        .header-left {
            width: 15%;
            text-align: left;
        }
        
        .header-center {
            width: 70%;
            text-align: center;
            line-height: 1.2;
        }
        
        .header-right {
            width: 15%;
            text-align: right;
        }
        
        .header-logo {
            width: 50px;
            height: auto;
        }
        
        .header-org-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .header-address {
            font-size: 11px;
        }
        
        .report-title {
            text-align: center;
            margin: 15px 0;
            font-size: 20px;
            font-weight: bold;
        }
        
        .report-info {
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
            text-align: center;
        }
        
        .report-date {
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
            text-align: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-size: 11px;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 12px;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <table class="header-table">
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
            <td colspan="3" class="header-center" style="text-align: center; padding-top: 5px;">
                <p class="header-address">Prk. 1 Along, National Highway, Brgy. Salvacion Panabo city</p>
            </td>
        </tr>
    </table>
    
    <div class="report-title">{{ $title }}</div>
    
    <div class="report-date">Date: {{ $reportDate }}</div>
    
    <div class="report-info">
        Filter: {{ $filterInfo }} | Total Records: {{ $total }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Appointment Type</th>
                <th>Pet Type</th>
                <th>Breed</th>
                <th>Owner Name</th>
                <th>Owner Email</th>
                <th>Symptoms</th>
                <th>Issued On</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prescriptions as $prescription)
                <tr>
                    <td>{{ $prescription['appointment_type'] }}</td>
                    <td>{{ $prescription['pet_type'] }}</td>
                    <td>{{ $prescription['pet_breed'] }}</td>
                    <td>{{ $prescription['owner_name'] }}</td>
                    <td>{{ $prescription['owner_email'] }}</td>
                    <td>{{ $prescription['symptoms'] ?? 'N/A' }}</td>
                    <td>{{ $prescription['issued_on'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>
