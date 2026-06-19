<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デモページ ログイン | N_DB SAMPLE</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Hiragino Sans', 'Noto Sans JP', 'Yu Gothic', sans-serif;
            background: #f0f4fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            padding: 40px 48px;
            width: 100%;
            max-width: 420px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo h1 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a3a6b;
            margin: 0 0 4px;
            letter-spacing: 0.05em;
        }
        .login-logo p {
            font-size: 0.8rem;
            color: #9ca3af;
            margin: 0;
        }
        .login-divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 0 0 24px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            margin-bottom: 18px;
            transition: border-color 0.15s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .error-msg {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #b91c1c;
            font-size: 0.85rem;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .login-btn {
            width: 100%;
            padding: 11px;
            background: #1a3a6b;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: background 0.15s;
        }
        .login-btn:hover { background: #2563eb; }
        .login-note {
            text-align: center;
            font-size: 0.78rem;
            color: #9ca3af;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">
        <h1>N_DB SAMPLE</h1>
        <p>入試データ デモページ</p>
    </div>
    <hr class="login-divider">

    @if($errors->any())
    <div class="error-msg">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('n-guest.login.post') }}" method="post">
        @csrf
        <input type="hidden" name="for" value="{{ $slug ?? 'n-demo' }}">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email"
               value="{{ old('email') }}"
               autocomplete="username"
               autofocus required>

        <label for="password">パスワード</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>

        <button type="submit" class="login-btn">ログイン</button>
    </form>
    <p class="login-note">このページはデモ用です。<br>アクセス情報はご担当者にお問い合わせください。</p>
</div>
</body>
</html>
