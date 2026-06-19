<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-accent="{{ $uiAccent }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ $title }}</title>
    @if (!empty($boot['branding']['cssVariables']) && is_array($boot['branding']['cssVariables']))
    <style>:root{ @foreach($boot['branding']['cssVariables'] as $varKey => $varVal) @if(is_string($varKey) && is_string($varVal) && $varVal !== '') {{ $varKey }}:{{ $varVal }}; @endif @endforeach }</style>
    @endif
    <link rel="stylesheet" href="{{ $css }}" />
    <script>window.__SIMPLEVPBOT_DASH__=@json($boot, JSON_UNESCAPED_UNICODE);</script>
</head>
<body class="svp-dashboard-body">
    <div id="root"></div>
    <script type="module" src="{{ $js }}"></script>
</body>
</html>
