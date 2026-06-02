# WORKLOAD_UI2_PLAN1 — 作業項目設定 UI 大改修

## 概要

WorkloadSetting の Index ページにインライン編集モードを追加し、
別ページだった Edit を廃止（Index へ統合）する。

---

## 要件一覧

| # | 要件 | 優先 |
|---|------|------|
| 1 | ボタン配置を CONSOLIDATED_01 に合わせる（ヘッダー右） | 高 |
| 2 | Index に編集モード ON/OFF トグル | 高 |
| 3 | 編集モード：行ごとに ＋/－ ボタン（追加・削除） | 高 |
| 4 | 編集モード：グループ内アイテム並べ替え（ドラッグ or ▲▼） | 高 |
| 5 | 編集モード：グループ追加・名前変更・削除・並べ替え | 高 |
| 6 | 会社全体アイテムに部署バッジ（情報表示のみ） | 中 |
| 7 | 読み取りモードでも部署バッジを表示 | 中 |
| 8 | 「DTP など未登録グループが表示される」バグ修正 | 高 |
| 9 | タイプ別に保存ボタン（編集モード中のみ headerExtras） | 高 |

---

## バグ原因（#8）

`buildGroupConfig` が `null`（グループなし）を無条件追加しているため、
items に null グループがなくても「グループなし」セクションが常に表示される。

**Fix:** `fromItems` に `null` が含まれる場合のみ `groups` に追加する。

---

## アーキテクチャ変更

| 変更前 | 変更後 |
|--------|--------|
| Index = 読み取り専用一覧 | Index = 読み取り + インライン編集モード |
| Edit = 別ページ（/workload-setting/edit/{type}） | Edit = Index にリダイレクト（ルート維持） |

---

## UI 設計（新 Index）

```
AppLayout title="作業項目設定"
  #header:
    <h2>作業項目設定</h2>
    （スコープバーは headerExtras に移動 or header 直下）
  #headerExtras:
    [編集モード OFF 時] [✎ 編集] ボタン
    [編集モード ON 時]  [タイプ選択タブ] [保存] [キャンセル]
  
  default:
    部署スコープバー（既存）
    
    タイプタブ or アコーディオン
      各タイプ（Stages / Work Item Types / Sizes / ...）
        - 読み取りモード: リスト + 部署バッジ
        - 編集モード:
            グループヘッダー: [▲][▼] + グループ名 + [+行追加] [グループ名変更] [グループ削除]
            各行: [≡ドラッグ or ▲▼] [名前入力] [係数入力] [部署バッジ] [－削除]
            フッター: [+ グループを追加]
```

---

## 部署バッジ（#6 #7）

- 会社全体スコープ表示時のみ有効（部署スコープ時は不要）
- 各 work_item_type に `_usedByDepts: [{id, name}]` を付与
  → コントローラーで各アイテム名と一致する dept-scope アイテムの dept を集計
- 表示: 丸いカラーバッジ（例: `情報出版 ●`）
- 読み取りモード・編集モード両方で表示

---

## 保存の流れ

編集モード中、タイプごとに変更内容をメモリに保持。
「保存」ボタンで `router.post(workload_setting.store, { type, items, group_orders, scope })` を送信。
→ 保存後も編集モードを維持（連続編集できるように）

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/Pages/WorkloadSetting/Index.vue` | 大幅改修（編集モード追加、部署バッジ、ドラッグ並べ替え） |
| `resources/js/Pages/WorkloadSetting/Edit.vue` | Index へリダイレクト（型ごとのクエリパラメータ付き） |
| `app/Http/Controllers/WorkloadSettingController.php` | index() に部署使用情報を付与、edit() をリダイレクト化 |

routes/web.php の変更なし → Ziggy 再生成不要

---

## 並べ替え実装方針

外部ライブラリ不使用。▲▼ボタンによる入れ替え（既存 Edit.vue と同じ方式）。
将来的にドラッグ対応が必要な場合は vuedraggable を検討。

---

## 注意点

- 5タイプすべてに編集モードを適用するため Index.vue が大きくなる
- タイプごとにローカル state を持つ（5 × reactive state）
- 保存は 1タイプずつ（全タイプ一括保存は行わない）
