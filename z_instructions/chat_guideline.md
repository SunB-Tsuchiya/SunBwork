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
1. ChatMessageモデル・マイグレーション作成
2. ChatController作成（送信・取得API）
3. チャットイベント（ChatMessageSent）作成
4. Reverb設定・BroadcastServiceProvider調整
5. ルート（routes/chat.php）追加
6. Vueチャット画面（Chat/Index.vue等）作成
7. Broadcast/リアルタイム受信のVue実装
8. 認証・認可（Gate/Policy）設計
9. テスト・セキュリティ確認
10. AIチャットボット連携（ChatGPT API）

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
