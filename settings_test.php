<?php
/**
 * 設定の保存（sanitize）と設定画面の描画を検証する。
 * 実行: php -n settings_test.php
 */
define('ABSPATH', __DIR__ . '/');

function add_action($h, $cb, $p = 10, $a = 1) {}
function add_filter($h, $cb, $p = 10, $a = 1) {}
function get_option($k, $d = array()) { return isset($GLOBALS['UKGI_OPTS']) ? $GLOBALS['UKGI_OPTS'] : $d; }
function wp_parse_args($a, $d) { return array_merge($d, (array)$a); }
function plugin_basename($f) { $f = str_replace('\\', '/', $f); return implode('/', array_slice(explode('/', $f), -2)); }
function current_user_can($c) { return true; }
function esc_url_raw($s) { return $s; }
function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_js($s) { return addslashes($s); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function wp_kses_post($s) { return strip_tags($s, '<a><strong><em><br><p>'); }
function settings_fields($g) { echo '<input type="hidden" name="option_page" value="' . $g . '">'; }
function submit_button() { echo '<p class="submit"><input type="submit" class="button-primary" value="変更を保存"></p>'; }
function checked($a, $b = true, $echo = true) { if ($a == $b) echo ' checked'; }
function selected($a, $b = true, $echo = true) { if ($a == $b) echo ' selected'; }

$GLOBALS['UKGI_OPTS'] = array();
require __DIR__ . '/uru-kagi-popup/uru-kagi-popup.php';

$ng = 0;
function t($name, $got, $want) {
    global $ng;
    $ok = ($got === $want);
    if (!$ok) $ng++;
    printf("%s %s\n     got=%s want=%s\n", $ok ? 'OK  ' : 'NG  ', $name, var_export($got, true), var_export($want, true));
}

$p = new Uru_Kagi_Popup();

/* ---- 保存（sanitize） ---- */
$d = Uru_Kagi_Popup::defaults();
t('既定は30%', $d['scroll_pct'], 30);
t('旧px設定は残っていない', array_key_exists('scroll_px', $d), false);

$s = $p->sanitize(array('scroll_pct' => '45'));
t('45 はそのまま', $s['scroll_pct'], 45);
t('101 は 100 に丸める', $p->sanitize(array('scroll_pct' => 101))['scroll_pct'], 100);
t('-10 は 0 に丸める', $p->sanitize(array('scroll_pct' => -10))['scroll_pct'], 0);
t('0 は 0 のまま（即表示）', $p->sanitize(array('scroll_pct' => 0))['scroll_pct'], 0);
t('空欄は既定の30に戻る', $p->sanitize(array())['scroll_pct'], 30);
t('文字が入っても0以上に収まる', $p->sanitize(array('scroll_pct' => 'あいう'))['scroll_pct'], 0);
t('旧px値を渡しても保存されない', array_key_exists('scroll_px', $p->sanitize(array('scroll_px' => 300))), false);

/* ---- 未送信の項目が null にならないこと（PHP警告つきで壊れていた） ---- */
t('mode 未送信で image に戻る', $p->sanitize(array())['mode'], 'image');
t('scope 未送信で all に戻る', $p->sanitize(array())['scope'], 'all');
t('不正な mode は image に戻る', $p->sanitize(array('mode' => 'xxx'))['mode'], 'image');
t('不正な scope は all に戻る', $p->sanitize(array('scope' => 'xxx'))['scope'], 'all');
t('正しい mode はそのまま', $p->sanitize(array('mode' => 'shortcode'))['mode'], 'shortcode');
t('正しい scope はそのまま', $p->sanitize(array('scope' => 'posts'))['scope'], 'posts');

/* ---- 設定画面の描画 ---- */
ob_start();
$p->settings_page();
$html = ob_get_clean();
t('新しい%入力欄が出ている', strpos($html, 'ukgi_popup_options[scroll_pct]') !== false, true);
t('旧px入力欄は消えている', strpos($html, 'scroll_px') !== false, false);
t('上限100が付いている', preg_match('/scroll_pct\]"[^>]*max="100"/', $html) === 1, true);
t('PHPの警告が出ていない', strpos($html, 'Warning') !== false, false);

/* ---- 旧版(px)から更新したユーザーの設定を読んだとき ---- */
$GLOBALS['UKGI_OPTS'] = array('enabled' => 1, 'scroll_px' => 300, 'width' => 320);
$o = Uru_Kagi_Popup::get();
t('旧設定を読んでも%は既定値で埋まる', $o['scroll_pct'], 30);
t('他の設定（有効化）は引き継がれる', $o['enabled'], 1);

echo "\n" . ($ng === 0 ? "すべて OK" : "NG {$ng} 件") . "\n";
exit($ng === 0 ? 0 : 1);
