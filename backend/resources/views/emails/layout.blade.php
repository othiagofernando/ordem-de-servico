<!DOCTYPE html>
<html lang="pt-BR" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f5f5f0; margin: 0; padding: 32px 16px; color: #1a1a1a; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        a { color: #d97706; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Preheader — hidden text shown in email client preview */
        .preheader { display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; font-size: 1px; line-height: 1px; }

        /* Layout */
        .container { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06); }

        /* Header */
        .header { background: #1c1917; padding: 28px 40px; }
        .header-eyebrow { color: #78716c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin: 0 0 8px; }
        .header h1 { color: #fbbf24; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; line-height: 1.3; }

        /* Body */
        .body { padding: 36px 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; color: #374151; }
        .body p:last-child { margin-bottom: 0; }

        /* Button */
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background: #f59e0b; color: #1c1917 !important; font-size: 15px; font-weight: 700; text-decoration: none !important; padding: 13px 32px; border-radius: 8px; letter-spacing: -0.1px; }

        /* OTP Code box */
        .code-box { background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 10px; padding: 24px; text-align: center; margin: 28px 0; }
        .code { font-size: 40px; font-weight: 800; letter-spacing: 0.3em; color: #1c1917; font-family: 'Courier New', monospace; }
        .code-validity { font-size: 13px; color: #9ca3af; margin: 10px 0 0; }

        /* Note / alert box */
        .note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin: 0 0 20px; }
        .note p { margin: 0; font-size: 13px; color: #92400e; line-height: 1.5; }

        /* Info box — key/value rows */
        .info-box { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin: 24px 0; }
        .info-row { padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6b7280; }
        .info-value { color: #111827; font-weight: 600; text-align: right; }

        /* Fallback URL box */
        .link-fallback { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin: 20px 0; }
        .link-fallback-label { margin: 0 0 8px; font-size: 12px; color: #6b7280; }
        .link-fallback code { font-size: 11px; color: #374151; word-break: break-all; }

        /* Helpers */
        .divider { border: none; border-top: 1px solid #f3f4f6; margin: 24px 0; }
        .helper { font-size: 13px; color: #9ca3af; text-align: center; margin: 0 0 16px; }
        .muted { font-size: 13px; color: #9ca3af; }

        /* Footer */
        .footer { background: #f9fafb; border-top: 1px solid #f3f4f6; padding: 20px 40px; }
        .footer p { margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.6; }

        @media (max-width: 600px) {
            body { padding: 16px 12px; }
            .header, .body, .footer { padding-left: 24px !important; padding-right: 24px !important; }
            .code { font-size: 32px; }
            .btn { padding: 13px 24px; }
        }
    </style>
</head>
<body>
    @hasSection('preheader')
    <div class="preheader">@yield('preheader') &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
    @endif
    <div class="container">
        <div class="header">
            @hasSection('header-eyebrow')
            <p class="header-eyebrow">@yield('header-eyebrow')</p>
            @endif
            <h1>@yield('header')</h1>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>@yield('footer', 'Este é um e-mail automático. Não responda a esta mensagem.')</p>
        </div>
    </div>
</body>
</html>
