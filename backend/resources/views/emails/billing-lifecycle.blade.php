<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1c1917; padding: 32px 40px; }
        .header h1 { color: #fbbf24; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .body { padding: 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background: #f59e0b; color: #1c1917 !important; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 36px; border-radius: 8px; }
        .footer { background: #f9fafb; border-top: 1px solid #f3f4f6; padding: 20px 40px; font-size: 12px; color: #9ca3af; }
        .footer p { margin: 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title }}</h1>
        </div>
        <div class="body">
            <p><strong>{{ $companyName }}</strong></p>
            @foreach($lines as $line)
                <p>{{ $line }}</p>
            @endforeach
            @if($actionUrl && $actionText)
                <div class="btn-wrap">
                    <a href="{{ $actionUrl }}" class="btn">{{ $actionText }}</a>
                </div>
            @endif
        </div>
        <div class="footer">
            <p>{{ $footer ?: 'Este e-mail foi enviado automaticamente.' }}</p>
        </div>
    </div>
</body>
</html>
