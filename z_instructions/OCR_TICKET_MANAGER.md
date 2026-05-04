# 伝票OCR機能 設計・作業管理書

作成日: 2026-05-03  
ステータス: 未着手

---

## ■ この管理書の使い方

**ユーザーへ:**
- 「OCR-01を始めましょう」などと Claude に伝えてください
- Claude は以下の作業フローに従って安全に進めます

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（OCR_TICKET_MANAGER.md）を読む
2. `CLAUDE.md` を読む（プロジェクト全体ルール）
3. 進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 実装前に関連ファイルを必ず読んでから着手すること

---

## ■ 機能概要

`/prepress/tickets/create` の伝票登録フォームに **伝票画像OCR自動入力機能** を追加する。

### ユーザーフロー
1. ユーザーがPDF/画像をドロップまたは選択
2. **ファイルが自動的に一時アップロードされる**（保存前）
3. サーバー側でJPG変換 → EasyOCR解析（Python）
4. **OCRモーダルが開く**：
   - 変換されたJPGサムネイル表示
   - 伝票番号・クライアント名・品目名がインプット枠に入力済み
   - クライアント名のDB照合結果を表示（一致 / 候補複数 / 未登録）
5. ユーザーが値を修正または確認して「この内容で反映」ボタン
6. メインフォームへ値が流し込まれる（保存はメインフォームのsubmitで行う）

---

## ■ システムアーキテクチャ

```
[ブラウザ]
 ↓ ① ファイル選択（handleFileSelect で変更をフック）
 ↓ ② FormData でPOST /prepress/ocr/analyze（multipart）

[Laravel: TicketOcrController@analyze]
 ↓ ③ PrepressImageService::convertAndStore() でJPG変換・一時保存
 ↓ ④ OcrSpaceService::analyze(imagePath) を呼び出し

[PHP: OcrSpaceService]
 ↓ ⑤ ocr.space API に HTTPS POST（multipart で画像送信）
 ↓ ⑥ JSON レスポンスからテキスト抽出・正規表現でフィールド解析

[TicketOcrController]
 ↓ ⑦ クライアント名でDB検索（部分一致・曖昧検索）
 ↓ ⑧ JSON レスポンスを返す

[ブラウザ: OcrModal.vue]
 ↓ ⑨ モーダル表示（サムネイル + 入力枠 + クライアント照合結果）
 ↓ ⑩ 「反映」でメインフォームの各フィールドに値をセット
 ↓ ⑪ 通常のフォーム submit へ（既存フロー）
```

---

## ■ OCR 技術選定

| 項目 | 内容 |
|------|------|
| サービス | **ocr.space API**（クラウドOCR） |
| 言語 | 日本語（`language=jpn`・`OCREngine=2`） |
| 動作方式 | PHP から HTTPS POST（multipart）で画像送信 |
| 無料枠 | **月25,000回**（社内利用では十分） |
| 選定理由 | さくら共有サーバーでPython/Tesseractが利用不可のため。Pythonスクリプト不要 |
| 環境変数 | `OCR_SPACE_API_KEY` を `.env` に設定 |

> **変更経緯:** EasyOCR（PyTorch ~2GB）はさくら共有サーバーのメモリ制限でインストール不可、Tesseractも未導入。ocr.space APIが最も現実的。

---

## ■ OCR クロップ領域定義

伝票は以下の固定書式（A4縦）。PDFは `PrepressImageService` が200DPI→1800px幅のJPGに変換済み。  
スマホ撮影画像は全体OCR＋正規表現でフォールバック。

### 対象フィールドとクロップ率（画像幅・高さに対するパーセンテージ）

```
┌────────────────────────────────────────────────┐
│ 株式会社■   御中   指示書（企画）               │ ← ヘッダー行（y=0〜3%）
├────────────────────────────────────────────────┤
│ 受注番号 │ 黄:受注番号値 │ 得意先│05660│ 緑:クライアント名 │ 出力日時 │
│          ↑ jobcode_box         ↑ client_box                        │ ← y=3%〜6.5%
├────────────────────────────────────────────────┤
│ 品名     │ 青:品目名ここまでたくさん文字が入ります   │ 担当者   │
│          ↑ title_box                                               │ ← y=6.5%〜10%
├────────────────────────────────────────────────┤
│ （以下、仕様・製造情報 etc）                     │
```

