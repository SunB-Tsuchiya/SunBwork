## Ziggy（Laravel+Vue+Inertia）でのルート指定について
- このプロジェクトはLaravel+Vue+Inertia構成でZiggyを利用しています。
- Ziggyでルートパラメータが必要な場合は、必ずパラメータ名を明示してオブジェクト形式で渡すこと。
  - 例: `route('coordinator.project_jobs.show', { projectJob: job.id })`
  - 間違った例: `route('coordinator.project_jobs.show', job.id)`（←パラメータ名がないとエラーになる）
- 削除や編集なども同様に、`route('coordinator.project_jobs.edit', { projectJob: job.id })` のように書く。
- ルートパラメータ名はLaravel側のweb.phpやroute:listで確認し、必ず一致させること。

### 推奨書式（Vueファイル内）
```js
// 正しい例
route('coordinator.project_jobs.show', { projectJob: job.id })
route('coordinator.project_jobs.edit', { projectJob: job.id })
router.delete(route('coordinator.project_jobs.destroy', { projectJob: job.id }))
```

// 間違った例
// route('coordinator.project_jobs.show', job.id) // ←NG
```
# AIエージェント向けコーディングルール：エラーハンドリング・バリデーション


## バリデーション・DB接続時のエラーハンドリング
- バリデーションエラー時は、何がエラーかをユーザーに明示的に伝えること。
  - InertiaやAPIレスポンスでエラー内容を返し、Vue等のフロント側でフォームごとにエラー表示やalertを行う。
- バリデーションエラー時は、ユーザーが入力した値を消さずにそのままフォームに残すこと（入力値の保持）。
- データベース接続やクエリ実行時の例外もtry-catchで捕捉し、ユーザーに分かりやすいエラー通知を行う。
- バリデーションルールはサーバー・クライアント両方で厳密に一致させる。
- **DBへのstore, update（登録・更新）処理を行う際は、必ずtry-catchでエラーハンドリングを実装し、catch節でユーザーに分かりやすいエラー通知・ログ記録を行うこと。ガイドライン違反は重大なバグとみなす。**

## エラー発生時のデバッグ・記録
- バリデーションやDBエラーが発生した場合、console.logやloggerでエラー内容・リクエスト内容を必ず記録する。
- エラー内容は開発者・運用者が追跡しやすいように、十分な情報を残す。
- 同じエラーが再発した場合も、必ずconsoleやlogで詳細を確認し、原因を特定すること。

## フロントエンドでのエラー通知
- バリデーションエラーはフォーム項目ごとに明示的に表示する。
- 重大なエラーや予期しない例外はalertやダイアログでユーザーに通知する。
- エラー内容は日本語で分かりやすく記載する。

---
このルールはz_instructions配下のAIエージェント向けガイドラインとして常に参照すること。
