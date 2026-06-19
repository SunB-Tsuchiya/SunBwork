<?php

namespace App\Mail;

use App\Models\DemoPage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoPageUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DemoPage $demoPage,
        public readonly string $action,      // 'password_changed' | 'email_added' | 'email_removed' | 'status_changed' | 'expiry_changed'
        public readonly string $operatorName,
        public readonly string $detail = '',
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->action) {
            'password_changed' => "[デモページ管理] パスワードが変更されました: {$this->demoPage->name}",
            'email_added'      => "[デモページ管理] 許可メールを追加しました: {$this->demoPage->name}",
            'email_removed'    => "[デモページ管理] 許可メールを削除しました: {$this->demoPage->name}",
            'status_changed'   => "[デモページ管理] 公開状態が変更されました: {$this->demoPage->name}",
            'expiry_changed'   => "[デモページ管理] 公開期限が変更されました: {$this->demoPage->name}",
            default            => "[デモページ管理] 設定が更新されました: {$this->demoPage->name}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo_page_updated');
    }
}
