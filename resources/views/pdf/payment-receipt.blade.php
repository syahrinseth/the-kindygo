<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        @page {
            padding: 0;
            margin: 50px 0px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
            font-size: 14px;
            color: #636363;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        #wrapper {
            margin: 0;
            width: 100%;
            max-width: 21cm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2563eb;
            font-size: 20px;
            margin: 0;
            font-weight: 300;
        }

        .receipt-info {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        fieldset {
            margin-bottom: 20px;
            border: 2px solid #e5e5e5;
            padding: 0;
        }

        legend {
            padding: 0 8px;
            font-weight: bold;
            color: #333;
        }

        .address {
            padding: 12px;
            color: #636363;
            line-height: 1.15em;
        }

        .address h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .payment-table th,
        .payment-table td {
            border: 1px solid #e5e5e5;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }

        .payment-table th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #333;
        }

        .payment-table .text-right {
            text-align: right;
        }

        .payment-table .text-center {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .gateway-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .gateway-chip {
            background-color: #d1fae5;
            color: #065f46;
        }

        .gateway-bank_transfer {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .gateway-cash {
            background-color: #f3f4f6;
            color: #374151;
        }

        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #059669;
        }

        .footer {
            border-top: 1px solid #e5e5e5;
            padding-top: 20px;
            text-align: center;
            color: #858585;
            font-size: 12px;
            margin-top: 40px;
        }

        .invoice-section {
            margin-bottom: 25px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            overflow: hidden;
        }

        .invoice-header {
            background-color: #f8fafc;
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
        }

        .invoice-items {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-items th,
        .invoice-items td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
            text-align: left;
        }

        .invoice-items th {
            background-color: #f9fafb;
            font-size: 11px;
            color: #4b5563;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
            <tbody>
                <tr>
                    <td align="center" valign="top">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tbody>
                                <tr>
                                    <td align="center" valign="top">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tbody>
                                                <tr>
                                                    <td valign="top" style="background-color: #ffffff;">
                                                        <table border="0" cellpadding="20" cellspacing="0" width="100%">
                                                            <tbody>
                                                                <tr>
                                                                    <td valign="top" style="padding: 0px 48px 0;">
                                                                        <div>
                                                                            <!-- Header -->
                                                                            <div style="width:100%; text-align:left; margin-bottom: 30px;">
                                                                                <table style="width:100%;">
                                                                                    <tbody>
                                                                                        <tr>
                                                                                            <td>
                                                                                                @if($centre && $centre->logo)
                                                                                                    <img src="{{ $centre->logo }}" alt="{{ $centre->name }}" style="width:150px;">
                                                                                                @else
                                                                                                    <div style="width: 150px; height: 60px; background-color: #f3f4f6; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                                                                        <span style="color: #6b7280; font-weight: bold;">{{ $centre->name ?? 'KindyGo' }}</span>
                                                                                                    </div>
                                                                                                @endif
                                                                                            </td>
                                                                                            <td style="text-align:right;">
                                                                                                <h1 style="font-size: 20px; font-weight: 300; line-height: 1.2em; margin: 0; color: #2563eb;">
                                                                                                    {{ $centre->name ?? 'KindyGo' }}
                                                                                                </h1>
                                                                                                <div style="margin-top: 10px;">
                                                                                                    <strong>Receipt #: {{ $payment->reference_no }}</strong>
                                                                                                </div>
                                                                                                <div style="margin-top: 5px; font-size: 12px; color: #6b7280;">
                                                                                                    Generated: {{ $generatedAt->format('M d, Y H:i') }}
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>

                                                                            <!-- Centre Information -->
                                                                            @if($centre)
                                                                            <fieldset style="margin-bottom: 20px; border: 2px solid #e5e5e5;">
                                                                                <legend style="padding: 0 8px; font-weight: bold;">Centre Information</legend>
                                                                                <address class="address" style="padding: 12px; color: #636363; line-height: 1.15em;">
                                                                                    <h4 style="margin: 0 0 10px 0;">{{ $centre->name }}</h4>
                                                                                    @if($centre->address)
                                                                                        {{ $centre->address }}<br>
                                                                                    @endif
                                                                                    @if($centre->phone)
                                                                                        Tel: {{ $centre->phone }}<br>
                                                                                    @endif
                                                                                    @if($centre->email)
                                                                                        Email: {{ $centre->email }}
                                                                                    @endif
                                                                                </address>
                                                                            </fieldset>
                                                                            @endif

                                                                            <!-- Customer Information -->
                                                                            <fieldset style="margin-bottom: 20px; border: 2px solid #e5e5e5;">
                                                                                <legend style="padding: 0 8px; font-weight: bold;">Customer Information</legend>
                                                                                <address class="address" style="padding: 12px; color: #636363; line-height: 1.15em;">
                                                                                    <h4 style="margin: 0 0 10px 0;">{{ $user->name }}</h4>
                                                                                    @if($user->email)
                                                                                        Email: {{ $user->email }}<br>
                                                                                    @endif
                                                                                    @if($user->profile?->phone)
                                                                                        Phone: {{ $user->profile->phone }}
                                                                                    @endif
                                                                                </address>
                                                                            </fieldset>

                                                                            <!-- Payment Summary -->
                                                                            <div style="background-color: #f0f9ff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #2563eb;">
                                                                                <h3 style="margin: 0 0 15px 0; color: #2563eb;">Payment Summary</h3>
                                                                                <table style="width: 100%;">
                                                                                    <tr>
                                                                                        <td><strong>Payment Status:</strong></td>
                                                                                        <td style="text-align: right;">
                                                                                            <span class="status-badge status-{{ strtolower($payment->status->value) }}">
                                                                                                {{ ucfirst($payment->status->value) }}
                                                                                            </span>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td><strong>Payment Method:</strong></td>
                                                                                        <td style="text-align: right;">
                                                                                            @if($payment->gateway->value === 'chip')
                                                                                                @php
                                                                                                    $chipPaymentMethod = $payment->getChipPaymentMethod();
                                                                                                    $chipStatus = $payment->getChipStatus();
                                                                                                @endphp
                                                                                                <span class="gateway-badge gateway-{{ strtolower($payment->gateway->value) }}">
                                                                                                    CHIP
                                                                                                    @if($chipPaymentMethod)
                                                                                                        ({{ ucfirst($chipPaymentMethod) }})
                                                                                                    @endif
                                                                                                </span>
                                                                                                @if($chipStatus && $chipStatus !== $payment->status->value)
                                                                                                    <br><small style="color: #6b7280; font-size: 10px;">CHIP Status: {{ ucfirst($chipStatus) }}</small>
                                                                                                @endif
                                                                                            @else
                                                                                                <span class="gateway-badge gateway-{{ strtolower($payment->gateway->value) }}">
                                                                                                    {{ ucfirst(str_replace('_', ' ', $payment->gateway->value)) }}
                                                                                                </span>
                                                                                            @endif
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td><strong>Payment Date:</strong></td>
                                                                                        <td style="text-align: right;">{{ $payment->paid_at ? $payment->paid_at->format('D, d M Y \a\t H:i') : 'N/A' }}</td>
                                                                                    </tr>
                                                                                    @if($payment->gateway_payment_id)
                                                                                    <tr>
                                                                                        <td><strong>Transaction ID:</strong></td>
                                                                                        <td style="text-align: right; font-family: monospace;">{{ $payment->gateway_payment_id }}</td>
                                                                                    </tr>
                                                                                    @endif
                                                                                    
                                                                                    @if($payment->gateway->value === 'chip' && $payment->gateway_payment_data)
                                                                                        @php
                                                                                            $chipTransactionId = $payment->getChipTransactionId();
                                                                                            $chipBankName = $payment->getChipBankName();
                                                                                            $chipReference = $payment->getChipReference();
                                                                                            $chipClientEmail = $payment->getChipClientEmail();
                                                                                        @endphp
                                                                                        
                                                                                        @if($chipTransactionId && $chipTransactionId !== $payment->gateway_payment_id)
                                                                                        <tr>
                                                                                            <td><strong>CHIP Transaction ID:</strong></td>
                                                                                            <td style="text-align: right; font-family: monospace; font-size: 12px;">{{ $chipTransactionId }}</td>
                                                                                        </tr>
                                                                                        @endif
                                                                                        
                                                                                        @if($chipBankName)
                                                                                        <tr>
                                                                                            <td><strong>Bank:</strong></td>
                                                                                            <td style="text-align: right;">{{ $chipBankName }}</td>
                                                                                        </tr>
                                                                                        @endif
                                                                                        
                                                                                        @if($chipReference)
                                                                                        <tr>
                                                                                            <td><strong>Reference:</strong></td>
                                                                                            <td style="text-align: right; font-family: monospace; font-size: 12px;">{{ $chipReference }}</td>
                                                                                        </tr>
                                                                                        @endif
                                                                                        
                                                                                        @if($chipClientEmail && $chipClientEmail !== $user->email)
                                                                                        <tr>
                                                                                            <td><strong>CHIP Email:</strong></td>
                                                                                            <td style="text-align: right; font-size: 12px;">{{ $chipClientEmail }}</td>
                                                                                        </tr>
                                                                                        @endif
                                                                                    @endif
                                                                                    <tr style="border-top: 2px solid #2563eb;">
                                                                                        <td><strong style="font-size: 16px;">Total Amount:</strong></td>
                                                                                        <td style="text-align: right;">
                                                                                            <span class="amount">{{ $payment->getFormattedAmount() }}</span>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>

                                                                            <!-- Associated Invoices -->
                                                                            @if($invoices->count() > 0)
                                                                                <h3 style="color: #2563eb; font-size: 16px; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px;">
                                                                                    Associated Invoices
                                                                                </h3>
                                                                                
                                                                                @foreach($invoices as $invoice)
                                                                                    <div class="invoice-section" style="margin-bottom: 25px; border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;">
                                                                                        <div class="invoice-header" style="background-color: #f8fafc; padding: 15px; border-bottom: 1px solid #e5e5e5;">
                                                                                            <table style="width: 100%;">
                                                                                                <tr>
                                                                                                    <td>
                                                                                                        <strong>Invoice #{{ $invoice->number }}</strong><br>
                                                                                                        <small style="color: #6b7280;">{{ $invoice->created_at->format('M d, Y') }}</small>
                                                                                                    </td>
                                                                                                    <td style="text-align: right;">
                                                                                                        <strong>Amount Paid: {{ 'RM' . number_format($invoice->pivot->amount / 100, 2) }}</strong>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </div>
                                                                                        
                                                                                        @if($invoice->invoiceItems->count() > 0)
                                                                                            <table class="invoice-items" style="width: 100%; border-collapse: collapse;">
                                                                                                <thead>
                                                                                                    <tr style="background-color: #f9fafb;">
                                                                                                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">Item</th>
                                                                                                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">Child</th>
                                                                                                        <th style="padding: 10px; text-align: center; font-size: 11px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">Qty</th>
                                                                                                        <th style="padding: 10px; text-align: right; font-size: 11px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">Unit Price</th>
                                                                                                        <th style="padding: 10px; text-align: right; font-size: 11px; color: #4b5563; border-bottom: 1px solid #e5e7eb;">Total</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    @foreach($invoice->invoiceItems as $item)
                                                                                                        <tr>
                                                                                                            <td style="padding: 10px; border-bottom: 1px solid #f3f4f6;">{{ $item->name }}</td>
                                                                                                            <td style="padding: 10px; border-bottom: 1px solid #f3f4f6;">{{ $item->child->full_name ?? 'N/A' }}</td>
                                                                                                            <td style="padding: 10px; text-align: center; border-bottom: 1px solid #f3f4f6;">{{ $item->quantity }}</td>
                                                                                                            <td style="padding: 10px; text-align: right; border-bottom: 1px solid #f3f4f6;">{{ 'RM' . number_format($item->price / 100, 2) }}</td>
                                                                                                            <td style="padding: 10px; text-align: right; border-bottom: 1px solid #f3f4f6;">{{ 'RM' . number_format($item->total / 100, 2) }}</td>
                                                                                                        </tr>
                                                                                                    @endforeach
                                                                                                </tbody>
                                                                                            </table>
                                                                                        @endif
                                                                                    </div>
                                                                                @endforeach
                                                                            @else
                                                                                <div style="text-align: center; color: #6b7280; padding: 40px;">
                                                                                    <p>No associated invoices found for this payment.</p>
                                                                                </div>
                                                                            @endif

                                                                            @if($payment->description)
                                                                                <div style="margin-top: 30px; padding: 15px; background-color: #f9fafb; border-radius: 8px;">
                                                                                    <h4 style="margin: 0 0 10px 0; color: #374151;">Notes:</h4>
                                                                                    <p style="margin: 0; color: #6b7280;">{{ $payment->description }}</p>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" valign="top">
                        <!-- Footer -->
                        <table border="0" cellpadding="10" cellspacing="0" width="100%">
                            <tbody>
                                <tr>
                                    <td valign="top" style="padding: 0;">
                                        <table border="0" cellpadding="10" cellspacing="0" width="100%">
                                            <tbody>
                                                <tr>
                                                    <td colspan="2" valign="middle" class="footer">
                                                        <p style="margin: 0;">{{ $centre->name ?? 'KindyGo' }}</p>
                                                        @if($centre && $centre->address)
                                                            <p style="margin: 5px 0 0 0; font-size: 11px;">{{ $centre->address }}</p>
                                                        @endif
                                                        <p style="margin: 10px 0 0 0; font-size: 11px;">
                                                            This is a computer-generated receipt. No signature is required.
                                                        </p>
                                                        <p style="margin: 5px 0 0 0; font-size: 10px; color: #9ca3af;">
                                                            Generated on {{ $generatedAt->format('M d, Y H:i:s') }}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
