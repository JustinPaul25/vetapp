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
        body {
            font-family: Arial, sans-serif;
            font-size: 16px;
            margin: 20px;
            padding-right: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .filter-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
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
            font-size: 15px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 16px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="filter-info">
            Filter: {{ $filterInfo }} | Total Records: {{ $total }}
        </div>
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







