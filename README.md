# スライドインポップアップ（WordPressプラグイン）

右下スライドイン型のポップアップ。画像モード（画像＋リンク）と
ショートコードモード（任意HTML）を切り替えられる。
ミカタ株式会社の資産。fudosan-uru.jp 向けに作ったが業種依存の処理は無く、
他サイトにもそのまま入れられる（v1.2.0で表示名を汎用にした）。

**フォルダ名・オプションキー・ページスラッグは `uru-kagi-popup` / `ukgi_*` のまま。**
表示名を変えてもここを変えないのは、変えると既存サイトの自動更新が切れて
設定も引き継がれないため。

- プラグイン本体: `uru-kagi-popup/uru-kagi-popup.php`（単一ファイル）
- 自動更新チェッカー: `uru-kagi-popup/includes/plugin-updater.php`
- 配布zip: `uru-kagi-popup.zip`（`build.py` が生成）
- 配信元: https://github.com/yoshimucom-gif/uru-kagi-popup-plugin

識別子は `UKGI_*` / `ukgi_*`、設定は単一オプション `ukgi_popup_options`、
設定画面は「設定 → ポップアップ」。

## 更新の出し方

```
py build.py 1.0.2 "・○○を修正しました"
git add -A && git commit -m "v1.0.2" && git push
```

これだけで、プラグインを入れた全サイトの管理画面に「更新可能」バッジが出る。
WordPress は最大12時間ごとに更新チェックするため、すぐ見たい場合は
「ダッシュボード → 更新」の「もう一度確認する」を押す。

`build.py` がやること:

1. `uru-kagi-popup.php` の `Version:` ヘッダーと `UKGI_VER` を書き換える
2. `update.json` の `version` と `changelog` を書き換える（changelogは上書き）
3. `uru-kagi-popup.zip` を再生成する（アーカイブ内パスは常に `/` 区切り。
   Windows の `Compress-Archive` は `\` 区切りのzipを作り、WPが正しく展開できない）

## 検証

- `settings_test.php` … 保存（sanitize）と設定画面の描画
- `updater_test.php` … 実際のGitHubに接続して更新通知が出るかを確認
- `render_preview.php` … `render()` と `settings_page()` の実出力をHTMLに書き出す
  （`php -n render_preview.php preview/popup30.html <表示%> [段落数]`。`-admin.html` も一緒に出る）

書き出し先の `preview/` は git 管理外。表示タイミングをブラウザで見るときは
Claude Code の preview `popup-preview`（`~/.claude/launch.json`・ポート4408）が
このフォルダを配信する。

```
C:\Users\yoshi\php-portable\php82\php.exe -n settings_test.php
C:\Users\yoshi\php-portable\php82\php.exe -n -d extension_dir="C:\Users\yoshi\php-portable\php82\ext" -d extension=curl -d extension=openssl updater_test.php
```

## 設計上の決めごと

- **PC/スマホの出し分けは `wp_is_mobile()` を使わず、画面幅のメディアクエリで行う**。
  `wp_is_mobile()` はサーバー側でUAを見るため、ページキャッシュがあると
  PC向けに生成したHTMLがそのままスマホにも配られて出し分けが壊れる。
  CSSならブラウザ側で判定されるのでキャッシュされても正しい。
- **表示タイミングはpxでなく「ページを何%読んだか」**。記事の長さがバラバラでも
  同じ体感で出せるため（pxだと長い記事では上のほう、短い記事では出ないまま終わる）。
  スクロールできない短いページは「すでに全部見えている」＝100%とみなして表示する。
- **初回判定は `load` 後**。画像の読み込みでページ高さが変わるため、確定前に測ると
  長い記事でもいきなり出てしまう。
- **ポップアップ本体のCSSはすべてインラインstyle**。テーマのCSSに
  上書きされて潰れる事故を避けるため（相場プラグインで2回やられている）。
- **閉じた記録は sessionStorage**。localStorage だと二度と出せなくなる。
  出し直したいときのために「表示バージョン」を用意してある。
- **ショートコード/HTML欄は `unfiltered_html` 権限がある管理者のみ生タグ保存**。
  それ以外は `wp_kses_post` を通す。
