<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اشتراک</title>
    <link rel="stylesheet" href="{{ asset('portal/subscription.css') }}">
</head>
<body>
    <main class="wrap">
        <h1>لینک‌های اشتراک</h1>
        @if(!empty($userinfo))
            <p class="meta">{{ $userinfo }}</p>
        @endif
        <ul class="uris">
            @foreach($uris as $uri)
                <li>
                    <code class="uri">{{ $uri }}</code>
                    <button type="button" class="copy" data-uri="{{ $uri }}">کپی</button>
                </li>
            @endforeach
        </ul>
        @if(count($uris) === 1)
            <div id="qrcode" data-uri="{{ $uris[0] }}"></div>
        @endif
    </main>
    <script>
        document.querySelectorAll('.copy').forEach((btn) => {
            btn.addEventListener('click', () => {
                navigator.clipboard.writeText(btn.dataset.uri || '');
                btn.textContent = 'کپی شد';
            });
        });
    </script>
</body>
</html>
