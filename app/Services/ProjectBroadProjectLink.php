<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\ProjectJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProjectBroadProjectLink
{
    private BroadProjectFavorites $favorites;

    public function __construct(private BroadProjectTree $tree)
    {
        $this->favorites = new BroadProjectFavorites($tree);
    }

    public function available(User $actor, ?int $companyId, ?ProjectJob $job = null): bool
    {
        if (! $this->tree->canManage($actor) || (int) $companyId !== (int) $actor->company_id) return false;
        if (! $job) return true;
        if ($job->company_id !== null && (int) $job->company_id !== (int) $actor->company_id) return false;
        if (! $actor->isSuperAdmin() && (int) $job->user_id !== (int) $actor->id
            && ! $job->coordinators()->where('users.id', $actor->id)->exists()) return false;
        $departmentUsers = fn () => User::where('company_id', $actor->company_id)->where('department_id', $actor->department_id);
        $related = $departmentUsers()->whereIn('id', array_filter([$job->user_id, $job->created_by_user_id]))->exists()
            || $job->coordinators()->where('users.company_id', $actor->company_id)->where('users.department_id', $actor->department_id)->exists()
            || $job->teamMembers()->whereIn('user_id', $departmentUsers()->select('id'))->exists()
            || $job->projectJobAssignments()->whereIn('user_id', $departmentUsers()->select('id'))->exists();
        if (! $related) return false;
        // A link managed in another department must not be silently cleared by this editor.
        return ! $job->broad_project_id || $this->tree->scoped($actor)->whereKey($job->broad_project_id)->exists();
    }

    public function attributes(User $actor, array $input, ?int $companyId, ?ProjectJob $job = null): array
    {
        if (! array_key_exists('broad_project_id', $input)) return [];
        if (! $this->available($actor, $companyId, $job)) throw new AuthorizationException;
        $data = Validator::make($input, ['broad_project_id' => ['nullable', 'integer', 'min:1']])->validate();
        $id = $data['broad_project_id'] ?? null;
        if ($id === null) return ['broad_project_id' => null];
        $node = $this->tree->scoped($actor)->with('parent')->find($id);
        if (! $node || (! $node->activePath() && (int) $job?->broad_project_id !== (int) $id)) {
            throw ValidationException::withMessages(['broad_project_id' => '同じ部署の有効な大グループまたは中グループを選んでください。']);
        }
        return ['broad_project_id' => (int) $node->id];
    }

    public function editor(User $actor, ?int $companyId, ?ProjectJob $job = null): array
    {
        if (! $this->available($actor, $companyId, $job)) return ['enabled' => false];
        $nodes = $this->tree->scoped($actor)->with('parent')->inDisplayOrder()->get()
            ->sortBy(fn ($node) => $node->displayOrderKey())
            ->filter(fn ($node) => $node->activePath() || (int) $node->id === (int) $job?->broad_project_id);
        $favoriteIds = array_fill_keys($this->favorites->ids($actor), true);
        $clientRows = collect($this->tree->rows($actor))->keyBy('id');
        $options = $nodes->map(fn ($node) => [
            'id' => $node->id, 'parent_id' => $node->parent_id, 'label' => $node->pathLabel(),
            'clients' => $clientRows->get($node->id)['display_clients'] ?? [],
            'active' => $node->activePath(), 'is_favorite' => isset($favoriteIds[$node->id]),
        ])->values()->all();
        return ['enabled' => true, 'options' => $options, 'suggestions' => $job && ! $job->broad_project_id
            ? $this->suggestions($job->title ?? '', $nodes->all()) : []];
    }

    public function label(User $actor, ?int $companyId, ProjectJob $job): ?string
    {
        if (! $job->broad_project_id || ! $this->available($actor, $companyId, $job)) return null;
        return $this->tree->scoped($actor)->with('parent')->find($job->broad_project_id)?->pathLabel();
    }

    /** Generic series words only. Suggestions never set or save a link. */
    public function suggestions(string $title, array $nodes): array
    {
        $normalize = fn ($text) => mb_strtolower(preg_replace('/[\s\x{3000}]+/u', '', mb_convert_kana($text, 'asKV', 'UTF-8')));
        $title = $normalize($title);
        if ($title === '') return [];
        $aliases = [
            'r01' => ['公開模試', '育成テスト', 'mft', '合格力', '思考力', '全国テスト', '記述力', '講習テスト', '家庭学習確認', '難関チャレンジ', '難問マスターズ', '受験票', 'アンサーガイド'],
            'r02' => ['本科テキスト', '予科', 'ユーリカ', '科学者講座', '前期テキスト', '後期テキスト'],
            'r03' => ['日特', '学校別研究', '適性検査', '合格力tp', '知識獲得'],
            'r04' => ['中学入試問題集'], 'r05' => ['中学入試データベース'],
            'r06' => ['進学レーダー'], 'r07' => ['netty', 'かわら版'], 'r08' => ['私学ブック'],
            'r10' => ['オークション', 'ワインカタログ', '美術カタログ'], 'r11' => ['fp教材', 'fp2級', 'fp3級'],
            'r12' => ['学校案内', '入試要項', '学生便覧'], 'r13' => ['募集案内', '講習案内', '教室案内'],
            'r14' => ['牛角', 'フレッシュネス'], 'r15' => ['取扱説明書', '三和シャッター'],
            'r17' => ['展示物', '博物館'], 'r18' => ['ノベルティ', 'グッズ'], 'r19' => ['カレンダー'],
            'r20' => ['名刺', '事務印刷'], 'r21' => ['動画', '映像'],
            'r22' => ['lumi-co', '共立メンテナンス'], 'r23' => ['sbサイト', '社内システム'],
        ];
        $candidates = [];
        foreach ($nodes as $node) {
            if (! $node->activePath()) continue;
            if ($node->fiscal_year && ! str_contains($title, (string) $node->fiscal_year)) continue;
            $root = $node->parent_id ? $node->parent : $node;
            if (! $root) continue;
            $key = substr($root->import_key ?? '', -3);
            $words = array_merge([$root->name], $aliases[$key] ?? []);
            $matched = null;
            foreach ($words as $word) {
                if (mb_strlen($normalize($word)) >= 2 && str_contains($title, $normalize($word))) { $matched = $word; break; }
            }
            if (! $matched) continue;
            $score = mb_strlen($normalize($matched));
            if ($node->parent_id) {
                $childWords = preg_split('/[・\/／]/u', $node->name);
                $childMatch = false;
                foreach ($childWords as $word) {
                    if (mb_strlen($normalize($word)) >= 2 && str_contains($title, $normalize($word))) { $childMatch = true; break; }
                }
                if (! $childMatch) continue;
                $score += 30;
            }
            $candidates[] = ['id' => $node->id, 'label' => $node->pathLabel(), 'reason' => '案件名の「'.$matched.'」からの候補', 'score' => $score];
        }
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_map(fn ($candidate) => array_diff_key($candidate, ['score' => true]), array_slice($candidates, 0, 3));
    }
}
