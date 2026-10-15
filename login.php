<?php
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) redirect('dashboard.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = $_POST['password'] ?? '';
    $u = row("SELECT * FROM users WHERE username = ? AND aktif = 1", [$username]);
    if ($u && password_verify($password, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>$u['id'],'nama'=>$u['nama'],'nip'=>$u['nip'],
                             'jabatan'=>$u['jabatan'],'role'=>$u['role'],'username'=>$u['username']];
        log_activity('Login ke dashboard', 'auth', 'Username: ' . $username);
        $to = $_SESSION['after_login'] ?? 'dashboard.php';
        unset($_SESSION['after_login']);
        redirect($to);
    }
    $err = 'Username / password salah atau akun tidak aktif.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk Petugas — <?= e(setting('nama_dinas_short')) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="assets/img/favicon.svg">
</head>
<body class="login-body">
  <div class="login-side">
    <h2><?= e(setting('nama_dinas')) ?></h2>
    <p style="color:#c9d6ee;line-height:1.7">Sistem Informasi Administrasi Kependudukan — panel pengelolaan permohonan dokumen kependudukan: verifikasi berkas, penjadwalan pengambilan, hingga penyerahan dokumen.</p>
    <p class="small" style="color:#8fa4c6"><?= e(setting('alamat')) ?> · Telp <?= e(setting('telepon')) ?></p>
  </div>
  <div class="login-card">
    <div style="text-align:center;margin-bottom:22px">
      <?php $logo = setting('logo'); if ($logo): ?>
        <img src="uploads/<?= e($logo) ?>" style="height:58px;margin-bottom:10px">
      <?php endif; ?>
      <h3 class="mt0 mb0">Masuk Petugas</h3>
      <p class="small muted mt0">Halaman ini khusus pegawai dinas</p>
    </div>
    <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
    <?php foreach (flash_get() as $f): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
      <div class="fgroup">
        <label class="flbl">Username</label>
        <input class="inp" name="username" required autofocus value="<?= e(post('username')) ?>">
      </div>
      <div class="fgroup">
        <label class="flbl">Password</label>
        <input class="inp" type="password" name="password" required>
      </div>
      <button class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">Masuk ke Dashboard</button>
    </form>
    <p class="small muted" style="text-align:center;margin-top:18px">
      Akun demo: <b>admin / petugas / kadis / kabid</b><br>password: <b>password123</b>
    </p>
    <p style="text-align:center;margin-top:10px"><a href="index.php" class="small">← Kembali ke halaman publik</a></p>
  </div>
</body></html>
