<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$page_title = 'Beranda Dashboard';

$u = current_user();
$total = (int)val("SELECT COUNT(*) FROM permohonan");
$bln_ini = date('Y-m');
$bulan_ini = (int)val("SELECT COUNT(*) FROM permohonan WHERE DATE_FORMAT(tgl_daftar,'%Y-%m') = ?", [$bln_ini]);
$selesai_bulan = (int)val("SELECT COUNT(*) FROM permohonan WHERE status='selesai' AND DATE_FORMAT(diserahkan_pada,'%Y-%m') = ?", [$bln_ini]);
$menunggu = (int)val("SELECT COUNT(*) FROM permohonan WHERE status='menunggu_verifikasi'");
$vhari = (int)val("SELECT COUNT(*) FROM permohonan WHERE tgl_verifikasi = ? AND status != 'dibatalkan'", [today()]);

$perJenis = rows("SELECT j.nama, COUNT(p.id) AS total FROM jenis_permohonan j
                  LEFT JOIN permohonan p ON p.jenis_id = j.id AND p.status != 'dibatalkan'
                  GROUP BY j.id ORDER BY total DESC");
$maxJ = max(array_map(fn($r) => (int)$r['total'], $perJenis ?: [['total'=>1]]));

$terbaru = rows("SELECT p.*, j.nama AS jenis_nama FROM permohonan p JOIN jenis_permohonan j ON j.id=p.jenis_id
                 ORDER BY p.id DESC LIMIT 5");
$aktivitas = rows("SELECT * FROM activity_log ORDER BY id DESC LIMIT 5");
$pendekatan = rows("SELECT DATE(tgl_daftar) t, COUNT(*) c FROM permohonan WHERE tgl_daftar >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(tgl_daftar) ORDER BY t");
$maxH = max(1, max(array_map(fn($r) => (int)$r['c'], $pendekatan ?: [['c'=>1]])));

include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="stats">
  <div class="stat" style="--dot:#2563eb;--tint:#e8f0fe"><div class="lbl"><i class="dot"></i>Total Permohonan</div>
    <div class="val"><?= numfmt($total) ?></div><div class="sub">Seluruh periode</div></div>
  <div class="stat" style="--dot:#16a34a;--tint:#e7f7ee"><div class="lbl"><i class="dot"></i>Permohonan Bulan Ini</div>
    <div class="val"><?= numfmt($bulan_ini) ?></div><div class="sub"><?= BULAN_ID[(int)date('n')] ?> <?= date('Y') ?></div></div>
  <div class="stat" style="--dot:#c9a227;--tint:#faf3dd"><div class="lbl"><i class="dot"></i>Selesai Bulan Ini</div>
    <div class="val"><?= numfmt($selesai_bulan) ?></div><div class="sub">Dokumen diserahkan</div></div>
  <div class="stat" style="--dot:#dc2626;--tint:#fdecec"><div class="lbl"><i class="dot"></i>Menunggu Verifikasi</div>
    <div class="val"><?= numfmt($menunggu) ?></div><div class="sub">Verifikasi hari ini: <?= $vhari ?> pemohon</div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="hd"><h5>Jumlah Permohonan per Jenis Layanan</h5></div>
    <div class="bd">
      <?php foreach ($perJenis as $r): $w = $maxJ ? round((int)$r['total'] / $maxJ * 100) : 0; ?>
        <div class="hbar-row">
          <div class="text-truncate" title="<?= e($r['nama']) ?>"><?= e($r['nama']) ?></div>
          <div class="hbar"><i style="width:<?= max($w, 2) ?>%"></i></div>
          <div style="text-align:right;font-weight:700;color:var(--navy)"><?= numfmt($r['total']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$perJenis): ?><div class="empty">Belum ada data.</div><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h5>Tren Pendaftaran 7 Hari Terakhir</h5></div>
    <div class="bd">
      <?php $days = []; for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
            foreach ($pendekatan as $d) $days[$d['t']] = (int)$d['c']; ?>
      <div style="display:flex;align-items:flex-end;gap:10px;height:170px;padding-top:10px">
        <?php foreach ($days as $tgl => $c): $h = $maxH ? max(4, round($c / $maxH * 140)) : 4; ?>
          <div style="flex:1;text-align:center">
            <div style="font-size:.72rem;font-weight:700;color:var(--navy)"><?= $c ?></div>
            <div style="height:<?= $h ?>px;background:linear-gradient(180deg,#3b82f6,#1e3a8a);border-radius:6px 6px 0 0;margin-top:2px"></div>
            <div class="small muted" style="font-size:.68rem;margin-top:4px"><?= date('d/m', strtotime($tgl)) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="hd"><h5>5 Permohonan Terbaru</h5><a class="btn btn-outline btn-sm" href="pemrosesan.php">Lihat Semua →</a></div>
    <div class="bd">
      <ul class="list-latest">
        <?php foreach ($terbaru as $t): ?>
          <li>
            <div><b><?= e($t['nomor_pendaftaran']) ?></b><br>
              <span class="small muted"><?= e($t['nama']) ?> — <?= e($t['jenis_nama']) ?></span></div>
            <div style="text-align:right"><?= status_badge($t['status']) ?><br>
              <span class="small muted"><?= tgl_id($t['tgl_daftar']) ?></span></div>
          </li>
        <?php endforeach; ?>
        <?php if (!$terbaru): ?><div class="empty">Belum ada permohonan.</div><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h5>5 Aktivitas Terbaru</h5><a class="btn btn-outline btn-sm" href="log-aktivitas.php">Log Lengkap →</a></div>
    <div class="bd">
      <ul class="list-latest">
        <?php foreach ($aktivitas as $a): ?>
          <li>
            <div><b><?= e($a['aktivitas']) ?></b><br>
              <span class="small muted"><?= e($a['user_name']) ?> (<?= e(role_label($a['peran'])) ?>)<?= $a['keterangan'] ? ' · ' . e($a['keterangan']) : '' ?></span></div>
            <div class="small muted" style="white-space:nowrap"><?= date('d/m H:i', strtotime($a['created_at'])) ?></div>
          </li>
        <?php endforeach; ?>
        <?php if (!$aktivitas): ?><div class="empty">Belum ada aktivitas.</div><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
