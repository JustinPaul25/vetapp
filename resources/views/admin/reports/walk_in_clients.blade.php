<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .filter-info {
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
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
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="filter-info">
            Filter: {{ $filterInfo }} | Total Records: {{ $total }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Mobile Number</th>
                <th>Address</th>
                <th>Patients Count</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @forelse($walkInClients as $client)
                <tr>
                    <td>{{ $client['name'] }}</td>
                    <td>{{ $client['email'] }}</td>
                    <td>{{ $client['mobile_number'] }}</td>
                    <td>{{ $client['address'] }}</td>
                    <td>{{ $client['patients_count'] }}</td>
                    <td>{{ $client['created_at'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>










