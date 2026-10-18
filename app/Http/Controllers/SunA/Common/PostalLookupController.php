<?php

namespace App\Http\Controllers\SunA\Common;

use App\Http\Controllers\Controller;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 郵便番号から住所（都道府県・市区町村・町域）を検索する（SunA 共通。社員情報・年末調整・受付で使う）。
 * 公開 API（zipcloud）をサーバー側から呼び、結果を 30 日キャッシュする。検索に使うのは郵便番号のみ（個人情報は送らない）。
 * 失敗しても画面は手入力で使える。
 */
class PostalLookupController extends Controller
{
    private const ENDPOINT = 'https://zipcloud.ibsnet.co.jp/api/search';

    public function lookup(Request $request)
    {
        $zip = NenchoRules::digits((string) $request->query('zip'));
        if (! $zip || strlen($zip) !== 7) {
            return response()->json(['message' => '郵便番号は7桁で入力してください。'], 422);
        }

        $key = "suna_postal_{$zip}";
        $result = Cache::get($key);
        if ($result === null) {
            $result = $this->fetch($zip);
            if ($result !== false) {
                Cache::put($key, $result ?? 'none', now()->addDays(30));   // 該当なしも覚える（'none'）。通信失敗はキャッシュしない
            }
        }
        if ($result === false) {
            return response()->json(['message' => '住所を検索できませんでした。住所を手入力してください。'], 503);
        }
        if ($result === 'none' || $result === null) {
            return response()->json(['message' => 'この郵便番号に該当する住所が見つかりませんでした。'], 404);
        }

        return response()->json($result);
    }

    /** @return array|null|false 住所 / null=該当なし / false=通信失敗 */
    private function fetch(string $zip): array|null|false
    {
        try {
            $res = Http::timeout(5)->get(self::ENDPOINT, ['zipcode' => $zip]);
            if (! $res->ok()) {
                return false;
            }
            $row = $res->json('results.0');
        } catch (\Throwable $e) {
            return false;
        }

        return $row ? $this->format($row) : null;
    }

    /** 国の様式（全角）にそろえる。町域の補足（「（…）」「以下に掲載がない場合」）は取り除く */
    private function format(array $row): array
    {
        $town = preg_replace('/（.*$/u', '', (string) ($row['address3'] ?? ''));
        $town = str_contains($town, '以下に掲載がない場合') ? '' : $town;

        return [
            'prefecture' => $row['address1'] ?? '',
            'city' => NenchoRules::zenkakuText($row['address2'] ?? ''),
            'town' => NenchoRules::zenkakuText($town),
        ];
    }
}
