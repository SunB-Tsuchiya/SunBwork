# OCR ローカル Tesseract 実装仕様・レポート

> 作成日: 2026-05-09
> 対象環境: さくらレンタルサーバー（FreeBSD 13.0、`silverlamb759.sakura.ne.jp`）

---

## 1. 背景・目的

従来の OCR 機能は外部クラウド API（**OCR.space Free プラン**）を使用していた。
重要案件の伝票画像を外部サーバーに送信することへのセキュリティ懸念があり、
**サーバー内完結 OCR** への移行を実施した。

---

## 2. さくらサーバーの環境調査結果

| 項目 | 結果 |
|---|---|
| OS | FreeBSD 13.0-RELEASE-p14 (amd64) |
| Python | 3.8.12（`/usr/local/bin/python3`） |
| pip | `python3 -m pip install --user` で利用可 |
| Node.js | **未インストール** |
| Tesseract バイナリ | **未インストール**（root権限なしで pkg インストール不可） |
| ImageMagick | 6.9.12-34（`convert`, `identify` 使用可） |
| Ghostscript | 9.52 |
| PHP imagick 拡張 | 3.7.0 |
| gcc / clang / cmake / make | インストール済み（ソースビルド可能） |
| libjpeg / libpng / libtiff | インストール済み（Tesseract 依存ライブラリ） |
| ホームディレクトリ空き容量 | 約 1.2 TB |
| /tmp 空き容量 | 約 7.3 GB |

### pip でインストール済みのパッケージ（今回追加分含む）

| パッケージ | バージョン | 備考 |
|---|---|---|
| Pillow | 10.4.0 | 今回 `pip install --user` で追加 |
| pytesseract | 0.3.13 | 今回 `pip install --user` で追加（バイナリ依存） |
| requests | 2.25.1 | 元から存在 |

> **注意:** pytesseract は Python ラッパーに過ぎず、tesseract バイナリがなければ動作しない。
> 実際には PHP から直接バイナリを `proc_open` で呼び出す方式を採用した。

---

## 3. ソースビルド手順

### 3-1. インストールプレフィックス

```
/home/silverlamb759/local/
```

root 権限不要。ホームディレクトリ以下に全てインストール。

### 3-2. Leptonica 1.82.0（Tesseract の必須依存ライブラリ）

```bash
cd ~/src
wget https://github.com/DanBloomberg/leptonica/releases/download/1.82.0/leptonica-1.82.0.tar.gz
tar xzf leptonica-1.82.0.tar.gz
cd leptonica-1.82.0 && mkdir build && cd build
cmake .. \
  -DCMAKE_INSTALL_PREFIX=/home/silverlamb759/local \
  -DCMAKE_BUILD_TYPE=Release \
  -DBUILD_SHARED_LIBS=ON
make -j2
make install
```

**注意:** cmake の `CMAKE_INSTALL_PREFIX` には `$HOME` を使わず絶対パスを指定すること。
SSH 経由では `$HOME` がローカルマシンのパスに展開される場合がある。

インストール確認:
```
/home/silverlamb759/local/lib/libleptonica.so.1.82.0
```

### 3-3. Tesseract 5.3.4

```bash
cd ~/src
wget https://github.com/tesseract-ocr/tesseract/archive/refs/tags/5.3.4.tar.gz -O tesseract-5.3.4.tar.gz
tar xzf tesseract-5.3.4.tar.gz
cd tesseract-5.3.4 && mkdir build && cd build
PKG_CONFIG_PATH=/home/silverlamb759/local/lib/pkgconfig \
cmake .. \
  -DCMAKE_INSTALL_PREFIX=/home/silverlamb759/local \
  -DCMAKE_BUILD_TYPE=Release \
  -DLeptonica_DIR=/home/silverlamb759/local/lib/cmake/leptonica \
  -DBUILD_TRAINING_TOOLS=OFF
make -j2
make install
```

インストール確認:
```
/home/silverlamb759/local/bin/tesseract
```

