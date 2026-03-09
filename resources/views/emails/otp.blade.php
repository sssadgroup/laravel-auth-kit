<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Code de vérification</title>
    <style>
        body  { margin:0; padding:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f0f2f5; }
        .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.08); }
        .hdr  { background:linear-gradient(135deg,#1a1a2e,#16213e); color:#fff; padding:36px 40px; text-align:center; }
        .hdr h1 { margin:0; font-size:20px; letter-spacing:.5px; }
        .hdr p  { margin:8px 0 0; font-size:13px; opacity:.7; }
        .body { padding:36px 40px; color:#333; line-height:1.6; }
        .otp  { font-size:42px; font-weight:700; letter-spacing:14px; text-align:center; color:#1a1a2e; background:#f5f6fa; border:2px dashed #d0d3e0; border-radius:10px; padding:22px 16px; margin:28px 0; }
        .warn { background:#fff8e1; border-left:4px solid #f59e0b; border-radius:4px; padding:12px 16px; font-size:13px; color:#92400e; margin-top:20px; }
        .foot { background:#f5f6fa; text-align:center; padding:20px; font-size:12px; color:#9ca3af; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hdr">
            <h1>🔐 Vérification de votre identité</h1>
            <p>{{ config('app.name') }}</p>
        </div>
        <div class="body">
            <p>Bonjour,</p>
            <p>Vous avez demandé une réinitialisation de mot de passe. Voici votre code de vérification :</p>

            <div class="otp">{{ $otp }}</div>

            <p>
                Ce code est valable <strong>{{ $expiresIn }} minutes</strong>.<br>
                Passé ce délai, vous devrez effectuer une nouvelle demande.
            </p>

            <div class="warn">
                ⚠️ <strong>Ne partagez jamais ce code.</strong>
                Aucun membre de notre équipe ne vous le demandera.
                Si vous n'avez pas fait cette demande, ignorez cet email.
            </div>
        </div>
        <div class="foot">
            &copy; {{ date('Y') }} {{ config('app.name') }} — Tous droits réservés.
        </div>
    </div>
</body>
</html>
