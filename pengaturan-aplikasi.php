<?php
require_once __DIR__ . '/includes/helpers.php';
require_role(['admin', 'kepala_dinas', 'kepala_bidang']);
$page_title = 'Pengaturan Aplikasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = post('act');
    if ($act === 'umum') {
        foreach (['nama_dinas','nama_dinas_short','alamat','telepon','email','website','quota_global'] as $k) {
            setting_save($k, post($k));
        }
        // format nomor: validasi harus mengandung placeholder counter
        $fmt = post('format_nomor');
        if (!str_contains($fmt, '{n')) $fmt .= '/{nnnn}';
        setting_save('format_nomor', $fmt);
        log_activity('Memperbarui pengaturan aplikasi umum', 'pengaturan');
        flash_set('success', 'Pengaturan umum berhasil disimpan.');
        redirect('pengaturan-aplikasi.php');
    }
    if ($act === 'mail') {
        setting_save('mail_enabled', isset($_POST['mail_enabled']) ? '1' : '0');
        foreach (['mail_host','mail_port','mail_user','mail_enc','mail_from','mail_fromname'] as $k) {
            setting_save($k, post($k));
        }
        if (post('mail_pass') !== '') setting_save('mail_pass', post('mail_pass'));
        log_activity('Memperbarui konfigurasi email (PHPMailer)', 'pengaturan');
        flash_set('success', 'Konfigurasi email disimpan.');
        redirect('pengaturan-aplikasi.php#mail');
    }
    if ($act === 'upload') {
        $sub = post('target') === 'logo' ? 'logo' : 'ttd';
        if (!empty($_FILES['berkas']['name'])) {
            $path = handle_upload($_FILES['berkas'], $sub);
            if ($path) {
                setting_save($sub === 'logo' ? 'logo' : 'ttd_kepala_dinas', $path);
                log_activity('Mengunggah ' . ($sub === 'logo' ? 'logo' : 'tanda tangan kepala dinas'), 'pengaturan');
                flash_set('success', 'Berkas berhasil diunggah.');
            } else flash_set('danger', 'Berkas ditolak (PNG/JPG maks 4MB).');
        } else {
            setting_save($sub === 'logo' ? 'logo' : 'ttd_kepala_dinas', '');
            log_activity('Menghapus ' . ($sub === 'logo' ? 'logo' : 'tanda tangan'), 'pengaturan');
            flash_set('success', 'Berkas dihapus.');
        }
        redirect('pengaturan-aplikasi.php');
    }
    if ($act === 'ttd') {
        setting_save('ttd_kepala_dinas_nama', post('nama'));
        setting_save('ttd_kepala_dinas_nip', post('nip'));
        setting_save('ttd_jabatan', post('jabatan'));
        log_activity('Memperbarui data penanda tangan', 'pengaturan');
        flash_set('success', 'Data penanda tangan disimpan.');
        redirect('pengaturan-aplikasi.php');
    }
}

