<?php if (!$reservations): ?>
  <p class="empty">予約はありません。</p>
<?php else: ?>
<table class="table">
  <thead><tr><th>時間</th><th>人数</th><th>会場</th><th>グループ名・顧客</th><th>フラグ・注文・メモ</th><th>予約ID</th><th>ステータス</th></tr></thead>
  <tbody>
  <?php foreach ($reservations as $r): ?><?= \App\Core\View::partial('partials/rsv_row', ['r' => $r, 'baseQuery' => $baseQuery ?? [], 'basePath' => $basePath ?? null]) ?><?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
