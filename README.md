# Reservation-Signage

ホテルニューイタヤ向けの予約管理システム（TableCheck の置き換え）と、館内デジタルサイネージの統合版。

- PHP 8.1 / フレームワークなし / PDO（SQLite・MySQL どちらでも動作）
- 準備サイト: https://jyunbi.sakura.ne.jp/reservation-signage/ （サーバ `~/www/reservation-signage`）

## 画面
| URL | 内容 |
|---|---|
| `/` | ホーム（組数・人数・注文金額・会場未定、ステータス・フラグ別件数） |
| `/chart` | 会場×時間のチャート。空き部分クリックで新規予約 |
| `/list` | 予約リスト（ステータス絞り込み） |
| `/calendar` | 月間カレンダー（朝・昼・夜の組数・人数） |
| `/customers` | 顧客検索・登録・予約履歴 |
| `/reservations/{id}` | 予約詳細・操作履歴（変更前後の差分） |
| `/signage` | サイネージの表示URL・プレビュー・当日の表示内容 |
| `/report` | 宴会ご予約一覧表（PDF=印刷 / Excel=.xlsx）。「記」「種別」は当面空欄 |
| `/settings` | 店舗・会場・フラグ・選択肢・メニュー・利用者（管理者のみ） |

どの画面でも `?new=1`（新規）/ `?edit={id}`（編集）で右側に予約入力欄が開く。

## サイネージ連携
- 表示画面: `signage/venue.html`（旧サイネージの「会場（自動）」表示をそのまま移植。ログイン不要）
- データ: `GET /api/signage/events?date=YYYY-MM-DD&days=N` — 旧 `tablecheck-sync.php` と同じ JSON 形式
  (`events[]` の `time / endTime / venue / location / name / pax / status / flags` など)
- 表示条件: 「サイネージに表示する」がオン、会場あり、ステータスが仮予約・キャンセル・無断キャンセル以外、
  「サイネージ非表示」設定のフラグなし。表示名は看板名 → グループ名 → 顧客名。

## ローカル起動
```
cp src/config/config.sample.php src/config/config.local.php   # base_path を '' に
php tools/install.php src '<admin初期パスワード8文字以上>'
php -S 127.0.0.1:8090 -t src tools/dev-router.php
```
初期データ（店舗・会場・フラグ）は `db/seed.json`。テーブルが空のときだけ投入される。

## 準備サイトへの反映
`./deploy.sh`（サーバをバックアップ → `src/` を転送。サーバの `config/config.php` と `storage/` は上書きしない）
