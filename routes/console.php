<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ghost:cleanup')->daily();

// マイジョブ（自己割当）で日付を過ぎたものを毎日深夜0:05に自動完了
Schedule::command('auto-complete:my-jobs')->dailyAt('00:05');

// AIサマリーのディスパッチを5分毎に実行
Schedule::command('ai:dispatch-summaries')->everyFiveMinutes();

// 予定表: 当日の朝まとめ通知を毎朝8:00に登録
Schedule::command('schedule:send-notifications')->dailyAt('08:00');
