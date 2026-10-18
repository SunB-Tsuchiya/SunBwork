<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\ClerkCalendarReminder;
use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\SunA\GeneralAffairs\Nencho\Notice;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\SunA\GeneralAffairs\Nencho\Target;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 年末調整の案内。既存の「お知らせ」（Announcement）とカレンダーのリマインダー（ClerkCalendarReminder）を使い、専用の通知は作らない。
 *  - 受付開始のお知らせ: 対象者全員（除外した人を除く）
 *  - 未提出の方への催促: 対象者のうち、未入力・入力中・差戻しの人
 */
class NoticeService
{
    /** 宛先のユーザー ID（ユーザー削除済みは除く） */
    public function recipients(Campaign $campaign, string $kind): array
    {
        $targets = Target::where('campaign_id', $campaign->id)->where('is_excluded', false)->whereNotNull('user_id')->pluck('user_id');
        if ($kind === 'start') {
            return $targets->unique()->values()->all();
        }
        $submitted = Submission::where('campaign_id', $campaign->id)->where('status', 'submitted')->pluck('user_id');

        return $targets->diff($submitted)->unique()->values()->all();
    }

    /** 送信前に見せる文面の下書き（総務が編集して送れる） */
    public function drafts(Campaign $campaign): array
    {
        $due = $campaign->due_on->format('Y年n月j日');
        $open = $campaign->opens_on->format('Y年n月j日');
        $tail = "入力は、ユーザー用のタブ「年末調整」から行ってください。\nご不明な点は、総務までご連絡ください。";
        $msg = trim((string) $campaign->message);

        return [
            'start' => [
                'title' => "【年末調整】{$campaign->title}の入力をお願いします",
                'content' => ($msg !== '' ? $msg . "\n\n" : '') . "受付期間：{$open} ～ {$due}\n提出の締切：{$due}\n\n" . $tail,
                'count' => count($this->recipients($campaign, 'start')),
            ],
            'reminder' => [
                'title' => "【年末調整】{$campaign->title}の提出がまだの方へ（締切：{$due}）",
                'content' => "年末調整の提出が、まだ完了していません。\n提出の締切は {$due} です。お早めに入力・提出をお願いします。\n\n" . $tail,
                'count' => count($this->recipients($campaign, 'reminder')),
            ],
        ];
    }

    /** お知らせとして送信し、履歴を残す。宛先の人数を返す */
    public function send(Campaign $campaign, string $kind, string $title, string $content, User $sender): int
    {
        $ids = $this->recipients($campaign, $kind);
        abort_if(! $ids, 422, '宛先がいません。');

        return DB::transaction(function () use ($campaign, $kind, $title, $content, $sender, $ids) {
            $announcement = Announcement::create([
                'sender_id' => $sender->id,
                'target_type' => 'individual',
                'title' => $title,
                'content' => $content,
                'target_company_id' => $campaign->company_id,
                'status' => 'sent',
            ]);
            AnnouncementRecipient::upsert(array_map(fn ($uid) => [
                'announcement_id' => $announcement->id, 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now(),
            ], $ids), ['announcement_id', 'user_id'], ['updated_at']);
            Notice::create([
                'campaign_id' => $campaign->id, 'kind' => $kind, 'announcement_id' => $announcement->id, 'title' => $title,
                'recipient_count' => count($ids), 'sent_by' => $sender->id, 'sent_by_name' => $sender->name, 'created_at' => now(),
            ]);

            return count($ids);
        });
    }

    /** リマインダー文面の下書き */
    public function reminderDraft(Campaign $campaign): string
    {
        return "【年末調整】{$campaign->title}：" . $campaign->due_on->format('n月j日') . 'までに、ユーザー用タブ「年末調整」から入力・提出してください。';
    }

    /** カレンダーのリマインダー（受付期間に、その会社のユーザーのカレンダー上部へ案内を表示）を作成／更新する。文面は総務が編集できる */
    public function upsertReminder(Campaign $campaign, User $by, ?string $content = null): ClerkCalendarReminder
    {
        $content = $content !== null && trim($content) !== '' ? trim($content) : $this->reminderDraft($campaign);
        $attrs = ['company_id' => $campaign->company_id, 'content' => $content, 'color_key' => 'orange',
            'starts_on' => $campaign->opens_on->toDateString(), 'ends_on' => $campaign->due_on->toDateString(), 'is_active' => true];
        $reminder = $campaign->calendar_reminder_id ? ClerkCalendarReminder::find($campaign->calendar_reminder_id) : null;
        if ($reminder) {
            $reminder->update($attrs);
        } else {
            $reminder = ClerkCalendarReminder::create($attrs + ['created_by' => $by->id]);
            $campaign->update(['calendar_reminder_id' => $reminder->id]);
        }

        return $reminder;
    }

    /** リマインダーを削除する（受付との紐づけも外す） */
    public function removeReminder(Campaign $campaign): void
    {
        if ($campaign->calendar_reminder_id) {
            ClerkCalendarReminder::where('id', $campaign->calendar_reminder_id)->delete();
            $campaign->update(['calendar_reminder_id' => null]);
        }
    }

    /** 受付の日付を変えたとき、紐づいているリマインダーの期間も合わせる */
    public function syncReminder(Campaign $campaign, User $by): void
    {
        // 期間だけ合わせる（総務が編集した文面は上書きしない）
        if ($campaign->calendar_reminder_id) {
            ClerkCalendarReminder::whereKey($campaign->calendar_reminder_id)->update([
                'starts_on' => $campaign->opens_on->toDateString(), 'ends_on' => $campaign->due_on->toDateString(),
            ]);
        }
    }
}
