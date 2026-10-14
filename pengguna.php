<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['admin', 'kepala_dinas']);

$ROLES = ['admin' => 'Administrator', 'kepala_dinas' => 'Kepala Dinas',
          'kepala_bidang' => 'Kepala Bidang', 'petugas' => 'Petugas'];

/* ---------- Aksi POST (CRUD) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = post('aksi');
    $u = current_user();

    if ($aksi === 'simpan') {
        $id       = (int)post('id');
        $nama     = post('nama');
        $nip      = post('nip');
        $jabatan  = post('jabatan');
        $role     = post('role');
        $username = post('username');
        $password = post('password');
        $aktif    = post('aktif') === '1' ? 1 : 0;

        if (!$nama || !$username || !$role) { flash_set('danger', 'Nama, username, dan role wajib diisi.'); redirect('pengguna.php'); }
        if (!isset($ROLES[$role])) { flash_set('danger', 'Role tidak valid.'); redirect('pengguna.php'); }

        // cek username unik
        $exist = row("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
        if ($exist) { flash_set('danger', 'Username sudah dipakai pengguna lain.'); redirect('pengguna.php'); }

        if ($id) {
            if ($u['id'] === $id && !$aktif) { flash_set('warning', 'Anda tidak dapat menonaktifkan akun sendiri.'); $aktif = 1; }
            if ($password !== '') {
                if (strlen($password) < 6) { flash_set('danger', 'Password minimal 6 karakter.'); redirect('pengguna.php'); }
                q("UPDATE users SET nama=?, nip=?, jabatan=?, role=?, username=?, aktif=?, password=? WHERE id=?",
                  [$nama, $nip, $jabatan, $role, $username, $aktif, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                q("UPDATE users SET nama=?, nip=?, jabatan=?, role=?, username=?, aktif=? WHERE id=?",
                  [$nama, $nip, $jabatan, $role, $username, $aktif, $id]);
            }
            log_activity('Mengubah data pengguna', 'Pengguna', "$nama ($username)");
            flash_set('success', 'Pengguna berhasil diperbarui.');
        } else {
            if (strlen($password) < 6) { flash_set('danger', 'Password minimal 6 karakter untuk pengguna baru.'); redirect('pengguna.php'); }
            q("INSERT INTO users (nama, nip, jabatan, role, username, password, aktif) VALUES (?,?,?,?,?,?,?)",
              [$nama, $nip, $jabatan, $role, $username, password_hash($password, PASSWORD_DEFAULT), $aktif]);
            log_activity('Menambah pengguna baru', 'Pengguna', "$nama ($username)");
            flash_set('success', 'Pengguna baru berhasil ditambahkan.');
        }
        redirect('pengguna.php');
    }

    if ($aksi === 'hapus') {
        $id = (int)post('id');
        if ($id === (int)$u['id']) { flash_set('danger', 'Anda tidak dapat menghapus akun sendiri.'); redirect('pengguna.php'); }
        $usr = row("SELECT * FROM users WHERE id = ?", [$id]);
        if ($usr) {
            q("DELETE FROM users WHERE id = ?", [$id]);
            log_activity('Menghapus pengguna', 'Pengguna', "{$usr['nama']} ({$usr['username']})");
            flash_set('success', 'Pengguna berhasil dihapus.');
        }
        redirect('pengguna.php');
    }
}

$edit = null;
if (get('edit')) { $edit = row("SELECT * FROM users WHERE id = ?", [(int)get('edit')]); }

$list = rows("SELECT * FROM users ORDER BY FIELD(role,'admin','kepala_dinas','kepala_bidang','petugas'), nama");
include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="card">
  <div class="hd"><h5>Data Pengguna Sistem</h5>
    <button class="btn btn-primary btn-sm" onclick="openModal('m-user')">+ Tambah Pengguna</button></div>
  <div class="bd">
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Role</th><th>Username</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): ?>
          <tr>
            <td><b><?= e($r['nama']) ?></b><?php if ((int)$r['id'] === (int)$u['id']): ?> <span class="chip">Anda</span><?php endif; ?></td>
            <td class="small"><?= e($r['nip'] ?: '-') ?></td>
            <td class="small"><?= e($r['jabatan'] ?: '-') ?></td>
            <td><span class="badge bg-secondary"><?= e($ROLES[$r['role']] ?? $r['role']) ?></span></td>
            <td><code><?= e($r['username']) ?></code></td>
            <td><?= $r['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="pengguna.php?edit=<?= (int)$r['id'] ?>">Ubah</a>
              <?php if ((int)$r['id'] !== (int)$u['id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus pengguna <?= e($r['nama']) ?>?')">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn-sm">Hapus</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="empty">Belum ada data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal-bg<?= $edit ? ' open' : '' ?>" id="m-user">
  <div class="modal">
    <form method="post">
      <div class="hd"><h5><?= $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h5>
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('m-user')">✕</button></div>
      <div class="bd">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Nama Lengkap <span class="req">*</span></label>
            <input class="inp" name="nama" required value="<?= e($edit['nama'] ?? '') ?>"></div>
          <div class="fgroup"><label class="flbl">NIP</label>
            <input class="inp" name="nip" value="<?= e($edit['nip'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Jabatan</label>
            <input class="inp" name="jabatan" value="<?= e($edit['jabatan'] ?? '') ?>"></div>
          <div class="fgroup"><label class="flbl">Role <span class="req">*</span></label>
            <select class="inp" name="role" required>
              <?php foreach ($ROLES as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($edit['role'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Username <span class="req">*</span></label>
            <input class="inp" name="username" required value="<?= e($edit['username'] ?? '') ?>"></div>
          <div class="fgroup"><label class="flbl">Password <?= $edit ? '(kosongkan jika tidak diubah)' : '*' ?></label>
            <input class="inp" type="password" name="password" <?= $edit ? '' : 'required minlength="6"' ?> placeholder="Minimal 6 karakter"></div>
        </div>
        <div class="fgroup">
          <label style="display:flex;gap:8px;align-items:center;font-size:.9rem">
            <input type="checkbox" name="aktif" value="1" <?= (!$edit || (int)$edit['aktif'] === 1) ? 'checked' : '' ?>> Akun Aktif
          </label>
        </div>
        <div style="text-align:right;margin-top:8px">
          <button type="button" class="btn btn-outline" onclick="closeModal('m-user')">Batal</button>
          <button class="btn btn-primary">Simpan</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