// Daftar kode jenis permohonan (untuk preview & dropdown contoh) + default = jenis pertama
$jenis_list_all  = q("SELECT kode, nama FROM jenis_permohonan WHERE aktif = 1 ORDER BY nama");
$jenis_first     = $jenis_list_all[0] ?? null;
// Preview contoh nomor: pakai kode jenis permohonan asli dari database (default: yang pertama)
$kode_preview = trim(post('contoh_kode')) ?: ($jenis_first['kode'] ?? 'KK');
$contoh = render_format_nomor(setting('format_nomor'), $kode_preview, 27);
include __DIR__ . '/app/partials/header_dash.php';
?>
<div class="grid-2">
  <div class="card">
    <div class="hd"><h5>Identitas Dinas</h5></div>
    <div class="bd">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="act" value="umum">
        <div class="fgroup"><label class="flbl">Nama Dinas</label>
          <input class="inp" name="nama_dinas" value="<?= e(setting('nama_dinas')) ?>"></div>
        <div class="fgroup"><label class="flbl">Nama Singkat Dinas</label>
          <input class="inp" name="nama_dinas_short" value="<?= e(setting('nama_dinas_short')) ?>"></div>
        <div class="fgroup"><label class="flbl">Alamat Lengkap</label>
          <textarea class="inp" name="alamat" rows="2"><?= e(setting('alamat')) ?></textarea></div>
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Telepon</label><input class="inp" name="telepon" value="<?= e(setting('telepon')) ?>"></div>
          <div class="fgroup"><label class="flbl">Email</label><input class="inp" name="email" value="<?= e(setting('email')) ?>"></div>
          <div class="fgroup"><label class="flbl">Website</label><input class="inp" name="website" value="<?= e(setting('website')) ?>"></div>
          <div class="fgroup"><label class="flbl">Kuota Global / Hari (fallback)</label><input class="inp" type="number" name="quota_global" value="<?= e(setting('quota_global', 100)) ?>"></div>
        </div>
        <div class="fgroup"><label class="flbl">Format Nomor Pendaftaran</label>
          <input class="inp" name="format_nomor" id="format_nomor" value="<?= e(setting('format_nomor')) ?>">
          <div class="hint" style="margin-top:6px">
            Klik placeholder untuk menyisipkan ke kolom format:<br>
            <span class="ph-chips">
              <button type="button" class="chip ph" data-ph="{kode}" title="Kode jenis permohonan, mis. KK, AKTA-LAHIR (huruf kecil)">{kode}</button>
              <button type="button" class="chip ph" data-ph="{Kode}">{Kode}</button>
              <button type="button" class="chip ph" data-ph="{KODE}" title="Kode jenis permohonan (huruf kapital)">{KODE}</button>
              <button type="button" class="chip ph" data-ph="{y}">{y}</button>
              <button type="button" class="chip ph" data-ph="{yy}">{yy}</button>
              <button type="button" class="chip ph" data-ph="{yyyy}">{yyyy}</button>
              <button type="button" class="chip ph" data-ph="{m}">{m}</button>
              <button type="button" class="chip ph" data-ph="{mm}">{mm}</button>
              <button type="button" class="chip ph" data-ph="{d}">{d}</button>
              <button type="button" class="chip ph" data-ph="{dd}">{dd}</button>
              <button type="button" class="chip ph" data-ph="{n}">{n}</button>
              <button type="button" class="chip ph" data-ph="{nn}">{nn}</button>
              <button type="button" class="chip ph" data-ph="{nnn}">{nnn}</button>
              <button type="button" class="chip ph" data-ph="{nnnn}">{nnnn}</button>
            </span>
          </div>
          <div class="form-row" style="align-items:end;margin-top:8px">
            <div class="fgroup" style="flex:0 0 auto;margin-bottom:0">
              <label class="flbl" for="contoh_kode">Contoh kode jenis</label>
              <select class="inp" name="contoh_kode" id="contoh_kode" onchange="this.form.submit()">
                <?php foreach ($jenis_list_all as $jp): ?>
                <option value="<?= e($jp['kode']) ?>" <?= $jp['kode'] === $kode_preview ? 'selected' : '' ?>><?= e($jp['kode']) ?> — <?= e($jp['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fgroup" style="margin-bottom:0">
              <label class="flbl">Preview hasil</label>
              <div class="nomor-preview" id="nomor_preview"><?= e($contoh) ?></div>
            </div>
          </div>
          <div class="hint">Placeholder <code>{kode}</code> otomatis diganti kode jenis permohonan yang dipilih warga saat mendaftar (diset pada Jenis Permohonan).</div>
        </div>
        <button class="btn btn-primary">💾 Simpan Pengaturan Umum</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h5>Logo &amp; Tanda Tangan Kepala Dinas</h5></div>
    <div class="bd">
      <div class="form-row">
        <div>
          <label class="flbl">Logo Dinas</label>
          <?php if (setting('logo')): ?><img src="uploads/<?= e(setting('logo')) ?>" class="imgprev" style="max-height:90px"><br><?php endif; ?>
          <form method="post" enctype="multipart/form-data" style="margin-top:8px">
            <?= csrf_field() ?><input type="hidden" name="act" value="upload"><input type="hidden" name="target" value="logo">
            <input type="file" name="berkas" accept=".png,.jpg,.jpeg" class="upl-native">
            <div class="flex" style="margin-top:8px">
              <button class="btn btn-outline btn-sm">Unggah Logo</button>
              <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus logo?')" name="hapus" value="1">Hapus</button>
            </div>
          </form>
        </div>
        <div>
          <label class="flbl">Tanda Tangan Kepala Dinas (gambar)</label>
          <?php if (setting('ttd_kepala_dinas')): ?><img src="uploads/<?= e(setting('ttd_kepala_dinas')) ?>" class="imgprev" style="max-height:70px"><br><?php endif; ?>
          <form method="post" enctype="multipart/form-data" style="margin-top:8px">
            <?= csrf_field() ?><input type="hidden" name="act" value="upload"><input type="hidden" name="target" value="ttd">
            <input type="file" name="berkas" accept=".png,.jpg,.jpeg" class="upl-native">
            <div class="flex" style="margin-top:8px">
              <button class="btn btn-outline btn-sm">Unggah TTD</button>
              <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus tanda tangan?')">Hapus</button>
            </div>
          </form>
        </div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="act" value="ttd">
        <div class="form-row">
          <div class="fgroup"><label class="flbl">Nama Penanda Tangan</label>
            <input class="inp" name="nama" value="<?= e(setting('ttd_kepala_dinas_nama')) ?>"></div>
          <div class="fgroup"><label class="flbl">NIP</label>
            <input class="inp" name="nip" value="<?= e(setting('ttd_kepala_dinas_nip')) ?>"></div>
          <div class="fgroup"><label class="flbl">Jabatan pada Dokumen</label>
            <input class="inp" name="jabatan" value="<?= e(setting('ttd_jabatan')) ?>"></div>
        </div>
        <button class="btn btn-primary">💾 Simpan Data TTD</button>
      </form>
    </div>
  </div>
</div>

<div class="card" id="mail">
  <div class="hd"><h5>Konfigurasi Email (SMTP — PHPMailer)</h5>
    <span class="chip">Status: <?= setting('mail_enabled') == 1 ? 'SMTP Aktif' : 'Fallback mail() PHP' ?></span></div>
  <div class="bd">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="act" value="mail">
      <label class="flbl" style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
        <input type="checkbox" name="mail_enabled" value="1" <?= setting('mail_enabled')==1?'checked':'' ?>> Aktifkan pengiriman via SMTP
      </label>
      <div class="form-row">
        <div class="fgroup"><label class="flbl">SMTP Host</label><input class="inp" name="mail_host" value="<?= e(setting('mail_host')) ?>"></div>
        <div class="fgroup"><label class="flbl">Port</label><input class="inp" name="mail_port" value="<?= e(setting('mail_port')) ?>"></div>
        <div class="fgroup"><label class="flbl">Enkripsi</label>
          <select class="inp" name="mail_enc">
            <?php foreach (['tls','ssl','none'] as $en): ?><option <?= setting('mail_enc')===$en?'selected':'' ?>><?= $en ?></option><?php endforeach; ?>
          </select></div>
        <div class="fgroup"><label class="flbl">Username SMTP</label><input class="inp" name="mail_user" value="<?= e(setting('mail_user')) ?>"></div>
        <div class="fgroup"><label class="flbl">Password SMTP</label><input class="inp" type="password" name="mail_pass" placeholder="<?= setting('mail_pass') ? '•••••••• (kosongkan jika tidak diubah)' : '' ?>"></div>
        <div class="fgroup"><label class="flbl">From Address</label><input class="inp" name="mail_from" value="<?= e(setting('mail_from')) ?>"></div>
        <div class="fgroup"><label class="flbl">From Name</label><input class="inp" name="mail_fromname" value="<?= e(setting('mail_fromname')) ?>"></div>
      </div>
      <button class="btn btn-primary">💾 Simpan Konfigurasi Email</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/app/partials/footer_dash.php'; ?>
