<?php
// サーバ用は config.php、ローカルは config.local.php にコピーして値を設定する（Git管理外）
return [
    // 準備サイト・ローカルは SQLite、本番は MySQL を想定（どちらでも動く）
    'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/../storage/db/reservation.sqlite'],
    // 'db' => ['driver' => 'mysql', 'host' => 'mysql80.example.jp', 'name' => 'DB名', 'user' => 'ユーザー', 'pass' => 'パスワード'],
    'base_path' => '/reservation-signage',   // 公開URLのサブディレクトリ（ルート直下なら ''）
    'debug' => false,
    'site' => ['name' => 'ホテルニューイタヤ 予約管理', 'hotel' => 'HOTEL NEW ITAYA'],
];