| フィールド   | x1% | y1% | x2% | y2% | 後処理 |
|-------------|-----|-----|-----|-----|--------|
| `jobcode`   | 3   | 3   | 18  | 6.5 | 数字のみ抽出（`re.sub(r'[^0-9]', '', text)`） |
| `client_name` | 20 | 3  | 68  | 6.5 | 前後空白トリム |
| `title`     | 3   | 6.5 | 68  | 10  | 前後空白トリム |

> **注意:** 座標は初期値。実際の結果を見て `scripts/ocr/ocr_ticket.py` の `REGIONS` 定数で調整する。  
> スマホ撮影画像（PDF以外）には `is_photo` フラグを立て、全画像OCR＋正規表現フォールバックを使う。

---

## ■ 変更・作成ファイル一覧

### 新規作成ファイル

| # | ファイルパス | 内容 |
|---|-------------|------|
| N-01 | `app/Services/OcrSpaceService.php` | ocr.space API呼び出し・結果パース・クライアントDB照合 |
| N-02 | `app/Http/Controllers/Prepress/TicketOcrController.php` | OCR APIエンドポイント |
| N-03 | `resources/js/Components/Prepress/OcrModal.vue` | OCR結果モーダル |
| N-04 | `database/migrations/xxxx_add_client_id_to_prepress_tickets_table.php` | client_id カラム追加 |

### 修正ファイル

| # | ファイルパス | 変更内容 |
|---|-------------|---------|
| M-01 | `routes/web.php` | `POST prepress/ocr/analyze` ルート追加 |
| M-02 | `app/Http/Controllers/Prepress/TicketController.php` | `store()` に `client_id` を受け取る処理追加 |
| M-03 | `app/Models/PrepressTicket.php` | `client_id` を `$fillable` に追加 |
| M-04 | `resources/js/Pages/Prepress/Tickets/Create.vue` | OCRトリガー・モーダル呼び出し追加 |

---

## ■ 作業項目（タスク一覧）

### フェーズ1: バックエンド基盤

#### OCR-01: DBマイグレーション（`client_id`カラム追加）
**ファイル:** N-06, M-03  
**内容:**
- `prepress_tickets` テーブルに `client_id UNSIGNED BIGINT NULL FK→clients(id)` を追加
- `PrepressTicket::$fillable` に `'client_id'` を追加

```sql
-- マイグレーションのup()
$table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
```

---

#### OCR-02: Pythonスクリプト作成（`scripts/ocr/ocr_ticket.py`）
**ファイル:** N-01, N-02  
**内容:**

```python
#!/usr/bin/env python3
# scripts/ocr/ocr_ticket.py
# 使用方法: python ocr_ticket.py <image_path> [pdf|photo]
# 出力: JSON {"jobcode":..., "client_name":..., "title":..., "confidence":...}

import sys, json, re, os

# EasyOCR のインポート（初回はモデルDL）
import easyocr
from PIL import Image
import numpy as np

# ── クロップ領域定義（固定書式PDF用、パーセンテージ）──────────────────
REGIONS = {
    'jobcode':     (0.03, 0.030, 0.18, 0.065),  # 受注番号（黄）
    'client_name': (0.20, 0.030, 0.68, 0.065),  # クライアント名（緑）
    'title':       (0.03, 0.065, 0.68, 0.100),  # 品目名（青）
}

def crop(img_np, x1p, y1p, x2p, y2p):
    h, w = img_np.shape[:2]
    return img_np[int(h*y1p):int(h*y2p), int(w*x1p):int(w*x2p)]

def ocr_region(reader, img_np, region_key):
    region = crop(img_np, *REGIONS[region_key])
    results = reader.readtext(region, detail=1, paragraph=True)
    texts = [r[1] for r in results]
    confs = [r[2] for r in results if len(r) > 2]
    text = ' '.join(texts).strip()
    avg_conf = sum(confs) / len(confs) if confs else 0.0
    return text, avg_conf

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'no image path given'}))
        sys.exit(1)

    image_path = sys.argv[1]
    mode = sys.argv[2] if len(sys.argv) > 2 else 'pdf'  # pdf or photo

    if not os.path.exists(image_path):
        print(json.dumps({'error': f'file not found: {image_path}'}))
        sys.exit(1)

    img = Image.open(image_path).convert('RGB')
    img_np = np.array(img)

    reader = easyocr.Reader(['ja', 'en'], gpu=False, verbose=False)

    result = {}
    confs = []

    if mode == 'pdf':
        # 固定座標クロップOCR（高精度）
        for key in ['jobcode', 'client_name', 'title']:
            text, conf = ocr_region(reader, img_np, key)
            result[key] = text
            confs.append(conf)

        # 受注番号は数字のみ
        result['jobcode'] = re.sub(r'[^0-9]', '', result['jobcode'])

    else:
        # スマホ撮影フォールバック: 全画像OCR後に正規表現で抽出
        all_results = reader.readtext(img_np, detail=0, paragraph=True)
        full_text = '\n'.join(all_results)

        # 受注番号: 10桁前後の数字列
        m = re.search(r'\b(\d{8,12})\b', full_text)
        result['jobcode'] = m.group(1) if m else ''

        # クライアント名・品目名: 全文テキストから1〜2行目・3行目を推定
        lines = [l.strip() for l in full_text.split('\n') if l.strip()]
        result['client_name'] = lines[0] if len(lines) > 0 else ''
        result['title'] = lines[1] if len(lines) > 1 else ''

    result['confidence'] = round(sum(confs) / len(confs), 3) if confs else 0.0
    result['mode'] = mode

    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
```

