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
