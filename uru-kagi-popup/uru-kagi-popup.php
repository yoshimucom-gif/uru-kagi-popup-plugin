<?php
/**
 * Plugin Name: 不動産売却のカギ ポップアップ
 * Description: 右下スライドイン型ポップアップ。画像モード（画像＋リンクURL）とショートコードモード（任意HTML）を切替可能。スクロールで表示、×で閉じたらセッション中は再表示しない。
 * Version: 1.0.1
 * Author: 不動産売却のカギ
 * License: GPLv2 or later
 * Text Domain: uru-kagi-popup
 */

if (!defined('ABSPATH')) exit;

define('UKGI_VER', '1.0.1');

/**
 * 自動更新の置き場（update.json の URL）。
 * 新バージョンを置くと WP管理画面に「更新可能」バッジが出てワンクリック更新できる。
 * ※ 空なら自動更新は無効（手動アップロードでの運用は可能）。
 */
define('UKGI_UPDATE_URL', 'https://raw.githubusercontent.com/yoshimucom-gif/uru-kagi-popup-plugin/main/update.json');

if (UKGI_UPDATE_URL) {
    require_once __DIR__ . '/includes/plugin-updater.php';
    new UKGI_Popup_Updater(__FILE__, UKGI_UPDATE_URL);
}

class Uru_Kagi_Popup {

    const OPT = 'ukgi_popup_options';

    public static function defaults() {
        return array(
            'enabled'        => 0,
            'mode'           => 'image',        // image | shortcode
            'image_url'      => '',
            'image_alt'      => '',
            'link_url'       => '',
            'link_blank'     => 0,
            'shortcode_html' => '',
            'scroll_px'      => 300,            // 表示トリガー（スクロール量px）
            'width'          => 320,            // ポップアップ幅px（PC）
            'scope'          => 'all',          // all | posts | front
            'show_mobile'    => 1,
            'version'        => 1,              // 変えると閉じた人にも再表示
        );
    }

    public static function get() {
        return wp_parse_args(get_option(self::OPT, array()), self::defaults());
    }