**requirements.txt:**
```
easyocr>=1.7.0
Pillow>=10.0.0
numpy>=1.24.0
```

**さくらサーバーでのセットアップ:**
```bash
cd ~/www/members   # ドキュメントルート
pip3 install --user easyocr Pillow numpy
# 初回モデルDL確認
python3 scripts/ocr/ocr_ticket.py scripts/ocr/test_sample.jpg pdf
```

---

#### OCR-03: PHPサービス作成（`TicketOcrService`）
**ファイル:** N-03  
**内容:**

```php
<?php
namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TicketOcrService
{
    // さくら本番 / Docker コンテナ両対応で python3 を使用
    private string $pythonBin;
    private string $scriptPath;

    public function __construct()
    {
        $this->pythonBin  = config('app.ocr_python_bin', 'python3');
        $this->scriptPath = base_path('scripts/ocr/ocr_ticket.py');
    }

    /**
     * 画像パス（Storage::disk('public') 相対）を受け取り OCR 結果を返す。
     * @param string $storagePath  例: prepress/jobticker/uuid.jpg
     * @param bool   $isPhoto      スマホ撮影ならtrue（フォールバックモード）
     * @return array{jobcode:string, client_name:string, title:string, confidence:float, matched_clients:array}
     */
    public function analyze(string $storagePath, bool $isPhoto = false): array
    {
        $absPath = Storage::disk('public')->path($storagePath);
        $mode    = $isPhoto ? 'photo' : 'pdf';

        // セキュリティ: パスのサニタイズ（ディレクトリトラバーサル防止）
        $absPath = realpath($absPath);
        $baseDir = realpath(Storage::disk('public')->path(''));
        if (!$absPath || !str_starts_with($absPath, $baseDir)) {
            Log::warning('TicketOcrService: invalid path rejected', ['path' => $storagePath]);
            return $this->emptyResult();
        }

        $cmd    = escapeshellcmd($this->pythonBin)
                . ' ' . escapeshellarg($this->scriptPath)
                . ' ' . escapeshellarg($absPath)
                . ' ' . escapeshellarg($mode)
                . ' 2>/dev/null';

        $output = shell_exec($cmd);

        if (!$output) {
            Log::warning('TicketOcrService: Python returned no output', ['path' => $storagePath]);
            return $this->emptyResult();
        }

        $data = json_decode($output, true);
        if (!$data || isset($data['error'])) {
            Log::warning('TicketOcrService: OCR error', ['output' => $output]);
            return $this->emptyResult();
        }

        // クライアント名でDB照合
        $data['matched_clients'] = $this->searchClients($data['client_name'] ?? '');

        return $data;
    }

    /**
     * クライアント名でDB部分一致検索（最大5件）
     */
    public function searchClients(string $name): array
    {
        if (strlen(trim($name)) < 2) return [];

        return Client::where('name', 'like', '%' . $name . '%')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'is_dormant'])
            ->toArray();
    }

    private function emptyResult(): array
    {
        return [
            'jobcode'         => '',
            'client_name'     => '',
            'title'           => '',
            'confidence'      => 0.0,
            'matched_clients' => [],
        ];
    }
}
```

---

#### OCR-04: OCR APIコントローラ作成（`TicketOcrController`）
**ファイル:** N-04  
**内容:**
- `POST /prepress/ocr/analyze` を受け付ける
- ファイルをJPGに変換（PrepressImageService 流用）
- TicketOcrService で解析
- OCR結果＋クライアント照合をJSONで返す
- 一時ファイルパスもレスポンスに含める（後でstoreで使うため）

