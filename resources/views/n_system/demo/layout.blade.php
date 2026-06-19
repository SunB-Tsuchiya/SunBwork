<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '入試データ デモ') | N_DB SAMPLE</title>
    <link rel="stylesheet" href="/n_sample.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Hiragino Sans', 'Noto Sans JP', 'Yu Gothic', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .ndemo-header {
            background: #1a3a6b;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .ndemo-header h1 {
            font-size: 1.05rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .ndemo-header a {
            color: #aad4ff;
            text-decoration: none;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .ndemo-header a:hover { text-decoration: underline; }
        .ndemo-header-right {
            display: flex;
            gap: 8px;
            margin-left: auto;
            align-items: center;
        }
        .ndemo-search-form {
            display: flex;
            gap: 6px;
        }
        .ndemo-search-form input[type="text"] {
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            width: 200px;
            font-size: 0.9rem;
        }
        .ndemo-search-form button {
            padding: 5px 12px;
            border-radius: 4px;
            border: none;
            background: #4a9eff;
            color: #fff;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .ndemo-search-form button:hover { background: #2280e0; }
        .ndemo-logout-btn {
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.4);
            background: transparent;
            color: #cce0ff;
            cursor: pointer;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .ndemo-logout-btn:hover { background: rgba(255,255,255,0.1); }
        .ndemo-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        .ndemo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-共学 { background: #d1fae5; color: #065f46; }
        .badge-男子 { background: #dbeafe; color: #1e40af; }
        .badge-女子 { background: #fce7f3; color: #9d174d; }
        .badge-地方 { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
<header class="ndemo-header">
    <h1>N_DB SAMPLE — 入試データ デモ</h1>
    <a href="{{ route('n-demo.index') }}">学校一覧</a>
    <a href="{{ route('n-demo.search') }}">全文検索</a>
    <div class="ndemo-header-right">
        <form class="ndemo-search-form" action="{{ route('n-demo.search') }}" method="get">
            <input type="text" name="q" placeholder="キーワード検索…" value="{{ request('q') }}">
            <button type="submit">検索</button>
        </form>
        @if(!auth()->check())
        <form action="{{ route('n-guest.logout') }}" method="post" style="margin:0">
            @csrf
            <input type="hidden" name="slug" value="n-demo">
            <button type="submit" class="ndemo-logout-btn">ログアウト</button>
        </form>
        @endif
    </div>
</header>
<div class="ndemo-container">
    @yield('content')
</div>
</body>
</html>
