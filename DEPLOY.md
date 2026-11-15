# OPT 手動デプロイヤー

GitHub リポジトリ `Taiju-h/opt` の `main` にある `public_html/` を SSH + rsync でサーバーの公開ディレクトリへ転送します。

## 初期設定（GitHub）

Settings → Secrets and variables → Actions で以下の Repository secrets を設定してください。

| Secret | 内容 |
| --- | --- |
| `OPT_DEPLOY_HOST` | SSH 接続先ホスト名または IPv4 アドレス |
| `OPT_DEPLOY_USER` | SSH ユーザー名 |
| `OPT_DEPLOY_PORT` | SSH ポート（省略時は 22） |
| `OPT_DEPLOY_PATH` | 実際の公開ディレクトリへの絶対パス（例ではなく実値を設定） |
| `OPT_DEPLOY_SSH_KEY` | デプロイ専用の秘密鍵全文 |
| `OPT_DEPLOY_KNOWN_HOSTS` | 事前に安全な手段で検証した SSH ホスト公開鍵の known_hosts 行 |

SSH 対象ユーザーに `OPT_DEPLOY_PATH` への書き込み権限、サーバーに `rsync` が必要です。リポジトリや作業ログに秘密鍵、DB 接続情報を置かないでください。

## 操作

1. GitHub → **Actions** → **OPT manual deployment** → **Run workflow** を開きます。
2. まず `preview` で実行し、ログの差分を確認します。サーバー上のファイルは変更されません。
3. 内容が正しければ同じ画面から `deploy` を選択して再実行します。
4. `https://order.optjet.com/admin/` で編集機能などを手動確認します（ログイン先が違う場合は実際の管理URLを使用）。

## 保護事項

- 自動実行はしません。手動のみです。
- サーバーからファイルを削除しません（`--delete` を使用しません）。
- `public_html/inc.db.php`、`public_html/inc.mail.php`、`public_html/admin/.htpasswd`、`public_html/admin/inc.db.php` および `.env` 系ファイルは転送しません。
- サーバー上の未追跡ファイルはそのまま残ります。
- 本ワークフローはソース差分の検証のみで、PHP の実行、DB マイグレーション、HTTP 動作テスト、ロールバックは自動化していません。
- 事故を防ぐため、GitHub **Settings → Environments → production** に承認者設定を追加することを推奨します。GitHub プランやリポジトリ公開設定で利用可否が異なります。

初回設定に必要な SSH ユーザー・パス・ポート・ホスト鍵は、まだこのリポジトリから確認できていません。推測して設定しないでください。
