<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>فاکتور #{{ $invoice->id }}</title>
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Vazir';
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/Vazir-Bold.woff2') format('woff2');
            font-weight: bold;
            font-style: normal;
        }
        * {
            font-family: 'Vazir', 'Tahoma', 'Arial', sans-serif;
            box-sizing: border-box;
        }
        body {
            direction: rtl;
            padding: 40px;
            color: #333;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #10b981;
        }
        .invoice-info {
            text-align: left;
        }
        .invoice-number {
            font-size: 20px;
            color: #666;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #10b981;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            padding: 8px 0;
            color: #666;
            width: 150px;
        }
        .info-value {
            display: table-cell;
            padding: 8px 0;
            font-weight: bold;
        }
        table.transactions {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.transactions th,
        table.transactions td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e5e7eb;
        }
        table.transactions th {
            background: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        table.transactions tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        .total-section {
            margin-top: 30px;
            padding: 20px;
            background: #f0fdf4;
            border: 1px solid #10b981;
            border-radius: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        .total-label {
            font-weight: bold;
        }
        .total-value {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">{{ $companyName }}</div>
        <div class="invoice-info">
            <div class="invoice-number">فاکتور #{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div style="color: #666; margin-top: 5px;">
                تاریخ صدور: {{ \Morilog\Jalali\Jalalian::fromCarbon($invoice->created_at)->format('Y/m/d') }}
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">اطلاعات نماینده</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">نام:</div>
                <div class="info-value">{{ $reseller->username }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">برند:</div>
                <div class="info-value">{{ $reseller->resellerProfile?->brand_name ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">ایمیل:</div>
                <div class="info-value">{{ $reseller->email }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">شماره تماس:</div>
                <div class="info-value">{{ $reseller->resellerProfile?->contact_number ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">دوره فاکتور</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">از تاریخ:</div>
                <div class="info-value">{{ \Morilog\Jalali\Jalalian::fromCarbon($invoice->start_date)->format('Y/m/d') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">تا تاریخ:</div>
                <div class="info-value">{{ \Morilog\Jalali\Jalalian::fromCarbon($invoice->end_date)->format('Y/m/d') }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">جزئیات تراکنش‌ها</div>
        @if($transactions->count() > 0)
            <table class="transactions">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تاریخ</th>
                        <th>شرح</th>
                        <th>مبلغ (ریال)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $index => $tx)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Morilog\Jalali\Jalalian::fromCarbon($tx->created_at)->format('Y/m/d') }}</td>
                            <td>{{ $tx->description ?? 'خرید سرویس' }}</td>
                            <td>{{ number_format(abs($tx->amount)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #666; text-align: center; padding: 20px;">هیچ تراکنشی در این دوره ثبت نشده است.</p>
        @endif
    </div>

    <div class="total-section">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span>وضعیت:</span>
            <span class="status-badge status-{{ $invoice->status }}">
                @switch($invoice->status)
                    @case('pending') در انتظار پرداخت @break
                    @case('paid') پرداخت شده @break
                    @case('overdue') سررسید گذشته @break
                    @default {{ $invoice->status }}
                @endswitch
            </span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="total-label">مبلغ کل قابل پرداخت:</span>
            <span class="total-value">{{ number_format($invoice->total_amount) }} ریال</span>
        </div>
    </div>

    <div class="footer">
        <p>{{ $companyName }}</p>
        <p>پشتیبانی: {{ $companyPhone }}</p>
        <p>این فاکتور به صورت خودکار صادر شده است.</p>
    </div>
</body>
</html>

