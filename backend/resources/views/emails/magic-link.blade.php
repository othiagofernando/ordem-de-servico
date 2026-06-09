<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link de acesso</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1c1917; padding: 32px 40px; }
        .header h1 { color: #fbbf24; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .body { padding: 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background: #f59e0b; color: #1c1917; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 36px; border-radius: 8px; }
        .link-fallback { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin: 20px 0; }
        .link-fallback p { margin: 0 0 8px; font-size: 12px; color: #6b7280; }
        .link-fallback code { font-size: 11px; color: #374151; word-break: break-all; }
        .validity { font-size: 13px; color: #9ca3af; text-align: center; }
        .footer { background: #f9fafb; border-top: 1px solid #f3f4f6; padding: 20px 40px; font-size: 12px; color: #9ca3af; }
        .footer p { margin: 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }}</h1>
        </div>
        <div class="body">
            <p>Olá,</p>
            <p>Clique no botão abaixo para acessar o <strong>{{ $companyName }}</strong> sem precisar de senha:</p>
            <div class="btn-wrap">
                <a href="{{ $magicLinkUrl }}" class="btn">Acessar agora</a>
            </div>
            <p class="validity">Este link expira em 10 minutos e só pode ser usado uma vez.</p>
            <div class="link-fallback">
                <p>Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
                <code>{{ $magicLinkUrl }}</code>
            </div>
            <p>Se você não solicitou este link, ignore este e-mail.</p>
        </div>
        <div class="footer">
            <p>Este é um e-mail automático. Não responda a esta mensagem.</p>
        </div>
    </div>
</body>
</html>
