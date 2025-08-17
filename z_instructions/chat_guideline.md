# チャット機能開発ガイドライン（Laravel Reverb対応）

## 目的
- 管理者・リーダーによる全体/チーム向け通知・メッセージ配信
- Coordinator・User間のProjectJob単位チャット
- ユーザー間のフリーチャット
- ChatGPT APIを利用したAIチャットボット
- 安全性・拡張性・運用性を重視

## 技術方針
- Laravel 12 + Reverb（WebSocket）
- Pusher等外部サービスは使わず、Reverbサーバーを利用
- 認証・認可はLaravel標準機能を活用
- Vue3（Inertia）でリアルタイムUI
- メッセージはDB永続化

## ディレクトリ・構成
- app/Http/Controllers/Chat/ChatController.php（チャットAPI）
- app/Events/ChatMessageSent.php（イベント）
- app/Models/ChatMessage.php（メッセージモデル）
- routes/chat.php（チャット用ルート）
- resources/js/Pages/Chat/（Vueチャット画面）
- z_instructions/chat_guideline.md（本ガイドライン）

## 実装手順
1. ChatMessageモデル・マイグレーション作成　→【実装済み】
2. ChatController作成（送信・取得API）　→【実装済み】
3. チャットイベント（ChatMessageSent）作成　→【実装済み】
4. Reverb設定・BroadcastServiceProvider調整　→【未実装】
5. ルート（routes/chat.php）追加　→【実装済み】
6. Vueチャット画面（Chat/Index.vue等）作成　→【実装済み】
7. Broadcast/リアルタイム受信のVue実装　→【未実装】
8. 認証・認可（Gate/Policy）設計　→【未実装】
9. テスト・セキュリティ確認　→【一部実装済み】
10. AIチャットボット連携（ChatGPT API）　→【実装済み】

---

## これから実装する機能の指針・設計・操作手順

### 1. リアルタイム受信（Reverb連携）
- ChatMessageSentイベントをbroadcast（Reverb）で送信
- クライアント(Vue)でReverbチャンネルを購読し、メッセージを即時反映
- BroadcastServiceProviderでイベント登録
- .envでBROADCAST_DRIVER=reverbを指定
- Reverbサーバー起動・接続確認

#### 操作手順
1. Reverbインストール・設定
2. BroadcastServiceProviderでイベント登録
3. ChatMessageSentイベントでimplements ShouldBroadcastを追加
4. VueでEcho+Reverbを使い、チャンネル購読・受信処理を追加
5. 動作確認

### 2. 通知機能
- 新着メッセージや重要アクション時に通知（UIバッジ/トースト/ブラウザ通知）
- Laravel Notification/イベントを活用
- 通知既読管理（DB/フロント）

#### 操作手順
1. Notificationクラス作成
2. 通知テーブル・モデル作成（必要に応じて）
3. メッセージ送信時に通知発火
4. Vueで通知UI実装
5. 既読管理・バッジ表示

### 3. 権限制御（Admin機能）
- チャット閲覧・送信・管理の権限をGate/Policyで制御
- Adminは全チャット閲覧・管理、User/Coordinatorは自身の範囲のみ
- 不正アクセス時は403/エラー返却

#### 操作手順
1. Policy/Gate設計・実装
2. コントローラーで権限チェック
3. フロントでUI制御
4. テスト

### 4. 設定機能（Admin機能）
- チャット機能のON/OFF、通知方法、AI利用可否などを管理画面で設定
- 設定値はDBまたはconfigで管理

#### 操作手順
1. 設定モデル・テーブル作成
2. Admin用設定画面作成
3. 設定値をチャット・通知機能に反映
4. テスト

---

今後は上記の順で段階的に実装・チューニングを進めていきます。
## 運用・セキュリティ指針
- メッセージ内容・送信者・宛先・種別をDB記録
- 権限・宛先ごとに閲覧/投稿制御
- 不正アクセス・スパム対策
- ログ・監査証跡の保存
- 個人情報・機密情報の取り扱いに注意

## 作業段階ごとの提案・進行
- 各段階でAIが最適な設計・実装案を提案
- 仕様変更・追加要望は本ガイドラインに追記
- 作業履歴・決定事項も随時記録

---

このガイドラインはAIエージェントと開発者で共有し、チャット機能開発の指針・進行管理に活用します。
