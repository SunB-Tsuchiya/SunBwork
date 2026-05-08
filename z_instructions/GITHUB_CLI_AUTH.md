# GitHub CLI 認証手順

他のデバイスでも同じ操作が可能です。以下の手順で認証してください。

---

## 前提条件

GitHub CLI (`gh`) がインストールされていること。

インストールされていない場合：
```bash
# Ubuntu / Debian (WSL含む)
sudo apt install gh

# macOS
brew install gh
```

---

## 認証手順

### 1. ログインコマンドを実行

```bash
gh auth login
```

### 2. 対話形式で以下を選択

| 質問 | 選択 |
|------|------|
| Where do you use GitHub? | `GitHub.com` |
| What is your preferred protocol? | `HTTPS` |
| Authenticate Git with your GitHub credentials? | `Yes` |
| How would you like to authenticate? | `Login with a web browser` |

### 3. ブラウザで認証

1. ターミナルにワンタイムコード（例：`ABCD-1234`）が表示される
2. ブラウザで `https://github.com/login/device` を開く
3. コードを入力して **Continue**
4. **Authorize GitHub CLI** をクリック
5. ターミナルに `Logged in as [ユーザー名]` と表示されれば完了

---

## 認証確認

```bash
gh auth status
```

---

## よく使う操作例

### リポジトリを private に変更する

```bash
gh repo edit SunB-Tsuchiya/SunBwork --visibility private
```

### リポジトリを public に戻す

```bash
gh repo edit SunB-Tsuchiya/SunBwork --visibility public
```

### ログアウト

```bash
gh auth logout
```

---

## 注意事項

- 認証情報はデバイスごとに保存されるため、デバイスが変わるたびに `gh auth login` が必要
- `GH_TOKEN` 環境変数に Personal Access Token をセットする方法でも認証可能（CI/CD 環境向け）