**動作確認コマンド:**
```bash
export LD_LIBRARY_PATH=/home/silverlamb759/local/lib
export TESSDATA_PREFIX=/home/silverlamb759/local/share/tessdata
/home/silverlamb759/local/bin/tesseract --version
# → tesseract 5.3.4 / leptonica-1.82.0
```

### 3-4. 言語訓練データ（tessdata_best）

```bash
cd /home/silverlamb759/local/share/tessdata
wget https://github.com/tesseract-ocr/tessdata_best/raw/main/jpn.traineddata
wget https://github.com/tesseract-ocr/tessdata_best/raw/main/eng.traineddata
```

| ファイル | サイズ |
|---|---|
| jpn.traineddata | 14 MB |
| eng.traineddata | 15 MB |

### 3-5. 環境変数（~/.bashrc に追記済み）

```bash
export LD_LIBRARY_PATH=/home/silverlamb759/local/lib:$LD_LIBRARY_PATH
export TESSDATA_PREFIX=/home/silverlamb759/local/share/tessdata
export PATH=/home/silverlamb759/local/bin:$PATH
```

---

## 4. Laravel 実装

### 4-1. ファイル構成

| ファイル | 変更内容 |
|---|---|
| `app/Services/OcrSpaceService.php` | `parseJobcode` / `parseClientName` / `parseTitle` / `normalizeCompanyName` を `private` → `protected` に変更（継承のため） |
| `app/Services/LocalTesseractService.php` | **新規作成**。`OcrSpaceService` を継承し `analyze()` を上書き |
| `app/Providers/AppServiceProvider.php` | `register()` に OCR ドライバ切り替えバインドを追加 |
| `config/services.php` | `tesseract` セクション追加 |

### 4-2. LocalTesseractService の動作フロー

```
1. Storage::disk('public')->path($storagePath) で絶対パス取得
2. Imagick で画像を開く
3. COMBINED_REGION（y=8.8%〜17.0%）をクロップ
4. 高さ < 160px の場合は拡大（可読性向上）
5. グレースケール化・正規化
6. PNG 形式で /tmp に一時保存
7. proc_open() で tesseract バイナリを呼び出し
   - LD_LIBRARY_PATH・TESSDATA_PREFIX を env として渡す
   - 言語: jpn+eng
8. 一時ファイルを削除
9. parseJobcode / parseClientName / parseTitle で構造化
10. searchClients でDB照合
```

### 4-3. OCR ドライバの切り替え

`.env` の `OCR_DRIVER` で制御:

```env
OCR_DRIVER=local      # さくら本番（ローカル Tesseract を使用）
OCR_DRIVER=ocr_space  # ローカル開発環境 / フォールバック
```

`AppServiceProvider::register()` でバインドを切り替えるため、
`TicketOcrController` の実装変更は不要。

### 4-4. config/services.php の tesseract セクション

```php
'tesseract' => [
    'driver'          => env('OCR_DRIVER', 'ocr_space'),
    'binary'          => env('TESSERACT_BINARY', '/usr/bin/tesseract'),
    'lib_path'        => env('TESSERACT_LIB_PATH', ''),
    'tessdata_prefix' => env('TESSDATA_PREFIX', ''),
],
```

### 4-5. さくら本番 .env の設定値

```env
OCR_DRIVER=local
TESSERACT_BINARY=/home/silverlamb759/local/bin/tesseract
TESSERACT_LIB_PATH=/home/silverlamb759/local/lib
TESSDATA_PREFIX=/home/silverlamb759/local/share/tessdata
```

---

## 5. 動作確認結果

### Tesseract バイナリ単体テスト

```bash
# テスト画像（Pillow で生成）に "OCR Test 123" を描画
export LD_LIBRARY_PATH=/home/silverlamb759/local/lib
export TESSDATA_PREFIX=/home/silverlamb759/local/share/tessdata
/home/silverlamb759/local/bin/tesseract /tmp/test_ocr.png stdout -l eng
# → "CoRTest 123"（フォントなし画像のため多少ずれるが基本動作は正常）
```

### Laravel DI 確認

