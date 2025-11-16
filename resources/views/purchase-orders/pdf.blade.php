<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $order->po_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 32px;
            font-size: 12px;
            background-color: #f9fafb;
        }

        .po-wrapper {
            background-color: #ffffff;
            padding: 32px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .flex {
            display: flex;
            width: 100%;
        }

        .flex-between {
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-details h1 {
            margin: 0 0 4px;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .company-meta {
            margin: 0;
            color: #4b5563;
            line-height: 1.5;
        }

        .po-meta {
            text-align: right;
        }

        .po-number {
            font-size: 26px;
            margin: 0;
            font-weight: 600;
            color: #111827;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            background-color: #2563eb;
            color: #ffffff;
        }

        .status-badge.status-approved { background-color: #059669; }
        .status-badge.status-pending { background-color: #f59e0b; }
        .status-badge.status-ordered { background-color: #7c3aed; }
        .status-badge.status-partially_received { background-color: #0ea5e9; }
        .status-badge.status-received { background-color: #16a34a; }
        .status-badge.status-cancelled { background-color: #dc2626; }

        .section {
            margin-top: 28px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            color: #111827;
            letter-spacing: 1.2px;
            margin-bottom: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 24px;
        }

        .info-grid p {
            margin: 0;
            color: #374151;
            line-height: 1.6;
        }

        .info-label {
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        thead tr {
            background-color: #111827;
            color: #ffffff;
        }

        thead th {
            padding: 12px 14px;
            font-weight: 600;
            text-align: left;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background-color: #f3f4f6;
        }

        tbody td {
            padding: 12px 14px;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody td.numeric {
            text-align: right;
            white-space: nowrap;
        }

        .totals-table {
            width: 280px;
            margin-left: auto;
            margin-top: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .totals-table tr {
            background-color: #ffffff;
        }

        .totals-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .totals-table td {
            padding: 10px 14px;
            font-size: 12px;
        }

        .totals-table td.label {
            font-weight: 600;
            color: #4b5563;
        }

        .totals-table tr.total-row td {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .notes {
            margin-top: 24px;
            padding: 16px;
            border-radius: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .footer {
            margin-top: 48px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }

        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
@php
    $supplierAddress = [];
    if (!empty($supplier?->address)) {
        $addressValue = $supplier->address;
        if (is_string($addressValue)) {
            $decoded = json_decode($addressValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $supplierAddress = $decoded;
            }
        } elseif (is_array($addressValue)) {
            $supplierAddress = $addressValue;
        } elseif (is_object($addressValue)) {
            $supplierAddress = (array) $addressValue;
        }
    }

    $formatDate = static function ($date) {
        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->format('M d, Y');
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format('M d, Y');
        }

        if ($date) {
            try {
                return \Carbon\Carbon::parse($date)->format('M d, Y');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    };
@endphp
<div class="po-wrapper">
    <div class="flex flex-between">
        <div class="company-details">
            @if(!empty($company['logo']))
                <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }}" class="logo">
            @endif
            <h1>{{ $company['name'] }}</h1>
            <p class="company-meta">
                {{ $company['address'] }}<br>
                {{ $company['email'] }} | {{ $company['phone'] }}
            </p>
        </div>
        <div class="po-meta">
            <p class="po-number">Purchase Order</p>
            <p class="status-badge status-{{ $order->status }}">{{ strtoupper(str_replace('_', ' ', $order->status)) }}</p>
            <p class="company-meta" style="margin-top: 12px;">
                <span class="info-label">PO Number:</span> {{ $order->po_number }}<br>
                <span class="info-label">Created:</span> {{ $formatDate($order->created_at) ?? 'N/A' }}<br>
                @if($order->expected_delivery_date)
                    <span class="info-label">Expected Delivery:</span> {{ $formatDate($order->expected_delivery_date) }}<br>
                @endif
                <span class="info-label">Payment Status:</span> {{ strtoupper(str_replace('_', ' ', $order->payment_status)) }}
            </p>
        </div>
    </div>

    <div class="section">
        <div class="flex flex-between">
            <div style="width: 48%;">
                <div class="section-title">Supplier</div>
                <div class="info-grid">
                    <p><span class="info-label">Name:</span> {{ $supplier->name ?? 'N/A' }}</p>
                    <p><span class="info-label">Supplier Code:</span> {{ $supplier->supplier_code ?? 'N/A' }}</p>
                    <p><span class="info-label">Email:</span> {{ $supplier->email ?? '—' }}</p>
                    <p><span class="info-label">Phone:</span> {{ $supplier->phone ?? '—' }}</p>
                    <p style="grid-column: span 2;">
                        <span class="info-label">Address:</span>
                        {{ $supplierAddress['street'] ?? '—' }}
                        {{ $supplierAddress['city'] ?? '' }}
                    </p>
                </div>
            </div>
            <div style="width: 48%;">
                <div class="section-title">Order Summary</div>
                <div class="info-grid">
                    <p><span class="info-label">Status:</span> {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                    <p><span class="info-label">Created By:</span> {{ $order->created_by ?? 'System' }}</p>
                    @if($order->approved_at)
                        <p><span class="info-label">Approved By:</span> {{ $order->approved_by ?? '—' }}</p>
                        <p><span class="info-label">Approved On:</span> {{ $formatDate($order->approved_at) }}</p>
                    @endif
                    @if($order->ordered_at)
                        <p><span class="info-label">Ordered On:</span> {{ $formatDate($order->ordered_at) }}</p>
                    @endif
                    @if($order->received_at)
                        <p><span class="info-label">Received On:</span> {{ $formatDate($order->received_at) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Line Items</div>
        <table>
            <thead>
            <tr>
                <th style="width: 30%;">Item</th>
                <th style="width: 12%;">SKU</th>
                <th style="width: 8%; text-align: right;">Qty</th>
                <th style="width: 12%; text-align: right;">Unit Cost</th>
                <th style="width: 10%; text-align: right;">Tax</th>
                <th style="width: 10%; text-align: right;">Discount</th>
                <th style="width: 18%; text-align: right;">Line Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->product_sku ?? '—' }}</td>
                    <td class="numeric">{{ number_format($item->quantity) }}</td>
                    <td class="numeric">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="numeric">{{ number_format($item->tax, 2) }}</td>
                    <td class="numeric">{{ number_format($item->discount, 2) }}</td>
                    <td class="numeric">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td class="label">Subtotal</td>
                <td class="numeric">{{ number_format($totals['subtotal'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="numeric">{{ number_format($totals['tax'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Discounts</td>
                <td class="numeric">-{{ number_format($totals['discount'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Shipping</td>
                <td class="numeric">{{ number_format($totals['shipping'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Due</td>
                <td class="numeric">{{ number_format($totals['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    @if(!empty($order->notes) || !empty($order->terms_and_conditions))
        <div class="section">
            <div class="section-title">Notes & Terms</div>
            <div class="notes">
                @if(!empty($order->notes))
                    <p style="margin: 0 0 8px;"><span class="info-label">Notes:</span> {{ $order->notes }}</p>
                @endif
                @if(!empty($order->terms_and_conditions))
                    <p style="margin: 0;"><span class="info-label">Terms:</span> {{ $order->terms_and_conditions }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="footer">
        This document was generated electronically on {{ now()->format('M d, Y \a\t h:i A') }} and is valid without a signature.<br>
        Thank you for partnering with {{ $company['name'] }}.
    </div>
</div>
</body>
</html>
