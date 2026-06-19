<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Hiragino Sans', 'Yu Gothic', sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px; border: 1px solid #e5e7eb; }
        h1 { font-size: 1.1rem; color: #1a3a6b; margin: 0 0 20px; border-bottom: 2px solid #1a3a6b; padding-bottom: 12px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table th { text-align: left; padding: 8px 12px; background: #f9fafb; color: #6b7280; font-size: 0.85rem; width: 140px; border: 1px solid #e5e7eb; }
        .info-table td { padding: 8px 12px; font-size: 0.9rem; border: 1px solid #e5e7eb; }
        .action-box { background: #fef9c3; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; margin: 16px 0; font-size: 0.9rem; }
        .footer { margin-top: 24px; font-size: 0.78rem; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <h1>デモページ管理 — 設定変更通知</h1>

    <p style="font-size: 0.9rem; color: #374151;">
        SuperAdmin 専用通知です。以下のデモページの設定が変更されました。
    </p>

    <table class="info-table">
        <tr>
            <th>デモページ名</th>
            <td><strong>{{ $demoPage->name }}</strong></td>
        </tr>
        <tr>
            <th>スラッグ</th>
            <td><code>{{ $demoPage->slug }}</code></td>
        </tr>
        <tr>
            <th>操作者</th>
            <td>{{ $operatorName }}</td>
        </tr>
        <tr>
            <th>操作日時</th>
            <td>{{ now()->setTimezone('Asia/Tokyo')->format('Y年m月d日 H:i') }}</td>
        </tr>
        <tr>
            <th>変更内容</th>
            <td>
                @switch($action)
                    @case('password_changed')
                        <span class="badge badge-warning">パスワード変更</span>
                        @break
                    @case('email_added')
                        <span class="badge badge-info">許可メール追加</span>
                        @break
                    @case('email_removed')
                        <span class="badge badge-warning">許可メール削除</span>
                        @break
                    @case('status_changed')
                        <span class="badge badge-warning">公開状態変更</span>
                        @break
                    @case('expiry_changed')
                        <span class="badge badge-info">公開期限変更</span>
                        @break
                    @default
                        <span class="badge badge-info">設定更新</span>
                @endswitch
            </td>
        </tr>
        @if($detail)
        <tr>
            <th>詳細</th>
            <td>{{ $detail }}</td>
        </tr>
        @endif
    </table>

    <div class="action-box">
        現在の状態 ／
        公開: <strong>{{ $demoPage->is_active ? '有効' : '無効' }}</strong>
        &nbsp;｜&nbsp;
        期限: <strong>{{ $demoPage->expires_at ? $demoPage->expires_at->setTimezone('Asia/Tokyo')->format('Y/m/d H:i') : '無期限' }}</strong>
        &nbsp;｜&nbsp;
        許可メール数: <strong>{{ $demoPage->emails()->count() }}</strong> 件
    </div>

    <p style="font-size: 0.85rem; color: #6b7280;">
        設定変更は SunBwork 管理画面 → SuperAdmin → デモページ管理 から確認できます。
    </p>

    <div class="footer">
        このメールは SunBwork SuperAdmin 宛に自動送信されています。<br>
        心当たりがない場合は管理者にお問い合わせください。
    </div>
</div>
</body>
</html>