```php
// tinker 相当で確認
$svc = app(App\Services\OcrSpaceService::class);
get_class($svc);
// → "App\Services\LocalTesseractService"  ✅

config('services.tesseract.driver');
// → "local"  ✅

config('services.tesseract.binary');
// → "/home/silverlamb759/local/bin/tesseract"  ✅
```

---

## 6. フォールバック戦略

Tesseract バイナリが見つからない場合（`file_exists($binary)` 失敗）、
`LocalTesseractService::analyze()` は `emptyResult()` を返してログにエラーを記録する。
その場合は `.env` の `OCR_DRIVER` を `ocr_space` に戻せば即時切り替え可能。

---

## 7. 将来の改善候補

| 課題 | 対応案 |
|---|---|
| 日本語認識精度が低い場合 | 前処理強化（二値化・ノイズ除去）または `jpn_vert` モデル追加 |
| OSD（向き検出）が必要 | `osd.traineddata` を追加ダウンロード |
| tessdata_best より速度優先 | `tessdata_fast` に切り替え（GitHub: tesseract-ocr/tessdata_fast） |
| Tesseract バージョンアップ | `~/src/tesseract-X.X.X` でビルドし直す（Leptonica は使い回し可） |
| バイナリのパーミッション確認 | `ls -lh /home/silverlamb759/local/bin/tesseract` で 755 を確認 |

---

## 8. 読み取り調整ログ（2026-05-09 チューニング記録）

### 対象伝票

DocuCentre-VII C7773 複合機でスキャンしたモノクロ JPEG を PDF 化したもの（`sample.pdf`）。
PDF 内部は DCTDecode（JPEG）埋め込み、2482×3510px、DeviceRGB。

読み取り対象フィールド:

| フィールド | 値（正解） | 種別 |
|---|---|---|
| 受注番号（jobcode） | `4505963` | 数字のみ |
| 得意先（client） | `（株）文化工房` | 日本語 |
| 品名（title） | `日本医科大学 150周年記念誌 PDF制作代` | 日本語＋数字 |

---

### 8-1. クロップ領域設計（最終確定）

```php
// 受注番号エリア（数字専用・狭め）
private const REGION_JOBCODE     = [0.080, 0.088, 0.280, 0.128];

// 得意先コードエリア（4〜6桁の数字）
// 得意先 label(x≈28-38%) の直後 → eng 言語 + psm=7（1行）で数字誤読を最小化
private const REGION_CLIENT_CODE = [0.380, 0.088, 0.570, 0.128];

// row1+row2 全幅（得意先名・品名を同時取得）
// x=0.900 まで広げないと「文化工房」が取れない（0.660 だと欠ける）
private const REGION_COMBINED    = [0.003, 0.088, 0.900, 0.170];
```

**3-region（REGION_CLIENT / REGION_TITLE 個別クロップ）は廃止。**
行1だけの薄いクロップでは Tesseract が安定して読めず、
combined 1枚で取得してパース関数で分離する方が精度が高い。

---

### 8-2. 言語設定（重要）

| クロップ | 言語設定 | 理由 |
|---|---|---|
| REGION_JOBCODE | `jpn+eng` | `eng` のみだと数字を誤読することがある |
| REGION_COMBINED | `jpn` **のみ** | `jpn+eng` にすると漢字が英字として誤認識される（例: 「文化工房」→「XIE TE」「MWXIETE」） |

**`jpn+eng` は combined に使ってはいけない。**

---

### 8-3. 画像前処理チェーン（`cropAndOcr()` 内）

```php
// 常に2倍以上に拡大（スキャン JPEG の解像度不足を補う）
$minH = 300;
if ($ch < $minH) {
    $scale = (int)ceil($minH / $ch);
    $crop->resizeImage($cw * $scale, $ch * $scale, Imagick::FILTER_LANCZOS, 1);
} else {
    $crop->resizeImage($cw * 2, $ch * 2, Imagick::FILTER_LANCZOS, 1);
}
$crop->transformImageColorspace(Imagick::COLORSPACE_GRAY);
$crop->sharpenImage(0, 1.0);   // JPEG ぼやけを補正
$crop->normalizeImage();
// 二値化: モノクロスキャンのノイズ・JPEG 圧縮アーティファクトを除去
$qr = Imagick::getQuantumRange();
$crop->thresholdImage(intval($qr['quantumRangeLong'] * 0.5));
$crop->setImageFormat('png');
```