    public function __construct() {
        add_action('admin_menu',            array($this, 'admin_menu'));
        add_action('admin_init',            array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_footer',             array($this, 'render'));
    }

    /* ---------- 管理画面 ---------- */

    public function admin_menu() {
        add_options_page('カギ ポップアップ', 'カギ ポップアップ', 'manage_options', 'ukgi-popup', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('ukgi_popup_group', self::OPT, array($this, 'sanitize'));
    }

    public function sanitize($in) {
        $d = self::defaults();
        $out = array();
        $out['enabled']        = empty($in['enabled']) ? 0 : 1;
        $out['mode']           = in_array($in['mode'] ?? 'image', array('image','shortcode'), true) ? $in['mode'] : 'image';
        $out['image_url']      = esc_url_raw($in['image_url'] ?? '');
        $out['image_alt']      = sanitize_text_field($in['image_alt'] ?? '');
        $out['link_url']       = esc_url_raw($in['link_url'] ?? '');
        $out['link_blank']     = empty($in['link_blank']) ? 0 : 1;
        // ショートコード/HTML：管理者のみ保存できる前提でタグを広めに許可
        $out['shortcode_html'] = current_user_can('unfiltered_html')
            ? (string)($in['shortcode_html'] ?? '')
            : wp_kses_post($in['shortcode_html'] ?? '');
        $out['scroll_px']      = max(0, intval($in['scroll_px'] ?? $d['scroll_px']));
        $out['width']          = min(600, max(200, intval($in['width'] ?? $d['width'])));
        $out['scope']          = in_array($in['scope'] ?? 'all', array('all','posts','front'), true) ? $in['scope'] : 'all';
        $out['show_mobile']    = empty($in['show_mobile']) ? 0 : 1;
        $out['version']        = max(1, intval($in['version'] ?? 1));
        return $out;
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_ukgi-popup') return;
        wp_enqueue_media();
    }

    public function settings_page() {
        $o = self::get(); ?>
        <div class="wrap">
            <h1>不動産売却のカギ ポップアップ</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ukgi_popup_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">表示</th>
                        <td><label><input type="checkbox" name="<?php echo self::OPT; ?>[enabled]" value="1" <?php checked($o['enabled'],1); ?>> ポップアップを有効にする</label></td>
                    </tr>
                    <tr>
                        <th scope="row">モード</th>
                        <td>
                            <label><input type="radio" name="<?php echo self::OPT; ?>[mode]" value="image" <?php checked($o['mode'],'image'); ?> class="ukgi-mode"> 画像モード（画像＋リンクURL）</label><br>
                            <label><input type="radio" name="<?php echo self::OPT; ?>[mode]" value="shortcode" <?php checked($o['mode'],'shortcode'); ?> class="ukgi-mode"> ショートコードモード（任意HTML）</label>
                        </td>
                    </tr>
                    <tr class="ukgi-row-image">
                        <th scope="row">画像</th>
                        <td>
                            <input type="text" name="<?php echo self::OPT; ?>[image_url]" id="ukgi-image-url" value="<?php echo esc_attr($o['image_url']); ?>" class="regular-text" placeholder="https://...">
                            <button type="button" class="button" id="ukgi-media-btn">メディアから選択</button>
                            <p class="description">推奨：横幅600px以上。表示幅（下の設定）に合わせて縮小されます。</p>
                            <div id="ukgi-image-preview" style="margin-top:8px;"><?php if ($o['image_url']) echo '<img src="'.esc_url($o['image_url']).'" style="max-width:240px;height:auto;border:1px solid #ddd;">'; ?></div>
                        </td>
                    </tr>
                    <tr class="ukgi-row-image">
                        <th scope="row">代替テキスト</th>
                        <td><input type="text" name="<?php echo self::OPT; ?>[image_alt]" value="<?php echo esc_attr($o['image_alt']); ?>" class="regular-text" placeholder="例：岡山市の売却相場を見る"></td>
                    </tr>
                    <tr class="ukgi-row-image">
                        <th scope="row">リンクURL</th>
                        <td>
                            <input type="text" name="<?php echo self::OPT; ?>[link_url]" value="<?php echo esc_attr($o['link_url']); ?>" class="regular-text" placeholder="https://...">
                            <label style="margin-left:8px;"><input type="checkbox" name="<?php echo self::OPT; ?>[link_blank]" value="1" <?php checked($o['link_blank'],1); ?>> 新しいタブで開く</label>
                            <p class="description">空の場合、画像はリンクなしで表示されます。</p>
                        </td>
                    </tr>
                    <tr class="ukgi-row-shortcode">
                        <th scope="row">ショートコード / HTML</th>
                        <td>
                            <textarea name="<?php echo self::OPT; ?>[shortcode_html]" rows="6" class="large-text code" placeholder="[contact-form-7 id=&quot;123&quot;] や任意のHTML"><?php echo esc_textarea($o['shortcode_html']); ?></textarea>
                            <p class="description">ショートコードは実行されて表示されます。白いカード内に描画されます。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">表示タイミング</th>
                        <td><input type="number" name="<?php echo self::OPT; ?>[scroll_px]" value="<?php echo esc_attr($o['scroll_px']); ?>" min="0" step="50" style="width:100px;"> px スクロールしたら表示（0=即表示）</td>
                    </tr>
                    <tr>
                        <th scope="row">幅（PC）</th>
                        <td><input type="number" name="<?php echo self::OPT; ?>[width]" value="<?php echo esc_attr($o['width']); ?>" min="200" max="600" step="10" style="width:100px;"> px（スマホでは画面幅に自動調整）</td>
                    </tr>
                    <tr>
                        <th scope="row">表示するページ</th>
                        <td>
                            <select name="<?php echo self::OPT; ?>[scope]">
                                <option value="all"   <?php selected($o['scope'],'all'); ?>>すべてのページ</option>
                                <option value="posts" <?php selected($o['scope'],'posts'); ?>>投稿ページのみ</option>
                                <option value="front" <?php selected($o['scope'],'front'); ?>>トップページのみ</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">スマホ表示</th>
                        <td><label><input type="checkbox" name="<?php echo self::OPT; ?>[show_mobile]" value="1" <?php checked($o['show_mobile'],1); ?>> スマホでも表示する</label></td>
                    </tr>
                    <tr>
                        <th scope="row">表示バージョン</th>
                        <td>
                            <input type="number" name="<?php echo self::OPT; ?>[version]" value="<?php echo esc_attr($o['version']); ?>" min="1" style="width:100px;">
                            <p class="description">数字を上げると、×で閉じた人にも再表示されます（内容を差し替えたときに使用）。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        (function($){
            function toggleRows(){
                var mode = $('.ukgi-mode:checked').val();
                $('.ukgi-row-image').toggle(mode==='image');
                $('.ukgi-row-shortcode').toggle(mode==='shortcode');
            }
            $('.ukgi-mode').on('change', toggleRows); toggleRows();
            $('#ukgi-media-btn').on('click', function(e){
                e.preventDefault();
                var frame = wp.media({title:'画像を選択', multiple:false, library:{type:'image'}});
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    $('#ukgi-image-url').val(att.url);
                    $('#ukgi-image-preview').html('<img src="'+att.url+'" style="max-width:240px;height:auto;border:1px solid #ddd;">');
                });
                frame.open();
            });
        })(jQuery);
        </script>
        <?php
    }

