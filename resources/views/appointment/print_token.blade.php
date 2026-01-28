<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token #{{ $appointment->token_number }} - {{ $business->name ?? 'ADC Portal' }}</title>
    <style>
        /* Thermal Printer Styles - 80mm width */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            width: 80mm;
            padding: 5mm;
            background: #fff;
            color: #000;
        }
        
        .receipt {
            width: 100%;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .header p {
            font-size: 10px;
        }
        
        .token-section {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px dashed #000;
            margin-bottom: 8px;
        }
        
        .token-label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .token-number {
            font-size: 48px;
            font-weight: bold;
            line-height: 1;
        }
        
        .details {
            padding: 8px 0;
            border-bottom: 1px dashed #000;
            margin-bottom: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 11px;
        }
        
        .detail-row .label {
            font-weight: bold;
            width: 40%;
        }
        
        .detail-row .value {
            width: 58%;
            text-align: right;
        }
        
        .appointment-id {
            text-align: center;
            font-size: 10px;
            padding: 5px 0;
            border-bottom: 1px dashed #000;
            margin-bottom: 8px;
        }
        
        .footer {
            text-align: center;
            font-size: 10px;
            padding-top: 8px;
        }
        
        .footer p {
            margin-bottom: 3px;
        }
        
        .timestamp {
            font-size: 9px;
            text-align: center;
            margin-top: 10px;
            color: #666;
        }
        
        /* Hide everything except receipt when printing */
        @media print {
            body {
                width: 80mm;
                padding: 2mm;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Screen preview styles */
        @media screen {
            body {
                margin: 20px auto;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <h1>{{ $business->name ?? 'Amad Diagnostic Centre' }}</h1>
            @if($appointment->LocationData)
                <p>{{ $appointment->LocationData->name ?? '' }}</p>
            @endif
        </div>
        
        <!-- Token Number -->
        <div class="token-section">
            <div class="token-label">TOKEN NUMBER</div>
            <div class="token-number">{{ str_pad($appointment->token_number, 3, '0', STR_PAD_LEFT) }}</div>
        </div>
        
        <!-- Appointment Details -->
        <div class="details">
            <div class="detail-row">
                <span class="label">Patient:</span>
                <span class="value">{{ $appointment->name ?? ($appointment->CustomerData->name ?? 'Walk-in') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Service:</span>
                <span class="value">{{ $appointment->ServiceData->name ?? 'N/A' }}</span>
            </div>
            @if($appointment->StaffData)
            <div class="detail-row">
                <span class="label">Doctor/Staff:</span>
                <span class="value">{{ $appointment->StaffData->name ?? 'N/A' }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span class="label">Date:</span>
                <span class="value">{{ \Carbon\Carbon::parse($appointment->date)->format('d-M-Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Time:</span>
                <span class="value">{{ $appointment->time ?? 'N/A' }}</span>
            </div>
            @if($appointment->referred_by || $appointment->ReferrerData)
            <div class="detail-row">
                <span class="label">Referred By:</span>
                <span class="value">{{ $appointment->referred_by ?? $appointment->ReferrerData->name }}</span>
            </div>
            @endif
        </div>
        
        <!-- Appointment ID -->
        <div class="appointment-id">
            Appointment #: {{ $appointment->id }}
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Please wait for your token to be called</p>
            <p>Thank you for choosing us!</p>
        </div>
        
        <!-- Timestamp -->
        <div class="timestamp">
            Printed: {{ now()->format('d-M-Y h:i A') }}
        </div>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
            // Close window after print dialog (works in most browsers)
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>
