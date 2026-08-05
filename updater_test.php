<?php
/**
 * 自動更新チェッカーの実HTTP検証。
 * GitHub に置いた update.json を実際に取りに行き、
 *   ・管理画面の外（WP-Cron相当）でもチェッカーが動くか
 *   ・同じバージョンなら「更新なし」になるか
 *   ・古い版には更新エントリ＋zipのURLが入るか
 * を確認する。
 *
 * 実行: C:\Users\yoshi\php-portable\php82\php.exe -n updater_test.php
 *   （-n は php.ini を読まない＝mbstring等が無い環境の再現）
 */

define('ABSPATH', __DIR__ . '/');
define('DOING_CRON', true);   // 管理画面の外を再現

/* ---------- WordPress の最小スタブ ---------- */
class WP_Error {
    public $msg;
    public function __construct($code = '', $msg = '') { $this->msg = $msg; }
}
function is_wp_error($t) { return ($t instanceof WP_Error); }

$GLOBALS['HOOKS'] = array();
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['HOOKS'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['HOOKS'][$h][] = $cb; }
function has_action($h) { return !empty($GLOBALS['HOOKS'][$h]); }

$GLOBALS['TRANSIENTS'] = array();
function get_transient($k) { return isset($GLOBALS['TRANSIENTS'][$k]) ? $GLOBALS['TRANSIENTS'][$k] : false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['TRANSIENTS'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['TRANSIENTS'][$k]); return true; }

function plugin_basename($file) {
    $file = str_replace('\\', '/', $file);
    $parts = explode('/', $file);
    return implode('/', array_slice($parts, -2));
}
function get_plugin_data($file, $markup = true, $translate = true) {
    preg_match('/^\s*\*\s*Version:\s*(\S+)/m', (string)file_get_contents($file), $m);
    return array('Version' => isset($m[1]) ? $m[1] : '');
}
function get_option($k, $d = array()) { return $d; }
function wp_parse_args($a, $d) { return array_merge($d, (array)$a); }

/** wp_remote_get は本物のHTTPに差し替える（TLS検証は切らない）。
 *  ポータブルPHPにはCAバンドルが無いのでWindowsの証明書ストアを使う。
 *  本番のWordPressは自前のCAバンドルを持つのでこの指定は検証環境専用。 */
function wp_remote_get($url, $args = array()) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        CURLOPT_USERAGENT      => 'WordPress/6.7; test',
        CURLOPT_SSL_OPTIONS    => CURLSSLOPT_NATIVE_CA,
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) return new WP_Error('http', 'curl failed');
    return array('code' => $code, 'body' => $body);
}
function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }

/* ---------- 検証 ---------- */
require __DIR__ . '/uru-kagi-popup/uru-kagi-popup.php';

$ng = 0;
function t($name, $got, $want) {
    global $ng;
    $ok = ($got === $want);
    if (!$ok) $ng++;
    printf("%s %s\n     got=%s want=%s\n", $ok ? 'OK  ' : 'NG  ', $name, var_export($got, true), var_export($want, true));
}

$main = __DIR__ . '/uru-kagi-popup/uru-kagi-popup.php';
$basename = 'uru-kagi-popup/uru-kagi-popup.php';

t('cron中(管理画面外)でもチェッカーが読み込まれている', class_exists('UKGI_Popup_Updater'), true);
t('更新チェックのフックが登録されている', has_action('pre_set_site_transient_update_plugins'), true);
t('「詳細を表示」のフックが登録されている', has_action('plugins_api'), true);

/* 1) インストール済みが最新＝更新なし */
$updater = new UKGI_Popup_Updater($main, UKGI_UPDATE_URL);
$tr = $updater->check_for_update((object)array('response' => array(), 'no_update' => array()));
t('同バージョンなら更新エントリは出ない', isset($tr->response[$basename]), false);
t('no_update に入る（サーバーに到達できている証拠）', isset($tr->no_update[$basename]), true);
if (isset($tr->no_update[$basename])) {
    echo "     配信中のバージョン: " . $tr->no_update[$basename]->new_version . "\n";
    t('配信バージョン = 本体バージョン（build.py の実行漏れ検出）', $tr->no_update[$basename]->new_version, UKGI_VER);
}

/* 2) 古い版には更新エントリが入るか（0.9.0 のダミーで再現） */
$fake = __DIR__ . '/_fake_old_plugin.php';
file_put_contents($fake, "<?php\n/**\n * Plugin Name: dummy\n * Version: 0.9.0\n */\n");
$updater2 = new UKGI_Popup_Updater($fake, UKGI_UPDATE_URL);
$tr2 = $updater2->check_for_update((object)array('response' => array(), 'no_update' => array()));
$key = plugin_basename($fake);
$entry = isset($tr2->response[$key]) ? $tr2->response[$key] : null;
t('古い版には更新エントリが入る', $entry !== null, true);
if ($entry) {
    t('新バージョンが入っている', $entry->new_version, UKGI_VER);
    t('zipのダウンロードURLが入っている', strpos($entry->package, 'uru-kagi-popup.zip') !== false, true);
    echo "     package: " . $entry->package . "\n";
}

/* 3) zip が実際にダウンロードできるか（WPが更新時に取りに行くURL） */
$zip = wp_remote_get(isset($entry->package) ? $entry->package : '');
t('zipのURLが 200 を返す', wp_remote_retrieve_response_code($zip), 200);
$body = wp_remote_retrieve_body($zip);
t('中身がzipである（PK ヘッダー）', substr($body, 0, 2), 'PK');

/* 4) 「詳細を表示」モーダルの中身 */
$info = $updater->plugins_api_filter(false, 'plugin_information', (object)array('slug' => 'uru-kagi-popup'));
t('プラグイン情報が返る', is_object($info), true);
if (is_object($info)) {
    t('名前が入っている', $info->name, '不動産売却のカギ ポップアップ');
    t('変更履歴が入っている', !empty($info->sections['changelog']), true);
}

@unlink($fake);
echo "\n" . ($ng === 0 ? "すべて OK" : "NG {$ng} 件") . "\n";
exit($ng === 0 ? 0 : 1);
