<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Bienvenue — Vos accès</title>
    <style>
        body  { margin:0; padding:0; font-family:'Segoe UI', Arial, sans-serif; background:#f0f2f5; }
        .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.08); }
        .hdr  { background:linear-gradient(135deg,#065f46,#047857); color:#fff; padding:36px 40px; text-align:center; }
        .hdr h1 { margin:0; font-size:20px; }
        .hdr p  { margin:8px 0 0; font-size:13px; opacity:.75; }
        .body { padding:36px 40px; color:#333; line-height:1.7; }
        .cred { background:#f5f6fa; border:1px solid #e5e7eb; border-radius:8px; padding:20px 24px; margin:24px 0; }
        .cred p { margin:6px 0; font-size:14px; }
        .cred strong { color:#1a1a2e; }
        .pwd  { font-size:22px; font-weight:700; letter-spacing:4px; color:#065f46; background:#ecfdf5; border:1px dashed #6ee7b7; border-radius:6px; padding:10px 16px; display:inline-block; margin-top:4px; }
        .warn { background:#fff8e1; border-left:4px solid #f59e0b; border-radius:4px; padding:12px 16px; font-size:13px; color:#92400e; margin-top:20px; }
        .btn  { display:block; width:fit-content; margin:28px auto 0; background:#065f46; color:#fff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; font-size:15px; }
        .foot { background:#f5f6fa; text-align:center; padding:20px; font-size:12px; color:#9ca3af; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hdr">
            <h1>👋 Bienvenue sur {{ config('app.name') }}</h1>
            <p>Votre compte a été créé par un administrateur</p>
        </div>
        <div class="body">
            <p>Bonjour <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,</p>
            <p>
                Un compte a été créé pour vous sur <strong>{{ config('app.name') }}</strong>.
                Voici vos identifiants de connexion :
            </p>

            <div class="cred">
                <p><strong>Email :</strong> {{ $user->email }}</p>
                <p><strong>Mot de passe temporaire :</strong></p>
                <span class="pwd">{{ $temporaryPassword }}</span>
            </div>

            <div class="warn">
                🔒 <strong>Action requise :</strong> Ce mot de passe est temporaire.
                Vous devrez le modifier dès votre première connexion pour sécuriser votre compte.
            </div>

            <a href="{{ $loginUrl }}" class="btn">Se connecter →</a>
        </div>
        <div class="foot">
            &copy; {{ date('Y') }} {{ config('app.name') }} — Tous droits réservés.<br>
            Si vous n'êtes pas concerné par cet email, ignorez-le.
        </div>
    </div>
</body>
</html>
