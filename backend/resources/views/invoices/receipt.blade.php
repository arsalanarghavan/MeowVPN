<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>رسید تراکنش #{{ $transaction->id }}</title>
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
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #10b981;
        }
        .receipt-title {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }
        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            font-weight: bold;
        }
        .amount-box {
            background: #d1fae5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .amount-label {
            color: #065f46;
            margin-bottom: 5px;
        }
        .amount-value {
            font-size: 28px;
            font-weight: bold;
            color: #10b981;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">{{ $companyName }}</div>
        <div class="receipt-title">رسید تراکنش</div>
    </div>

    <div class="info-box">
        <div class="info-row">
            <span class="info-label">شماره تراکنش:</span>
            <span class="info-value">#{{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">تاریخ:</span>
            <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromCarbon($transaction->created_at)->format('Y/m/d H:i') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">نوع تراکنش:</span>
            <span class="info-value">
                @switch($transaction->type)
                    @case('deposit') شارژ کیف پول @break
                    @case('purchase') خرید سرویس @break
                    @case('renewal') تمدید سرویس @break
                    @case('refund') استرداد @break
                    @default {{ $transaction->type }}
                @endswitch
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">درگاه:</span>
            <span class="info-value">
                @switch($transaction->gateway)
                    @case('zibal') درگاه زیبال @break
                    @case('card') کارت به کارت @break
                    @case('wallet') کیف پول @break
                    @default {{ $transaction->gateway }}
                @endswitch
            </span>
        </div>
        @if($transaction->ref_id)
            <div class="info-row">
                <span class="info-label">شماره پیگیری:</span>
                <span class="info-value">{{ $transaction->ref_id }}</span>
            </div>
        @endif
    </div>

    <div class="info-box">
        <div class="info-row">
            <span class="info-label">کاربر:</span>
            <span class="info-value">{{ $user->username ?? $user->email }}</span>
        </div>
        @if($subscription)
            <div class="info-row">
                <span class="info-label">سرویس:</span>
                <span class="info-value">#{{ $subscription->id }} - {{ $subscription->plan?->name ?? '-' }}</span>
            </div>
        @endif
    </div>

    <div class="amount-box">
        <div class="amount-label">مبلغ تراکنش</div>
        <div class="amount-value">{{ number_format(abs($transaction->amount)) }} ریال</div>
    </div>

    <div style="text-align: center;">
        <span class="status-badge status-{{ $transaction->status }}">
            @switch($transaction->status)
                @case('completed') موفق @break
                @case('pending') در انتظار @break
                @case('failed') ناموفق @break
                @default {{ $transaction->status }}
            @endswitch
        </span>
    </div>

    @if($transaction->description)
        <div class="info-box" style="margin-top: 20px;">
            <p style="margin: 0; color: #666;">توضیحات: {{ $transaction->description }}</p>
        </div>
    @endif

    <div class="footer">
        <p>{{ $companyName }}</p>
        <p>با تشکر از اعتماد شما</p>
        <p>این رسید به صورت خودکار صادر شده است.</p>
    </div>
</body>
</html>

