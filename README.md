# 不動産売却のカギ ポップアップ（WordPressプラグイン）

右下スライドイン型のポップアップ。画像モード（画像＋リンク）と
ショートコードモード（任意HTML）を切り替えられる。
ミカタ株式会社の資産（fudosan-uru.jp 系メディア向け）。

- プラグイン本体: `uru-kagi-popup/uru-kagi-popup.php`（単一ファイル）
- 自動更新チェッカー: `uru-kagi-popup/includes/plugin-updater.php`
- 配布zip: `uru-kagi-popup.zip`（`build.py` が生成）
- 配信元: https://github.com/yoshimucom-gif/uru-kagi-popup-plugin

識別子は `UKGI_*` / `ukgi_*`、設定は単一オプション `ukgi_popup_options`、
設定画面は「設定 → カギ ポップアップ」。

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

## 設計上の決めごと

- **ポップアップ本体のCSSはすべてインラインstyle**。テーマのCSSに
  上書きされて潰れる事故を避けるため（相場プラグインで2回やられている）。
- **閉じた記録は sessionStorage**。localStorage だと二度と出せなくなる。
  出し直したいときのために「表示バージョン」を用意してある。
- **ショートコード/HTML欄は `unfiltered_html` 権限がある管理者のみ生タグ保存**。
  それ以外は `wp_kses_post` を通す。