この前処理順序で認識精度が大幅に向上した（特に二値化が効果的）。

---

### 8-4. 得意先（クライアント名）の抽出ロジック

#### `parseClientNameTesseract()`

combined_raw の中から 7桁以上の数字を含む行（= jobcode 行）を見つけ、
その行から数字を除いた残りをクライアント名とする。

```
combined_raw 例:
  受注 状 号 ... 得意 先 05560
  4505963 物 文 化工 房 出力 日 時 :2026...   ← jobcode行
  了 、 。 押 当 者 ーー                        ← 行間ノイズ
  日 本 医科 大 学 150 周 年...               ← 品名行
```

jobcode 行の `4505963` を除去すると `物 文化工房` が残る。

#### `searchClientsSliding()`（スライディングDB検索）

OCR ノイズで先頭に余分な文字が付く（例: `物文化工房`）ため、
マッチしない場合は先頭を 1〜3 文字ずつ削って再検索する。

```
「物文化工房」→ DB検索: ヒットなし
「文化工房」  → DB検索: ヒット ✅
```

#### `cleanClientText()`

CJK 字間スペース除去（Tesseract が「文 化工 房」と出力するのを「文化工房」に修正）:

```php
preg_replace(
    '/(?<=[\x{3040}-\x{9fff}\x{f900}-\x{faff}])\s+(?=[\x{3040}-\x{9fff}\x{f900}-\x{faff}])/u',
    '',
    $text
);
```

---

### 8-5. 品名（title）の抽出ロジック

#### `parseTitleTesseract()` — 優先と fallback

**優先:** `品[名目]` ラベルの後に続くテキストを取得。

**fallback:** combined_raw に「品名」ラベルが含まれない場合（幅広クロップで混入する他フィールドが邪魔するとき）、
jobcode 行以降の行のうち **CJK 文字を最も多く含む行** を品名として採用。

これにより行間ノイズ行（`了、。押当者ーー`、CJK 文字 4個）よりも
実際の品名行（`日本医科大学 150周年記念誌...`、CJK 文字 多数）が正しく選ばれる。

#### `cleanTitle()` — クリーニング順序

```
1. 先頭の記号ノイズ（| l ｜ 等）を除去
2. 「品名」「品目」ラベルを先頭から除去
3. CJK 字間スペースを除去（「日 本 医科 大 学」→「日本医科大学」）
4. 「ーー」以降（他フィールドの混入）を除去
5. アンダースコアをスペースに置換（_PDF → PDF）
6. 複数スペースを1つに正規化
7. 末尾の記号ノイズを除去
```

**注意:** `ーー`（長音符2つ）が品名行に付いていることがある（例: `PDF制作代 ーー 大和田両`）。
step 4 で `ーー` 以降をすべて削除することで正しい品名が得られる。
ただし step 3 の CJK 字間スペース除去後は `代ーー` のようにくっつくため、
`\s*[ーｰ\-]{2,}[\s\S]*` パターンで確実に削除できる。

---

### 8-6. 実際のログと結果

**combined_raw（実際の Tesseract 出力）:**
```
受注 状 号 上 語 語 間 症 凍 得意 先 05560
4505963 物 文 化工 房 出力 日 時 :2026 年 03 月 06
了 、 。 押 当 者 ーー
日 本 医科 大 学 150 周 年 記念 誌 _PDF 制作 代 ーー 大 和田 両
```

**パース結果（最終）:**

| フィールド | 結果 | 備考 |
|---|---|---|
| jobcode | `4505963` | 正常 |
| client_name | `物文化工房` | OCR ノイズ（先頭「物」）あり → sliding で「文化工房」DB ヒット |
| title | `日本医科大学 150 周年記念誌 PDF 制作代` | cleanTitle で正規化済み |
| db_hits | 1 | 「文化工房」でヒット |

---

