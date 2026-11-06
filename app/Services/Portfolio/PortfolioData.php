<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioProfile;
use App\Models\Portfolio\PortfolioTag;
use App\Models\User;

/** 管理画面・閲覧画面に渡す、本人分のデータ一式 */
class PortfolioData
{
    public static function for(User $user): array
    {
        $profile = PortfolioProfile::firstOrNew(['user_id' => $user->id]);

        $items = PortfolioItem::where('user_id', $user->id)
            ->with('tags:id,name')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (PortfolioItem $i) => self::item($i))
            ->values();

        return [
            'profile' => [
                'accent_color' => $profile->accent_color ?: PortfolioProfile::DEFAULT_ACCENT,
                'display_name' => $profile->display_name,
                'headline' => $profile->headline,
                'default_name' => $user->name,
            ],
            'palette' => PortfolioProfile::ACCENT_PALETTE,
            'collections' => PortfolioCollection::where('user_id', $user->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'name', 'description', 'cover_item_id', 'sort_order']),
            'items' => $items,
            'tags' => PortfolioTag::where('user_id', $user->id)->orderBy('name')->pluck('name')->values(),
            'usage' => [
                'used_bytes' => PortfolioStorage::usedBytes($user->id),
                'quota_bytes' => PortfolioStorage::quota(),
            ],
        ];
    }

    public static function item(PortfolioItem $i): array
    {
        return [
            'id' => $i->id,
            'type' => $i->type,
            'title' => $i->title,
            'description' => $i->description,
            'collection_id' => $i->collection_id,
            'tags' => $i->tags->pluck('name')->values(),
            'status' => $i->status,
            'size_bytes' => $i->size_bytes,
            'width' => $i->width,
            'height' => $i->height,
            'page_count' => $i->page_count,
            'sort_order' => $i->sort_order,
            // URL はクライアントが Ziggy の route('portfolio.api.files') で組み立てる
            // （サーバー側で相対 URL を作ると、さくらの /members ベースパスが落ちるため）
            'has_thumb' => (bool) $i->thumb_path,
            'has_file' => (bool) $i->file_path,
            'has_poster' => (bool) $i->poster_path,
            'duration_sec' => $i->duration_sec,
        ];
    }
}
