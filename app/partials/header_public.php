<?php
require_once __DIR__ . '/../../includes/helpers.php';
/** Header untuk halaman publik (tanpa login) */
$logoRel = setting('logo');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? APP_TITLE) ?> — <?= e(setting('nama_dinas_short')) ?></title>
<link rel="stylesheet" href="<?= $asset_base ?? '' ?>assets/css/style.css">
<link rel="icon" href="<?= $asset_base ?? '' ?>assets/img/favicon.svg">
<script src="<?= $asset_base ?? '' ?>assets/js/app.js"></script>
</head>
<body>
<div class="pub-header">
  <div class="pub-nav">
    <a class="logo" href="<?= $asset_base ?? '' ?>index.php" style="color:#fff">
      <?php if ($logoRel): ?><img src="<?= $asset_base ?? '' ?>uploads/<?= e($logoRel) ?>" alt="logo"><?php endif; ?>
      <span>
        <span style="display:block;font-size:1.05rem"><?= e(setting('nama_dinas_short')) ?></span>
        <span style="display:block;font-size:.72rem;color:#9fb4d6;font-weight:500"><?= e(APP_TITLE) ?></span>
      </span>
    </a>
    <nav>
      <a class="navlink" href="<?= $asset_base ?? '' ?>index.php">Beranda</a>
      <a class="navlink" href="<?= $asset_base ?? '' ?>permohonan.php">Ajukan Permohonan</a>
      <a class="navlink" href="<?= $asset_base ?? '' ?>cek-permohonan.php">Cek Status</a>
      <a class="navlink btn btn-gold btn-sm" style="margin-left:16px" href="<?= $asset_base ?? '' ?>login.php">Masuk Petugas</a>
    </nav>
  </div>
</div>
<?php foreach (flash_get() as $f): ?>
  <div style="max-width:980px;margin:14px auto 0;padding:0 18px">
    <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
  </div>
<?php endforeach; ?>