---

#### OCR-05: ルート追加
**ファイル:** M-01  
**内容:**
```php
// routes/web.php の prepress グループ内に追加
Route::post('ocr/analyze', [\App\Http\Controllers\Prepress\TicketOcrController::class, 'analyze'])
    ->name('prepress.ocr.analyze');
```

---

### フェーズ2: フロントエンド

#### OCR-06: OcrModal.vue 作成
**ファイル:** N-05  
**内容・UI設計:**

```
┌─────────────────────────────────────────────────────────────┐
│ 🔍 伝票OCR読み取り結果                               [×]    │
├─────────────────────────────────────────────────────────────┤
│ [サムネイル画像]           伝票番号                         │
│ 変換後のJPGを表示          ┌──────────────────┐            │
│ （クリックで拡大）         │ 0000099999       │            │
│                           └──────────────────┘            │
│                           品目名                           │
│                           ┌──────────────────────────────┐ │
│                           │ 品目名ここまでたくさん文字... │ │
│                           └──────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ クライアント名（OCR読み取り: 「クライアント名あああ...」）   │
│                                                             │
│ ┌─ DBに一致するクライアントが見つかりました ──────────────┐ │
│ │ ○ クライアント名あああああ (ID:123) [選択]             │ │
│ │ ○ クライアント名あ株式会社 (ID:124) [選択]             │ │
│ └────────────────────────────────────────────────────────┘ │
│                                                             │
│ ┌─ 見つからない場合 ─────────────────────────────────────┐ │
│ │ [ID検索] [名前で検索（既存に紐づけ）] [新規登録]       │ │
│ └────────────────────────────────────────────────────────┘ │
│                                                             │
│ 精度スコア: ██████████░░ 83%                                │
├─────────────────────────────────────────────────────────────┤
│           [キャンセル]  [この内容でフォームに反映]           │
└─────────────────────────────────────────────────────────────┘
```

**クライアント紐づけロジック:**
1. `matched_clients.length >= 1` → 候補リストを表示・ラジオボタンで選択
2. `matched_clients.length === 0` → 「未登録」メッセージ＋以下の選択肢：
   - **既存に紐づけ**: Create.vue の既存ID/名前検索UI（コンポーネント化して流用）
   - **新規登録**: 別モーダル（ClientCreateForm.vue）を開く

**新規クライアント登録サブモーダル:**
- `leader/clients/create` のフォームを `ClientCreateForm.vue` コンポーネントとして抽出
- OcrModal内でインライン表示
- 登録完了後に `client_id` / `client_name` を自動反映

**「フォームに反映」で渡す値:**
- `jobcode` → `form.jobcode`
- `title` → `form.title`
- `client_id` → `form.client_id`
- `client_name` → `form.client_name`
- `tmpImagePath` → `form.tmp_ocr_image_path`（サーバーで保存済みJPGを使い回す）

---

#### OCR-07: Create.vue 修正
**ファイル:** M-04  
**変更点:**
1. `handleFileSelect()` にOCR解析トリガーを追加
2. OCR処理中は「解析中...」スピナーを表示
3. OCRモーダル（OcrModal.vue）の import・呼び出し追加
4. `form` に `tmp_ocr_image_path` を追加

**変更イメージ:**
```js
async function handleFileSelect(file) {
    // 既存のプレビュー処理（変更なし）
    ...

    // OCR解析（新規追加）
    if (file && (file.type === 'application/pdf' || file.type.startsWith('image/'))) {
        await triggerOcr(file);
    }
}

async function triggerOcr(file) {
    isOcrLoading.value = true;
    const fd = new FormData();
    fd.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const res = await axios.post(route('prepress.ocr.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf }
        });
        ocrResult.value = res.data;
        showOcrModal.value = true;
    } catch (e) {
        // OCR失敗時は無視してフォームをそのまま使う
    } finally {
        isOcrLoading.value = false;
    }
}
```

---

#### OCR-08: TicketController.php 修正（`client_id`保存）
**ファイル:** M-02  
**変更点:**
- `store()` のバリデーションに `client_id` を追加（既にあるか確認してから）
- `tmp_ocr_image_path` を受け取り、新規アップロードの代わりに使えるよう拡張

---

### フェーズ3: さくらサーバーデプロイ対応

#### OCR-09: Sakura サーバー EasyOCR セットアップ手順書作成
**内容:**
- Python3 パスの確認（`which python3`）
- pip インストール手順
- `APP_OCR_PYTHON_BIN` 環境変数の `.env` への追加
- 初回モデル DL の実行（`php artisan tinker` 経由でも可）
- メモリ制限の確認・対応（共有サーバーはメモリ制限があるため）