### 8-7. 試行錯誤の記録（失敗パターン）

| 試した設定 | 結果 | 原因・備考 |
|---|---|---|
| 3-region（REGION_CLIENT y=0.088-0.128、REGION_TITLE y=0.120-0.170） | client_raw が空、title が欠ける | 薄い1行クロップは Tesseract に向かない |
| combined に `jpn+eng` | 「文化工房」→「XIE TE」「MWXIETE」 | 漢字が英字として誤認識される |
| REGION_COMBINED x=0.003-0.660 | 「文化工房」が取れない | 得意先エリアが x≈50-75% にあるため幅不足 |
| fallback でjobcode行以降の全行を結合 | 品名が「了、。押当者」になる | 行間ノイズ行の「ーー」で cleanTitle が切断 |
| `parseTitleTesseract` fallback: CJK文字最多行を採用 | 正常取得 ✅ | 現在の実装 |

---

## 9. 変更ログ

### 2026-05-21: 得意先コード（client_code）DB照合機能追加

**背景:**
- `clients` テーブルに `client_code`（ユーザー定義のユニーク識別子）カラムを追加（別タスク）
- 伝票の「得意先」欄横の数字（4〜6桁）が `client_code` に対応すると判明
- 従来はクライアント名（日本語 OCR）のみで DB 照合していたため、誤読による不一致が多かった

**変更内容:**

#### `LocalTesseractService.php`

| 変更 | 内容 |
|---|---|
| 定数追加 | `REGION_CLIENT_CODE = [0.380, 0.088, 0.570, 0.128]` — 得意先コード専用クロップ領域 |
| OCR 追加 | `cropAndOcr()` を `eng` 言語 + `psm=7`（1行モード）で実行 → 数字認識精度を最大化 |
| 数字抽出 | `preg_replace('/[^0-9]/', '', ...)` で数字のみ抽出 |
| フォールバック | 4〜6桁にならない場合 → `parseClientCode($combinedRaw)` で combined テキストから抽出 |
| DB 検索順序 | ① `client_code` で完全一致 → ② ヒットなしなら `searchClientsSliding($clientName)` |
| ログ | `client_code_raw`・`code_hit` をログに追加 |
| OCR リージョン数 | 2-region（jobcode + combined）→ **3-region**（jobcode + client_code + combined）に拡張 |

#### `OcrSpaceService.php`（基底クラス）

| 変更 | 内容 |
|---|---|
| メソッド追加 | `parseClientCode(string $text): string` — OCR テキストから得意先コードを抽出 |
| メソッド追加 | `searchClientByCode(string $code): array` — `client_code` カラムで完全一致検索 |
| `searchClients()` 修正 | SELECT フィールドに `client_code` を追加（OCR モーダルでの表示用） |
| `analyze()` 修正 | コードファースト検索を採用（`client_code` → 名前の順） |

#### `OcrModal.vue`

| 変更 | 内容 |
|---|---|
| 候補リスト | `ID: {{ c.id }}` → `client_code` が存在する場合は `client_code` を表示 |

**`parseClientCode()` の動作:**
1. 優先: 「得意[　\s]先」を含む行に 4〜6 桁の数字があれば採用（LocalTesseractService の combined_raw 形式に対応）
2. フォールバック: jobcode 行（7 桁以上）以外で最初に現れる 4〜6 桁の独立数字を採用（OcrSpaceService の列読み取り形式に対応）

**REGION_CLIENT_CODE の座標根拠:**
- REGION_JOBCODE が x=0.080〜0.280（受注番号値）
- 得意先 label は x≈0.280〜0.380
- 得意先コード（4〜6桁）は x≈0.380〜0.570 と推定
- 実際の伝票でズレが生じた場合は `REGION_CLIENT_CODE` の x1/x2 を調整すること

**注意:**
- `eng` 言語 + `psm=7` は純粋な数字列の認識に最適（`jpn` は数字の誤読が多い）
- `REGION_CLIENT_CODE` がクライアント名エリアに重なると名前の一部が取れてしまうため、x2 を広げすぎないこと（目安: x2 ≤ 0.600）
