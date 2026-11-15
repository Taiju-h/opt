<?php
declare(strict_types=1);
/**
 * OPT browser deployer. Requires admin login and an existing git checkout at /var/www/order.optjet.com.
 * GET is read-only; POST applies a fast-forward or restores the immediately preceding deployment.
 */
session_start();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
if (($_SESSION['admin_role'] ?? '') !== 'super_admin' || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('特権管理者でログインしてください。');
}
$expectedHost = 'order.optjet.com';
$host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
if ($host !== $expectedHost) { http_response_code(403); exit('OPT本番専用です。'); }
$root = realpath(__DIR__ . '/../..');
$expectedRoot = '/var/www/order.optjet.com';
if ($root !== $expectedRoot || !is_dir($root . '/.git')) {
    http_response_code(503);
    exit('Git管理ディレクトリが想定と違います。サーバーの配置と権限を確認してください。');
}
if (!function_exists('exec')) { http_response_code(503); exit('execが無効です。'); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cmd(array $args): array {
    $output = []; $status = 0;
    exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $output, $status);
    return ['code' => $status, 'output' => implode("\n", $output)];
}
function git(array $args): array {
    global $root;
    return cmd(array_merge(['git', '-c', 'safe.directory=' . $root, '-C', $root], $args));
}
function checked(array $args): string {
    $r = git($args);
    if ($r['code'] !== 0) throw new RuntimeException('Git操作に失敗しました: ' . implode(' ', $args) . "\n" . $r['output']);
    return trim($r['output']);
}
function state(): array {
    $branch = checked(['symbolic-ref', '--quiet', '--short', 'HEAD']);
    $remote = checked(['remote', 'get-url', 'origin']);
    $head = checked(['rev-parse', 'HEAD']);
    $status = checked(['status', '--porcelain', '--untracked-files=no']);
    if ($branch !== 'main' || !in_array($remote, [
        'https://github.com/Taiju-h/opt.git',
        'git@github.com:Taiju-h/opt.git',
    ], true) || !preg_match('/^[a-f0-9]{40}$/D', $head)) {
        throw new RuntimeException('ブランチまたはoriginが想定と異なります。操作を停止しました。');
    }
    return compact('branch', 'remote', 'head', 'status');
}
if (empty($_SESSION['opt_deploy_csrf'])) $_SESSION['opt_deploy_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['opt_deploy_csrf'];
$recordFile = $root . '/.opt-deploy-last.json';
$lockFile = sys_get_temp_dir() . '/opt-deploy-' . sha1($root) . '.lock';
$error = ''; $result = ''; $before = null; $remoteHead = ''; $history = null;
try {
    $before = state();
    if (is_file($recordFile)) {
        $history = json_decode((string)file_get_contents($recordFile), true);
        if (!is_array($history)) $history = null;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403); throw new RuntimeException('不正なリクエストです。');
        }
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['deploy', 'rollback'], true)) throw new RuntimeException('無効な操作です。');
        if ((string)($_POST['confirm'] ?? '') !== ($action === 'deploy' ? 'DEPLOY_OPT' : 'ROLLBACK_OPT')) {
            throw new RuntimeException('確認チェックを付けてください。');
        }
        if (!hash_equals($before['head'], (string)($_POST['expected_head'] ?? ''))) {
            http_response_code(409); throw new RuntimeException('画面表示後にHEADが変更されました。画面を更新してください。');
        }
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('別のデプロイ操作が実行中です。');
        try {
            $now = state();
            if ($now !== $before || $now['status'] !== '') throw new RuntimeException('ローカル変更または状態変化があります。手動確認してください。');
            if ($action === 'deploy') {
                $fetch = git(['fetch', '--no-tags', 'origin', 'main']);
                if ($fetch['code'] !== 0) throw new RuntimeException("fetch失敗:\n" . $fetch['output']);
                $target = checked(['rev-parse', 'refs/remotes/origin/main']);
                if ($target === $now['head']) {
                    $result = '既に最新版です。変更はありません。';
                } else {
                    checked(['merge-base', '--is-ancestor', $now['head'], $target]);
                    $ff = git(['merge', '--ff-only', $target]);
                    if ($ff['code'] !== 0) throw new RuntimeException("反映失敗:\n" . $ff['output']);
                    $after = checked(['rev-parse', 'HEAD']);
                    if ($after !== $target) throw new RuntimeException('反映後のSHAが期待値と一致しません。');
                    $record = ['before' => $now['head'], 'after' => $after, 'time' => gmdate('c')];
                    $json = json_encode($record, JSON_THROW_ON_ERROR);
                    if (file_put_contents($recordFile . '.tmp', $json, LOCK_EX) === false || !rename($recordFile . '.tmp', $recordFile)) {
                        throw new RuntimeException('反映は完了しましたが復旧記録を保存できませんでした。');
                    }
                    $history = $record;
                    $result = "反映完了:\n" . $now['head'] . "\n→ " . $after . "\n" . $ff['output'];
                }
            } else {
                if (!$history || ($history['after'] ?? '') !== $now['head'] || !preg_match('/^[a-f0-9]{40}$/D', (string)($history['before'] ?? ''))) {
                    throw new RuntimeException('直前のデプロイ記録と現在のHEADが一致しません。復旧できません。');
                }
                $target = $history['before'];
                $reset = git(['reset', '--hard', $target]);
                if ($reset['code'] !== 0) throw new RuntimeException("復旧失敗:\n" . $reset['output']);
                if (checked(['rev-parse', 'HEAD']) !== $target) throw new RuntimeException('復旧後のSHAが一致しません。');
                if (!unlink($recordFile)) throw new RuntimeException('復旧後の履歴ファイルを削除できませんでした。');
                $history = null;
                $result = "復旧完了: " . $target . "\n再度最新を反映するには「GitHubへ反映」を使用してください。";
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        $before = state();
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405); header('Allow: GET, POST'); throw new RuntimeException('GETまたはPOSTのみ利用可能です。');
    }
    // Only local state is read on GET. Fetch runs on the POST deploy action.
    $remoteHead = checked(['rev-parse', '--verify', 'refs/remotes/origin/main']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>OPT デプロイヤー</title>
<style>
*{box-sizing:border-box}body{font:15px/1.6 system-ui,"Noto Sans JP",sans-serif;background:#f3f6fa;color:#172033;margin:0;padding:24px}
main{max-width:900px;margin:auto}header,.card{background:white;border:1px solid #dfe5ec;border-radius:12px;padding:22px;margin-bottom:16px}
h1{font-size:24px;color:#2563eb;margin:0}h2{font-size:19px;margin:0 0 12px}p{margin:10px 0}
pre{background:#101827;color:#edf2fa;padding:16px;border-radius:8px;overflow-wrap:anywhere;white-space:pre-wrap}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
.grid .card{margin:0}button{background:#2563eb;color:white;padding:12px 16px;border:0;border-radius:7px;cursor:pointer;font-weight:700}
button.danger{background:#b42318}button:disabled{background:#94a3b8;cursor:not-allowed}
label{display:block;margin:12px 0}input[type=checkbox]{width:18px;height:18px;vertical-align:middle}
.error{color:#b42318}.success{color:#087443}a{color:#2563eb}small{color:#64748b}
@media(max-width:600px){body{padding:12px}.grid{display:block}.grid .card{margin-bottom:16px}}
</style></head><body><main>
<header><h1>🚀 OPT デプロイヤー</h1><p>本番：order.optjet.com ／ GitHub：Taiju-h/opt ／ main</p>
<a href="/admin/">管理画面へ戻る</a>　<a href="/admin/m.client.php">担当者管理を確認</a></header>
<?php if ($error !== ''): ?><section class="card"><p class="error" role="alert"><?= h($error) ?></p></section><?php endif; ?>
<?php if ($result !== ''): ?><section class="card"><h2 class="success">処理結果</h2><pre><?= h($result) ?></pre></section><?php endif; ?>
<?php if ($before): ?>
<section class="card"><h2>Gitの状態</h2>
<pre><?= h("Branch: {$before['branch']}\nOrigin: {$before['remote']}\nHEAD: {$before['head']}\nローカル変更: " . ($before['status'] === '' ? 'なし' : $before['status']) . "\norigin/main（最後に取得した値）: " . ($remoteHead ?: '未取得')) ?></pre>
<small>表示された origin/main はサーバーに最後に取得された値です。最新のGitHub情報は反映実行時に fetch します。</small></section>
<div class="grid">
<section class="card"><h2>GitHubから反映</h2><p>現在の main から GitHub の最新 main へ安全な fast-forward 更新を行います。ローカル変更がある場合は停止します。</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="expected_head" value="<?= h($before['head']) ?>"><input type="hidden" name="action" value="deploy">
<label><input type="checkbox" name="confirm" value="DEPLOY_OPT" required> 本番の状態を確認しました</label>
<button type="submit" <?= $before['status'] !== '' ? 'disabled' : '' ?>>最新のmainを本番に反映</button></form></section>
<section class="card"><h2>前回のデプロイを戻す</h2><p>この画面で実行した直前のデプロイに限り、前のコミットへ復旧します。DB・未追跡ファイルは操作しません。</p>
<p><small><?= $history ? h("前: " . ($history['before'] ?? '?') . " / 後: " . ($history['after'] ?? '?')) : '復旧可能な履歴なし' ?></small></p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="expected_head" value="<?= h($before['head']) ?>"><input type="hidden" name="action" value="rollback">
<label><input type="checkbox" name="confirm" value="ROLLBACK_OPT" required> 復旧対象を確認しました</label>
<button class="danger" type="submit" <?= (!$history || ($history['after'] ?? '') !== $before['head'] || $before['status'] !== '') ? 'disabled' : '' ?>>前回の状態に復旧</button></form></section>
</div><?php endif; ?>
</main></body></html>