---

## ■ 進捗一覧

| タスク | ステータス | 完了日 |
|--------|------------|--------|
| OCR-01: DBマイグレーション（client_id追加） | 未着手 | - |
| OCR-02: Pythonスクリプト作成 | 未着手 | - |
| OCR-03: TicketOcrService 作成 | 未着手 | - |
| OCR-04: TicketOcrController 作成 | 未着手 | - |
| OCR-05: ルート追加 | 未着手 | - |
| OCR-06: OcrModal.vue 作成 | 未着手 | - |
| OCR-07: Create.vue 修正（OCRトリガー） | 未着手 | - |
| OCR-08: TicketController 修正（client_id保存） | 未着手 | - |
| OCR-09: さくらサーバーセットアップ手順 | 未着手 | - |

---

## ■ セキュリティ注意事項

1. **パス検証**: `TicketOcrService` でRealPath + ベースディレクトリチェックを必ず行う（ディレクトリトラバーサル防止）
2. **shell_exec の引数**: `escapeshellarg()` で必ずエスケープ
3. **一時ファイル**: OCR用の一時変換ファイルは `prepress/jobticker/tmp/` に保存し、24時間後に `artisan schedule` で自動削除
4. **ファイル種別検証**: アップロードファイルのMIMEチェックは既存の `PrepressImageService` のバリデーションを通す

---

## ■ さくらサーバー固有の注意点

| 項目 | 対応 |
|------|------|
| Python パス | `/usr/local/bin/python3` が一般的。`which python3` で確認 |
| メモリ制限 | 共有サーバーはプロセスメモリが制限される場合あり。EasyOCR は~600MBのメモリを使用 |
| モデルキャッシュ | `~/.EasyOCR/` に保存。初回のみ外部アクセス必要 |
| 実行タイムアウト | `max_execution_time` を `php.ini` で確認（OCR処理は5〜30秒かかる場合あり） |
| PHP `shell_exec` | さくらの共有プランでは `shell_exec` が無効になっている場合があるため、**事前に確認必須** |

> **確認コマンド:** `php -r "echo shell_exec('echo ok');"` でOKが返ればOK

---

## ■ OCR精度チューニングガイド

クロップ座標が合わない場合の調整手順:

1. 変換済みJPGを確認: `storage/app/public/prepress/jobticker/` の最新ファイル
2. 実際の画像サイズを確認: `python3 -c "from PIL import Image; img=Image.open('xxx.jpg'); print(img.size)"`
3. 各フィールドのピクセル座標を計測（GIMPやPhotoshopで確認）
4. `ocr_ticket.py` の `REGIONS` 定数をパーセンテージで更新

### よくある問題と対処

| 問題 | 原因 | 対処 |
|------|------|------|
| 受注番号に余分な文字が入る | クロップ範囲が広い | `x2` を小さくする |
| クライアント名が空になる | `x1` が「得意先」ラベルにかかっている | `x1` を大きくする |
| 品目名が途切れる | `x2` が小さい | `x2` を `0.80` まで広げる |
| スマホ撮影で全文が取れない | 傾き・暗さ | Pillow で前処理（グレースケール化・コントラスト強調）を追加 |

---

## ■ 将来の改善候補（今回は対象外）

- [ ] 画像の傾き自動補正（OpenCV の`getRotationMatrix2D`）
- [ ] 受注番号のバーコード/QRコード読み取り（`pyzbar`）
- [ ] OCR結果の学習・精度改善ループ（ユーザー修正をフィードバック）
- [ ] 非同期ジョブ化（Laravel Queue + Python）でUI応答を高速化

---

## ■ 関連ドキュメント

| ファイル | 内容 |
|---------|------|
| `z_instructions/PREPRESS_MANAGER.md` | Prepress エリア全体管理書 |
| `z_instructions/PREPRESS_PLAN.md` | Prepress 詳細仕様 |
| `z_instructions/CONSOLIDATED_08_attachment.md` | 添付ファイル設計 |
| `CLAUDE.md` | プロジェクト全体ルール |
| `app/Services/PrepressImageService.php` | 画像変換サービス（流用元） |
| `resources/js/Pages/Prepress/Tickets/Create.vue` | 修正対象フォーム |
| `resources/js/Pages/Clients/Create.vue` | クライアント新規作成フォーム（流用元） |
