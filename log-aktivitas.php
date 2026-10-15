<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();

$modul  = get('modul');
$cari   = get('cari');
$tgl    = get('tgl');

$where = []; $params = [];
if ($modul !== '') { $where[] = "modul = ?"; $params[] = $modul; }
if ($cari !== '') { $where[] = "(aktivitas LIKE ? OR keterangan LIKE ? OR user_name LIKE ?)"; $l = "%$cari%"; array_push($params, $l, $l, $l); }
if ($tgl && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) { $where[] = "DATE(created_at) = ?"; $params[] = $tgl; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalAll = (int)val("SELECT COUNT(*) FROM activity_log");
$perPage = 30;
$totalFiltered = (int)val("SELECT COUNT(*) FROM activity_log $wsql", $params);
$maxPage = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($maxPage, max(1, (int)(get('p') ?: 1)));
$off = ($page - 1) * $perPage;

$rows = rows("SELECT * FROM activity_log $wsql ORDER BY id DESC LIMIT $perPage OFFSET $off", $params);
$moduls = rows("SELECT DISTINCT modul FROM activity_log WHERE modul IS NOT NULL AND modul != '' ORDER BY modul");

$page_title = 'Log Aktivitas';
include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="card">
  <div class="hd"><h5>Catatan Seluruh Aktivitas Sistem</h5>
    <span class="small muted"><?= numfmt($totalFiltered) ?> dari <?= numfmt($totalAll) ?> entri log</span></div>
  <div class="bd">
    <form method="get" class="filterbar" style="margin-bottom:16px">
      <div class="fgroup"><label class="flbl">Modul</label>
        <select class="inp" name="modul">
          <option value="">Semua Modul</option>
          <?php foreach ($moduls as $m): ?><option value="<?= e($m['modul']) ?>" <?= $modul===$m['modul']?'selected':'' ?>><?= e($m['modul']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="fgroup"><label class="flbl">Tanggal</label>
        <input class="inp" type="date" name="tgl" value="<?= e($tgl) ?>"></div>
      <div class="fgroup" style="flex:1;min-width:220px"><label class="flbl">Cari aktivitas / keterangan / pengguna</label>
        <input class="inp" name="cari" value="<?= e($cari) ?>" placeholder="mis. Memverifikasi berkas"></div>
      <div class="fgroup"><button class="btn btn-primary">Terapkan</button>
        <a class="btn btn-outline" href="log-aktivitas.php">Reset</a></div>
    </form>

    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Keterangan</th><th>Modul</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="small" style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
            <td><b class="small"><?= e($r['user_name'] ?: '-') ?></b></td>
            <td class="small"><?= $r['peran'] ? e(role_label($r['peran'])) : '<span class="muted">Publik</span>' ?></td>
            <td><?= e($r['aktivitas']) ?></td>
            <td class="small muted"><?= e(mb_strimwidth($r['keterangan'] ?: '-', 0, 80, '…')) ?></td>
            <td><?= $r['modul'] ? '<span class="chip">' . e($r['modul']) . '</span>' : '-' ?></td>
            <td class="small muted"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada entri log pada filter ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($maxPage > 1): $q = http_build_query(['modul'=>$modul,'tgl'=>$tgl,'cari'=>$cari]); ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap">
      <?php for ($i = 1; $i <= $maxPage; $i++): ?>
        <a class="btn btn-sm <?= $i===$page ? 'btn-primary' : 'btn-outline' ?>"
           href="log-aktivitas.php?<?= e($q) ?>&p=<?= $i ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
