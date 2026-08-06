<?php
/**
 * プラグインの render() が実際に出力するHTMLを、ダミー記事に埋めたプレビューを書き出す。
 * ブラウザで表示タイミング（%）の挙動を目で確認するための検証用。
 *
 * 実行: php -n render_preview.php <出力先html> [表示%]
 */
define('ABSPATH', __DIR__ . '/');
define('UKGI_PREVIEW', true);

/* ---------- WordPress の最小スタブ ---------- */
$GLOBALS['UKGI_OPTS'] = array();
function add_action($h, $cb, $p = 10, $a = 1) {}
function add_filter($h, $cb, $p = 10, $a = 1) {}
function get_option($k, $d = array()) { return $GLOBALS['UKGI_OPTS']; }
function wp_parse_args($a, $d) { return array_merge($d, (array)$a); }
function is_admin() { return false; }
function is_front_page() { return true; }
function is_singular($t = '') { return true; }
function wp_is_mobile() { return false; }
function do_shortcode($s) { return $s; }
function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_js($s) { return addslashes($s); }
function current_user_can($c) { return true; }
function plugin_basename($f) { $f = str_replace('\\', '/', $f); return implode('/', array_slice(explode('/', $f), -2)); }

require __DIR__ . '/uru-kagi-popup/uru-kagi-popup.php';

$out = $argv[1] ?? (__DIR__ . '/preview.html');
$pct = isset($argv[2]) ? (int)$argv[2] : 30;

$GLOBALS['UKGI_OPTS'] = array(
    'enabled'    => 1,
    'mode'       => 'shortcode',
    'shortcode_html' => '<strong>売却を検討中の方へ</strong><br>まだ売ると決めていなくても、相場の確認だけでもどうぞ。'
                      . '<br><a href="#" style="display:inline-block;margin-top:10px;padding:8px 14px;background:#2e6b4f;color:#fff;border-radius:4px;text-decoration:none;">相場を見る</a>',
    'scroll_pct' => $pct,
    'width'      => 320,
    'scope'      => 'all',
    'show_mobile'=> 1,
    'version'    => 1,
);

ob_start();
$p = new Uru_Kagi_Popup();
$p->render();
$popup = ob_get_clean();

$n = isset($argv[3]) ? max(1, (int)$argv[3]) : 60;   // ダミー段落数（短いページの検証用）
$paras = '';
for ($i = 1; $i <= $n; $i++) {
    $paras .= "<p>ダミー本文 {$i}／{$n}。ページ全体の約 " . round($i / $n * 100) . "% 地点です。</p>\n";
}

$html = <<<HTML
<!doctype html>
<html lang="ja"><head><meta charset="utf-8">
<title>ポップアップ表示タイミング検証（{$pct}%）</title>
<style>body{font-family:sans-serif;max-width:800px;margin:0 auto;padding:20px;line-height:2}
#meter{position:fixed;left:16px;top:16px;background:#111;color:#fff;padding:6px 10px;border-radius:4px;font-size:13px;z-index:99999}</style>
</head><body>
<div id="meter">読了率 <span id="pct">0</span>% ／ しきい値 {$pct}% ／ ポップアップ <span id="state">非表示</span></div>
<h1>記事本文（ダミー）</h1>
{$paras}
{$popup}
<script>
(function(){
  var el = document.getElementById('ukgi-popup');
  function prog(){
    var d=document.documentElement,b=document.body;
    var h=Math.max(b.scrollHeight,d.scrollHeight,b.offsetHeight,d.offsetHeight);
    var s=h-window.innerHeight; if(s<=0) return 100;
    return Math.min(100,(window.pageYOffset||d.scrollTop||0)/s*100);
  }
  function tick(){
    document.getElementById('pct').textContent = Math.round(prog());
    document.getElementById('state').textContent =
      (el && getComputedStyle(el).opacity === '1') ? '表示' : '非表示';
  }
  window.addEventListener('scroll', tick, {passive:true});
  window.addEventListener('resize', tick);
  setInterval(tick, 200); tick();
})();
</script>
</body></html>
HTML;

file_put_contents($out, $html);
echo "wrote: {$out} (threshold={$pct}%)\n";
