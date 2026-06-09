<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de acesso</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1c1917; padding: 32px 40px; }
        .header h1 { color: #fbbf24; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .body { padding: 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .code-box { background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 10px; padding: 24px; text-align: center; margin: 28px 0; }
        .code { font-size: 40px; font-weight: 800; letter-spacing: 0.3em; color: #1c1917; font-family: 'Courier New', monospace; }
        .validity { font-size: 13px; color: #9ca3af; margin-top: 10px; }
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
            <p>Seu código de acesso ao <strong>{{ $companyName }}</strong> é:</p>
            <div class="code-box">
                <div class="code">{{ $code }}</div>
                <p class="validity">Válido por 10 minutos · Use uma única vez</p>
            </div>
            <p>Se você não solicitou este código, ignore este e-mail. Nenhuma ação é necessária.</p>
        </div>
        <div class="footer">
            <p>Este é um e-mail automático. Não responda a esta mensagem.</p>
        </div>
    </div>
</body>
</html>
