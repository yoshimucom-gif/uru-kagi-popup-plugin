<?php
/**
 * Plugin Name: スライドインポップアップ
 * Description: 右下からスライドインするポップアップ。画像モード（画像＋リンクURL）とショートコードモード（任意HTML）を切替可能。ページを何%読んだら出すか、PC・スマホのどちらに出すかを指定でき、×で閉じたらセッション中は再表示しない。
 * Version: 1.2.0
 * Author: ミカタ株式会社
 * License: GPLv2 or later
 * Text Domain: uru-kagi-popup
 */

if (!defined('ABSPATH')) exit;

define('UKGI_VER', '1.2.0');

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
            'scroll_pct'     => 30,             // 表示トリガー（ページを何%読んだら出すか）
            'width'          => 320,            // ポップアップ幅px（PC）
            'scope'          => 'all',          // all | posts | front
            'devices'        => 'all',          // all | pc | sp（出し分けは画面幅で行う）
            'sp_max'         => 767,            // この幅(px)以下をスマホとみなす
            'version'        => 1,              // 変えると閉じた人にも再表示
        );
    }

    public static function get() {
        $saved = get_option(self::OPT, array());
        if (!is_array($saved)) $saved = array();
        // v1.1.0以前は「スマホでも表示する」チェック1つだった。その設定を引き継ぐ
        if (!isset($saved['devices']) && isset($saved['show_mobile'])) {
            $saved['devices'] = empty($saved['show_mobile']) ? 'pc' : 'all';
        }
        unset($saved['show_mobile']);
        return wp_parse_args($saved, self::defaults());
    }

    public function __construct() {
        add_action('admin_menu',            array($this, 'admin_menu'));
        add_action('admin_init',            array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_footer',             array($this, 'render'));
    }

    /* ---------- 管理画面 ---------- */

    public function admin_menu() {
        add_options_page('スライドインポップアップ', 'ポップアップ', 'manage_options', 'ukgi-popup', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('ukgi_popup_group', self::OPT, array($this, 'sanitize'));
    }

    public function sanitize($in) {
        $d = self::defaults();
        $out = array();
        $out['enabled']        = empty($in['enabled']) ? 0 : 1;
        // ?? は必ず変数に受けてから判定する（三項の判定側だけに付けると、
        //   項目が送られてこなかったときに未定義キーを読んで null が保存される）
        $mode                  = $in['mode'] ?? 'image';
        $out['mode']           = in_array($mode, array('image','shortcode'), true) ? $mode : 'image';
        $out['image_url']      = esc_url_raw($in['image_url'] ?? '');
        $out['image_alt']      = sanitize_text_field($in['image_alt'] ?? '');
        $out['link_url']       = esc_url_raw($in['link_url'] ?? '');
        $out['link_blank']     = empty($in['link_blank']) ? 0 : 1;
        // ショートコード/HTML：管理者のみ保存できる前提でタグを広めに許可
        $out['shortcode_html'] = current_user_can('unfiltered_html')
            ? (string)($in['shortcode_html'] ?? '')
            : wp_kses_post($in['shortcode_html'] ?? '');
        // 旧版（px指定）から更新した場合は、保存されていた px を捨てて既定の%に戻す
        $out['scroll_pct']     = min(100, max(0, intval($in['scroll_pct'] ?? $d['scroll_pct'])));
        $out['width']          = min(600, max(200, intval($in['width'] ?? $d['width'])));
        $scope                 = $in['scope'] ?? 'all';
        $out['scope']          = in_array($scope, array('all','posts','front'), true) ? $scope : 'all';
        $devices               = $in['devices'] ?? 'all';
        $out['devices']        = in_array($devices, array('all','pc','sp'), true) ? $devices : 'all';
        $out['sp_max']         = min(1200, max(320, intval($in['sp_max'] ?? $d['sp_max'])));
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
            <h1>スライドインポップアップ</h1>
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
                        <td>
                            ページを <input type="number" name="<?php echo self::OPT; ?>[scroll_pct]" value="<?php echo esc_attr($o['scroll_pct']); ?>" min="0" max="100" step="5" style="width:80px;"> % 読んだら表示
                            <p class="description">
                                記事の長さに関わらず「どこまで読んだか」で出せます。<br>
                                0＝ページを開いた直後／30＝3割ほど読んだところ（おすすめ）／50＝ちょうど半分／100＝ページの一番下。<br>
                                ※スクロールが起きない短いページでは、開いた直後に表示します。
                            </p>
                        </td>
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
                        <th scope="row">表示するデバイス</th>
                        <td>
                            <select name="<?php echo self::OPT; ?>[devices]" class="ukgi-devices">
                                <option value="all" <?php selected($o['devices'],'all'); ?>>PC・スマホの両方</option>
                                <option value="pc"  <?php selected($o['devices'],'pc'); ?>>PCのみ（スマホには出さない）</option>
                                <option value="sp"  <?php selected($o['devices'],'sp'); ?>>スマホのみ（PCには出さない）</option>
                            </select>
                            <p class="description">画面の幅で判定します。ページのキャッシュを使っているサイトでも正しく出し分けられます。</p>
                        </td>
                    </tr>
                    <tr class="ukgi-row-spmax">
                        <th scope="row">スマホとみなす幅</th>
                        <td>
                            画面幅 <input type="number" name="<?php echo self::OPT; ?>[sp_max]" value="<?php echo esc_attr($o['sp_max']); ?>" min="320" max="1200" step="1" style="width:90px;"> px 以下をスマホとする
                            <p class="description">
                                既定の767pxなら、タブレットは横向き＝PC・縦向き＝スマホの扱いになります。<br>
                                タブレットもPC扱いにしたいときは小さく（例：600）、スマホ扱いにしたいときは大きく（例：1024）してください。
                            </p>
                        </td>
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
            function toggleDeviceRow(){
                $('.ukgi-row-spmax').toggle($('.ukgi-devices').val() !== 'all');
            }
            $('.ukgi-devices').on('change', toggleDeviceRow); toggleDeviceRow();
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
        // デバイスの出し分けはここで判定しない。
        // wp_is_mobile() はサーバー側でブラウザの名乗りを見る方式のため、
        // ページキャッシュがあるとPC向けのHTMLがそのままスマホにも配られて出し分けが壊れる。
        // 画面幅のメディアクエリ（device_css）でブラウザ側に判定させる。
        return true;
    }

    /** 表示するデバイスの指定を、画面幅のメディアクエリに変換する */
    private function device_css($o) {
        $bp = intval($o['sp_max']);
        if ($o['devices'] === 'pc') {
            return '@media (max-width:' . $bp . 'px){#ukgi-popup{display:none !important}}';
        }
        if ($o['devices'] === 'sp') {
            return '@media (min-width:' . ($bp + 1) . 'px){#ukgi-popup{display:none !important}}';
        }
        return '';
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
        $device_css = $this->device_css($o);
        ?>
        <?php if ($device_css) : ?><style id="ukgi-popup-css"><?php echo $device_css; ?></style><?php endif; ?>
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
            var th = <?php echo intval($o['scroll_pct']); ?>;
            // ページ全体のどこまで読んだかを % で返す。
            // スクロールできない短いページは「すでに全部見えている」＝100% とみなす。
            function progress(){
                var doc = document.documentElement, body = document.body;
                var h = Math.max(body ? body.scrollHeight : 0, doc.scrollHeight,
                                 body ? body.offsetHeight : 0, doc.offsetHeight);
                var scrollable = h - window.innerHeight;
                if (scrollable <= 0) return 100;
                var y = window.pageYOffset || doc.scrollTop || 0;
                return Math.min(100, y / scrollable * 100);
            }
            function cleanup(){
                window.removeEventListener('scroll', check);
                window.removeEventListener('resize', check);
            }
            function check(){ if (progress() >= th) { show(); cleanup(); } }
            window.addEventListener('scroll', check, {passive:true});
            window.addEventListener('resize', check);
            // 画像の読み込みでページの高さは変わるため、初回判定は読み込み完了後に行う
            // （高さが確定する前に測ると、長い記事でもいきなり出てしまう）
            if (th <= 0) { show(); cleanup(); }
            else if (document.readyState === 'complete') { check(); }
            else { window.addEventListener('load', check); }
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
