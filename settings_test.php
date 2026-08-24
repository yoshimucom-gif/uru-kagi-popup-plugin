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
function is_admin() { return false; }
function is_front_page() { return true; }
function is_singular($t = '') { return true; }
function do_shortcode($s) { return $s; }

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


/* ---- デバイスの出し分け ---- */
t('デバイスの既定は両方', $d['devices'], 'all');
t('スマホとみなす幅の既定は767', $d['sp_max'], 767);
t('pc はそのまま', $p->sanitize(array('devices' => 'pc'))['devices'], 'pc');
t('sp はそのまま', $p->sanitize(array('devices' => 'sp'))['devices'], 'sp');
t('不正な値は両方に戻る', $p->sanitize(array('devices' => 'xxx'))['devices'], 'all');
t('未送信でも両方に戻る', $p->sanitize(array())['devices'], 'all');
t('幅は320未満なら320に丸める', $p->sanitize(array('sp_max' => 100))['sp_max'], 320);
t('幅は1200超なら1200に丸める', $p->sanitize(array('sp_max' => 9999))['sp_max'], 1200);
t('旧 show_mobile は保存されない', array_key_exists('show_mobile', $p->sanitize(array('show_mobile' => 1))), false);

/* 旧版（チェックボックス1つ）からの引き継ぎ */
$GLOBALS['UKGI_OPTS'] = array('enabled' => 1, 'show_mobile' => 0);
$g = Uru_Kagi_Popup::get();
t('旧「スマホに出さない」→ PCのみ', $g['devices'], 'pc');
$GLOBALS['UKGI_OPTS'] = array('enabled' => 1, 'show_mobile' => 1);
$g = Uru_Kagi_Popup::get();
t('旧「スマホにも出す」→ 両方', $g['devices'], 'all');
$GLOBALS['UKGI_OPTS'] = array('enabled' => 1, 'show_mobile' => 0, 'devices' => 'sp');
$g = Uru_Kagi_Popup::get();
t('新しい設定があれば旧設定に上書きされない', $g['devices'], 'sp');

/* 実際にフロントへ出力されるCSS */
function ukgi_front($opts) {
    $GLOBALS['UKGI_OPTS'] = array_merge(
        array('enabled' => 1, 'mode' => 'shortcode', 'shortcode_html' => 'x'), $opts);
    $pp = new Uru_Kagi_Popup();
    ob_start(); $pp->render(); return ob_get_clean();
}
$html_all = ukgi_front(array('devices' => 'all'));
$html_pc  = ukgi_front(array('devices' => 'pc'));
$html_sp  = ukgi_front(array('devices' => 'sp'));
$html_bp  = ukgi_front(array('devices' => 'pc', 'sp_max' => 1024));

t('両方のときは出し分けCSSを出さない', strpos($html_all, 'ukgi-popup-css') !== false, false);
t('PCのみ＝767px以下で隠す', strpos($html_pc, '@media (max-width:767px){#ukgi-popup{display:none !important}}') !== false, true);
t('スマホのみ＝768px以上で隠す', strpos($html_sp, '@media (min-width:768px){#ukgi-popup{display:none !important}}') !== false, true);
t('幅の設定がCSSに反映される', strpos($html_bp, '@media (max-width:1024px)') !== false, true);
t('出し分けてもポップアップ本体は常に出力される', substr_count($html_pc, 'id="ukgi-popup"'), 1);
// コメントでは触れているので、コメントを除いたコード本体だけを見る
$src  = file_get_contents(__DIR__ . '/uru-kagi-popup/uru-kagi-popup.php');
$code = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk) && in_array($tk[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($tk) ? $tk[1] : $tk;
}
t('サーバー側でスマホを弾いていない（wp_is_mobile 未使用）',
  strpos($code, 'wp_is_mobile') !== false, false);

/* ---- 設定画面にデバイスの行があるか ---- */
$GLOBALS['UKGI_OPTS'] = array();
ob_start(); $p->settings_page(); $html2 = ob_get_clean();
t('デバイスの選択肢が出ている', strpos($html2, 'ukgi_popup_options[devices]') !== false, true);
t('スマホとみなす幅の欄が出ている', strpos($html2, 'ukgi_popup_options[sp_max]') !== false, true);
t('旧スマホ表示チェックは消えている', strpos($html2, 'show_mobile') !== false, false);
t('見出しが汎用名になっている', strpos($html2, '<h1>スライドインポップアップ</h1>') !== false, true);

echo "\n" . ($ng === 0 ? "すべて OK" : "NG {$ng} 件") . "\n";
exit($ng === 0 ? 0 : 1);
