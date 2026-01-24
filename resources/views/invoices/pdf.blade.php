<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
            padding: 40px;
        }
        .header {
            margin-bottom: 40px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #111;
            margin-bottom: 5px;
        }
        .company-details {
            color: #666;
            font-size: 10px;
        }
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            color: #111;
            margin-bottom: 5px;
        }
        .invoice-meta {
            color: #666;
            font-size: 10px;
        }
        .row {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .col {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .section-title {
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            font-size: 9px;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .customer-name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 3px;
        }
        .customer-details {
            color: #666;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        th {
            background-color: #f8f8f8;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 250px;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 5px 0;
        }
        .totals-label {
            display: table-cell;
            text-align: left;
            color: #666;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
        }
        .totals-row.total {
            border-top: 2px solid #333;
            margin-top: 5px;
            padding-top: 10px;
        }
        .totals-row.total .totals-label,
        .totals-row.total .totals-value {
            font-size: 14px;
            color: #111;
        }
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f8f8;
            border-left: 3px solid #666;
            font-style: italic;
            color: #666;
            font-size: 10px;
        }
        .status {
            margin-top: 30px;
            padding: 10px 15px;
            display: inline-block;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-open {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-draft {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .status-void {
            background-color: #f8d7da;
            color: #721c24;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #999;
            font-size: 9px;
        }
        .period {
            color: #888;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="row">
            <div class="col">
                <div class="company-name">{{ $company['name'] }}</div>
                <div class="company-details">
                    {{ $company['address'] }}<br>
                    VAT ID: {{ $company['vat_id'] }}<br>
                    {{ $company['email'] }}
                </div>
            </div>
            <div class="col text-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <strong>{{ $invoice->invoice_number }}</strong><br>
                    Date: {{ $invoice->created_at->format('d.m.Y') }}
                    @if($invoice->billing_period_start && $invoice->billing_period_end)
                        <br>Period: {{ $invoice->billing_period_start->format('d.m.Y') }} - {{ $invoice->billing_period_end->format('d.m.Y') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="section-title">Bill To</div>
            <div class="customer-name">{{ $invoice->customer_name }}</div>
            <div class="customer-details">
                @if($invoice->customer_address){{ $invoice->customer_address }}<br>@endif
                @if($invoice->customer_country){{ $invoice->customer_country }}<br>@endif
                @if($invoice->customer_vat_id)VAT ID: {{ $invoice->customer_vat_id }}@endif
            </div>
        </div>
        <div class="col">
            <div class="section-title">Payment Details</div>
            <div class="customer-details">
                Currency: {{ $invoice->currency }}<br>
                @if($invoice->isPaid())
                    Paid on: {{ $invoice->paid_at->format('d.m.Y') }}
                @endif
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 45%;">Description</th>
                <th style="width: 20%;">Period</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 12%;">Unit Price</th>
                <th class="text-right" style="width: 13%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->line_items as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="period">
                    @if(isset($item['period_start']) && isset($item['period_end']))
                        {{ $item['period_start'] }} - {{ $item['period_end'] }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">{{ $item['quantity'] }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="text-right">{{ number_format($item['amount'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <div class="totals-label">Subtotal</div>
            <div class="totals-value">{{ number_format($invoice->amount_net, 2) }} {{ $invoice->currency }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">VAT ({{ number_format($invoice->vat_rate, 0) }}%)</div>
            <div class="totals-value">{{ number_format($invoice->vat_amount, 2) }} {{ $invoice->currency }}</div>
        </div>
        <div class="totals-row total">
            <div class="totals-label">Total</div>
            <div class="totals-value">{{ number_format($invoice->amount_gross, 2) }} {{ $invoice->currency }}</div>
        </div>
    </div>

    @if($invoice->notes)
    <div class="notes">
        <strong>Note:</strong> {{ $invoice->notes }}
    </div>
    @endif

    <div class="status status-{{ $invoice->status }}">
        {{ ucfirst($invoice->status) }}
        @if($invoice->isPaid() && $invoice->paid_at)
            - Paid on {{ $invoice->paid_at->format('d.m.Y') }}
        @endif
    </div>

    <div class="footer">
        This invoice was generated automatically by {{ $company['name'] }}.<br>
        For questions regarding this invoice, please contact {{ $company['email'] }}.
    </div>
</body>
</html>