    /* ---------- フロント出力 ---------- */

    private function should_show($o) {
        if (!$o['enabled']) return false;
        if ($o['mode']==='image' && !$o['image_url']) return false;
        if ($o['mode']==='shortcode' && trim($o['shortcode_html'])==='') return false;
        if (is_admin()) return false;
        switch ($o['scope']) {
            case 'front': if (!is_front_page()) return false; break;
            case 'posts': if (!is_singular('post')) return false; break;
        }
        if (!$o['show_mobile'] && wp_is_mobile()) return false;
        return true;
    }

    public function render() {
        $o = self::get();
        if (!$this->should_show($o)) return;

        $key = 'ukgi_popup_closed_v' . intval($o['version']);
        $w   = intval($o['width']);

        if ($o['mode']==='image') {
            $img = '<img src="'.esc_url($o['image_url']).'" alt="'.esc_attr($o['image_alt']).'" style="display:block;width:100%;height:auto;border-radius:8px;">';
            if ($o['link_url']) {
                $target = $o['link_blank'] ? ' target="_blank" rel="noopener"' : '';
                $inner = '<a href="'.esc_url($o['link_url']).'"'.$target.' style="display:block;line-height:0;">'.$img.'</a>';
            } else {
                $inner = $img;
            }
            $card_style = 'background:transparent;padding:0;';
        } else {
            $inner = '<div class="ukgi-popup-body">'.do_shortcode($o['shortcode_html']).'</div>';
            $card_style = 'background:#fff;padding:16px;';
        }
        ?>
        <div id="ukgi-popup" aria-hidden="true" style="position:fixed;right:16px;bottom:16px;z-index:99999;width:<?php echo $w; ?>px;max-width:calc(100vw - 32px);opacity:0;transform:translateY(12px);transition:opacity .35s ease,transform .35s ease;pointer-events:none;">
            <div style="position:relative;<?php echo $card_style; ?>border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.14);">
                <button type="button" id="ukgi-popup-close" aria-label="閉じる" style="position:absolute;top:-10px;right:-10px;width:28px;height:28px;border:none;border-radius:50%;background:#333;color:#fff;font-size:14px;line-height:1;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.3);">×</button>
                <?php echo $inner; ?>
            </div>
        </div>
        <script>
        (function(){
            try { if (sessionStorage.getItem('<?php echo esc_js($key); ?>')) return; } catch(e){}
            var el = document.getElementById('ukgi-popup');
            var shown = false;
            function show(){
                if (shown) return; shown = true;
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
                el.style.pointerEvents = 'auto';
                el.setAttribute('aria-hidden','false');
            }
            var th = <?php echo intval($o['scroll_px']); ?>;
            if (th <= 0) { show(); }
            else {
                function onScroll(){
                    if ((window.pageYOffset || document.documentElement.scrollTop) >= th) {
                        show(); window.removeEventListener('scroll', onScroll);
                    }
                }
                window.addEventListener('scroll', onScroll, {passive:true});
                onScroll();
            }
            document.getElementById('ukgi-popup-close').addEventListener('click', function(){
                el.style.opacity = '0';
                el.style.transform = 'translateY(12px)';
                el.style.pointerEvents = 'none';
                el.setAttribute('aria-hidden','true');
                try { sessionStorage.setItem('<?php echo esc_js($key); ?>','1'); } catch(e){}
            });
        })();
        </script>
        <?php
    }
}

new Uru_Kagi_Popup();
