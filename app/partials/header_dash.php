<?php
require_once __DIR__ . '/../includes/helpers.php';
$u = current_user();
$page = basename($_SERVER['PHP_SELF']);
$logoRel = setting('logo');

/** Menu dashboard berdasarkan role */
$menu = [
  'Utama' => [
    'dashboard.php' => ['Beranda', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10'],
  ],
  'Pelayanan' => [
    'pemrosesan.php' => ['Pemrosesan Permohonan', 'M9 12h6m-6 4h6M7 4h10l4 4v12a1 1 0 01-1 1H4a1 1 0 01-1-1V8l4-4z'],
    'penyerahan.php' => ['Penyerahan Dokumen', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
    'log-aktivitas.php' => ['Log Aktivitas', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
  ],
  'Pengaturan' => [
    'pengaturan-aplikasi.php' => ['Pengaturan Aplikasi', 'M10.3 4.3c.4-1.7 2.9-1.7 3.3 0a1.7 1.7 0 003 1 1.7 1.7 0 012.4 2.4 1.7 1.7 0 001 3c1.7.4 1.7 2.9 0 3.3a1.7 1.7 0 00-1 3 1.7 1.7 0 01-2.4 2.4 1.7 1.7 0 00-3 1c-.4 1.7-2.9 1.7-3.3 0a1.7 1.7 0 00-3-1 1.7 1.7 0 01-2.4-2.4 1.7 1.7 0 00-1-3c-1.7-.4-1.7-2.9 0-3.3a1.7 1.7 0 001-3A1.7 1.7 0 016.9 5.3a1.7 1.7 0 003-1z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    'pengguna.php' => ['Pengaturan Pengguna', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-4a3 3 0 11-6 0'],
    'jenis-permohonan.php' => ['Jenis Permohonan', 'M4 6h16M4 12h16M4 18h7'],
    'loket.php' => ['Pengaturan Loket', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
  ],
];
$allow = [
  'pemrosesan.php' => ['petugas','admin','kepala_dinas','kepala_bidang'],
  'penyerahan.php' => ['petugas','admin','kepala_dinas','kepala_bidang'],
  'log-aktivitas.php' => ['admin','kepala_dinas','kepala_bidang','petugas'],
  'pengaturan-aplikasi.php' => ['admin','kepala_dinas','kepala_bidang'],
  'pengguna.php' => ['admin','kepala_dinas'],
  'jenis-permohonan.php' => ['admin','kepala_dinas','kepala_bidang'],
  'loket.php' => ['admin','kepala_dinas','kepala_bidang'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Dashboard') ?> — <?= e(setting('nama_dinas_short')) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="assets/img/favicon.svg">
<script src="assets/js/app.js"></script>
<script>window.BASE_URL='';</script>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <?php if ($logoRel): ?><img src="uploads/<?= e($logoRel) ?>" alt="logo">
      <?php else: ?><span class="ph"><?= e(substr(preg_replace('/[^A-Za-z]/','',setting('nama_dinas_short')),0,2)) ?: 'DK' ?></span><?php endif; ?>
      <span><b><?= e(setting('nama_dinas_short')) ?></b><span>SIDUK · Pelayanan</span></span>
    </div>
    <nav class="menu">
      <?php foreach ($menu as $grp => $items): $shown = false; foreach ($items as $href => $it) if (!isset($allow[$href]) || in_array($u['role'], $allow[$href])) $shown = true; ?>
        <?php if ($shown): ?><div class="grp"><?= e($grp) ?></div>
          <?php foreach ($items as $href => $it):
              if (isset($allow[$href]) && !in_array($u['role'], $allow[$href])) continue; ?>
            <a href="<?= e($href) ?>" class="<?= $page === $href ? 'active' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $it[1] ?>"/></svg>
              <?= e($it[0]) ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="grp">Tautan</div>
      <a href="index.php" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Halaman Publik
      </a>
    </nav>
    <div class="sidebar-foot">&copy; <?= date('Y') ?> <?= e(setting('nama_dinas_short')) ?></div>
  </aside>
  <div class="main">
    <div class="topbar">
      <h4><?= e($page_title ?? 'Dashboard') ?></h4>
      <div class="userchip">
        <div style="text-align:right;line-height:1.25">
          <b style="font-size:.86rem"><?= e($u['nama']) ?></b><br>
          <span class="small muted"><?= e(role_label($u['role'])) ?></span>
        </div>
        <span class="avatar"><?= e(strtoupper(substr($u['nama'], 0, 1))) ?></span>
        <a class="btn btn-outline btn-sm" href="logout.php">Keluar</a>
      </div>
    </div>
    <div class="content">
      <?php foreach (flash_get() as $f): ?>
        <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
