<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mailer.php';

$page_title = 'Formulir Permohonan';
$jenis_list = rows("SELECT * FROM jenis_permohonan WHERE aktif = 1 ORDER BY urutan");

$errors = [];
$old = ['nama'=>'','nik'=>'','email'=>'','no_hp'=>'','jenis_id'=>(int)get('jenis'), 'tgl_verifikasi'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit') {
    csrf_verify();
    $old['nama']   = post('nama');
    $old['nik']    = post('nik');
    $old['email']  = post('email');
    $old['no_hp']  = post('no_hp');
    $old['jenis_id'] = (int)post('jenis_id');
    $old['tgl_verifikasi'] = post('tgl_verifikasi');

    // Step 1 validasi
    if ($old['nama'] === '') $errors[] = 'Nama lengkap wajib diisi.';
    if (!valid_nik($old['nik'])) $errors[] = 'NIK wajib 16 digit angka.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $old['no_hp'])) $errors[] = 'Nomor HP tidak valid.';
    if (!captcha_check(post('captcha'))) $errors[] = 'Kode captcha salah. Silakan coba lagi.';

    // Step 2
    $jenis = $old['jenis_id'] ? row("SELECT * FROM jenis_permohonan WHERE id = ? AND aktif = 1", [$old['jenis_id']]) : null;
    if (!$jenis) $errors[] = 'Pilih jenis permohonan yang valid.';

    $fields = $jenis ? rows("SELECT * FROM field_isian WHERE jenis_id = ? ORDER BY urutan", [$jenis['id']]) : [];
    $syarats = $jenis ? rows("SELECT * FROM syarat_dokumen WHERE jenis_id = ? ORDER BY urutan", [$jenis['id']]) : [];

    // Step 3: data isian dinamis + upload
    $dataIsian = [];
    if ($jenis) {
        foreach ($fields as $f) {
            $v = post('f_' . $f['nama_field']);
            if ($f['wajib'] && $v === '') $errors[] = 'Isian "' . $f['label'] . '" wajib diisi.';
            $dataIsian[$f['nama_field']] = $v;
        }
    }

    // Step 4: tanggal verifikasi harus tersedia kuota
    $tgl_ok = false;
    if ($jenis && $old['tgl_verifikasi']) {
        $hari_minggu = val("SELECT COUNT(*) FROM loket WHERE jenis='penerimaan' AND aktif=1") > 0;
        $ts = strtotime($old['tgl_verifikasi']);
        if ($ts && $ts >= strtotime('tomorrow')) {
            if (kuota_sisa((int)$jenis['id'], $old['tgl_verifikasi']) > 0) $tgl_ok = true;
            else $errors[] = 'Kuota tanggal terpilih sudah penuh.';
        } else $errors[] = 'Tanggal verifikasi tidak valid (minimal besok).';
    } elseif ($jenis) {
        $errors[] = 'Pilih tanggal verifikasi berkas.';
    }

    // Proses upload syarat
    $uploads = []; // syarat_id => path
    $upload_err = false;
    if (!empty($_FILES['syarat']) && is_array($_FILES['syarat']['name'])) {
        foreach ($_FILES['syarat']['name'] as $sid => $nm) {
            if (($_FILES['syarat']['error'][$sid] ?? 4) === UPLOAD_ERR_OK) {
                $tmp = [
                    'name' => $nm, 'type' => $_FILES['syarat']['type'][$sid],
                    'tmp_name' => $_FILES['syarat']['tmp_name'][$sid],
                    'error' => 0, 'size' => $_FILES['syarat']['size'][$sid],
                ];
                $path = handle_upload($tmp, 'dokumen');
                if ($path) $uploads[(int)$sid] = $path;
                else { $errors[] = 'File "' . $nm . '" ditolak (format PDF/JPG/PNG maks 4MB).'; $upload_err = true; }
            }
        }
    }
    if ($jenis) {
        foreach ($syarats as $s) {
            if ($s['wajib'] && empty($uploads[(int)$s['id']])) {
                $errors[] = 'Syarat "' . $s['nama_syarat'] . '" wajib diunggah.';
            }
        }
    }

    if (!$errors) {
        // Nomor urut = posisi dalam antrean hari itu utk jenis ini
        $terpakai = (int)val("SELECT COUNT(*) FROM permohonan WHERE jenis_id = ? AND tgl_verifikasi = ? AND status != 'dibatalkan'",
                             [(int)$jenis['id'], $old['tgl_verifikasi']]);
        $nomor_urut = $terpakai + 1;
        $loket = pick_loket((int)$jenis['id'], 'penerimaan');
        $nomor = generate_nomor_pendaftaran($jenis['kode']);
        $token = bin2hex(random_bytes(20));

        q("INSERT INTO permohonan (nomor_pendaftaran, token, jenis_id, nama, nik, email, no_hp, data_isian,
                                   tgl_daftar, jam_daftar, tgl_verifikasi, nomor_urut, loket_id, status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'menunggu_verifikasi')",
          [$nomor, $token, (int)$jenis['id'], $old['nama'], $old['nik'], $old['email'], $old['no_hp'],
           json_encode($dataIsian, JSON_UNESCAPED_UNICODE), today(), date('H:i:s'),
           $old['tgl_verifikasi'], $nomor_urut, $loket ? (int)$loket['id'] : null]);
        $pid = ins_id();

        foreach ($syarats as $s) {
            if (!empty($uploads[(int)$s['id']])) {
                $orig = '';
                foreach ($_FILES['syarat']['name'] as $k => $v) if ((int)$k === (int)$s['id']) $orig = $v;
                q("INSERT INTO upload_syarat (permohonan_id, syarat_id, nama_syarat, file_path, original_name) VALUES (?,?,?,?,?)",
                  [$pid, (int)$s['id'], $s['nama_syarat'], $uploads[(int)$s['id']], $orig]);
            }
        }

        log_activity('Pengajuan permohonan baru oleh warga', 'permohonan',
                     "$nomor - {$jenis['nama']} - {$old['nama']}");

        // Email konfirmasi
        $link = base_url() . '/cek-permohonan.php?nomor=' . urlencode($nomor) . '&token=' . $token;
        $mailData = [
            'nomor' => $nomor, 'nama' => $old['nama'], 'nik' => $old['nik'],
            'tgl_daftar' => today(), 'tgl_verifikasi' => $old['tgl_verifikasi'],
            'nomor_urut' => $nomor_urut, 'loket' => $loket ? $loket['nama'] . ' (' . $loket['nomor'] . ')' : '-',
            'data' => array_combine(array_map(fn($f) => $f['label'], $fields),
                                    array_map(fn($f) => $dataIsian[$f['nama_field']], $fields)),
            'syarat' => array_values(array_map(fn($s) => ['nama_syarat' => $s['nama_syarat']], $syarats)),
        ];
        $sent = send_mail($old['email'], 'Konfirmasi Pendaftaran ' . $nomor . ' — ' . setting('nama_dinas_short'),
                          mail_body_pendaftaran($mailData, $jenis, $link));
        $_SESSION['pendaftaran_sukses'] = ['id' => $pid, 'email_sent' => $sent];
        redirect('permohonan-sukses.php');
    }
}

/* Ketersediaan kuota 14 hari ke depan utk jenis terpilih (endpoint AJAX) */
if (get('ajax') === 'kuota') {
    header('Content-Type: application/json');
    $jid = (int)get('jenis');
    $jenisA = $jid ? row("SELECT * FROM jenis_permohonan WHERE id = ? AND aktif = 1", [$jid]) : null;
    if (!$jenisA) exit(json_encode([]));
    $out = [];
    for ($i = 1; $i <= 14; $i++) {
        $d = date('Y-m-d', strtotime("+$i day"));
        $out[] = ['tanggal' => $d, 'sisa' => kuota_sisa((int)$jenisA['id'], $d)];
    }
    exit(json_encode($out));
}

include __DIR__ . '/app/partials/header_public.php';
?>
<div class="public-wrap">
  <div style="text-align:center;margin-top:10px">
    <h2 class="mt0 mb0" style="font-size:1.5rem">Formulir Permohonan Dokumen Kependudukan</h2>
    <p class="muted small mt0">Lengkapi 5 langkah berikut untuk mengajukan permohonan.</p>
  </div>

  <div class="steps">
    <div class="step" data-n="1"><span class="n">1</span> Identitas</div>
    <div class="step" data-n="2"><span class="n">2</span> Jenis Layanan</div>
    <div class="step" data-n="3"><span class="n">3</span> Data &amp; Syarat</div>
    <div class="step" data-n="4"><span class="n">4</span> Jadwal Verifikasi</div>
    <div class="step" data-n="5"><span class="n">5</span> Konfirmasi</div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><div><b>Terdapat kesalahan:</b><ul style="margin:6px 0 0;padding-left:18px">
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" onsubmit="return submitWizard(this)" action="permohonan.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="submit">
    <input type="hidden" name="tgl_verifikasi" id="tgl_verifikasi" value="<?= e($old['tgl_verifikasi']) ?>">

    <!-- STEP 1 -->
    <div class="card wstep" data-step="1">
      <div class="hd"><h5>Langkah 1 — Data Identitas Pemohon</h5></div>
      <div class="bd">
        <div class="form-row">
          <div class="fgroup"><label class="flbl req">Nama Lengkap</label>
            <input class="inp" name="nama" data-req="1" value="<?= e($old['nama']) ?>" required maxlength="100"></div>
          <div class="fgroup"><label class="flbl req">NIK (16 digit)</label>
            <input class="inp" name="nik" data-req="1" pattern="\d{16}" maxlength="16" inputmode="numeric" value="<?= e($old['nik']) ?>" required></div>
          <div class="fgroup"><label class="flbl req">Email</label>
            <input class="inp" type="email" name="email" data-req="1" value="<?= e($old['email']) ?>" required>
            <div class="hint">Bukti pendaftaran akan dikirim ke email ini.</div></div>
          <div class="fgroup"><label class="flbl req">Nomor HP / WhatsApp</label>
            <input class="inp" name="no_hp" data-req="1" value="<?= e($old['no_hp']) ?>" required placeholder="08xxxxxxxxxx"></div>
          <div class="fgroup"><label class="flbl req">Captcha</label>
            <div class="flex">
              <input class="inp" name="captcha" data-req="1" style="width:130px" maxlength="5" required autocomplete="off" placeholder="Kode">
              <img src="captcha.php" onclick="refreshCaptcha(this)" style="height:44px;border-radius:8px;cursor:pointer;border:1px solid var(--line)" alt="captcha" title="Klik untuk mengganti">
              <button type="button" class="btn btn-outline btn-sm" onclick="refreshCaptcha(this.previousElementSibling)">↻</button>
            </div></div>
        </div>
        <div style="text-align:right"><button type="button" class="btn btn-primary" onclick="nextStep(1)">Lanjut → Jenis Layanan</button></div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="card wstep" data-step="2" style="display:none">
      <div class="hd"><h5>Langkah 2 — Pilih Jenis Permohonan</h5></div>
      <div class="bd">
        <input type="hidden" name="jenis_id" id="jenis_id" value="<?= (int)$old['jenis_id'] ?>">
        <div class="jenis-grid">
          <?php foreach ($jenis_list as $j): ?>
            <div class="jenis-card <?= (int)$old['jenis_id'] === (int)$j['id'] ? 'sel' : '' ?>"
                 onclick="pickJenis(this, <?= (int)$j['id'] ?>); loadKuota()">
              <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6M7 4h10l4 4v12a1 1 0 01-1 1H4a1 1 0 01-1-1V8l4-4z"/></svg></div>
              <b><?= e($j['nama']) ?></b>
              <p><?= e($j['deskripsi']) ?></p>
              <span class="chip" style="margin-top:8px;display:inline-block">Kuota: <?= (int)$j['kuota_per_hari'] ?> /hari</span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="flex spread" style="margin-top:16px">
          <button type="button" class="btn btn-outline" onclick="gotoStep(1)">← Kembali</button>
          <button type="button" class="btn btn-primary" onclick="if(validateStep(2)){ if(!$id('jenis_id').value){alert('Pilih jenis permohonan');}else{gotoStep(3);} }">Lanjut → Data &amp; Syarat</button>
        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="card wstep" data-step="3" style="display:none">
      <div class="hd"><h5>Langkah 3 — Isian Data &amp; Unggah Syarat Dokumen</h5></div>
      <div class="bd">
        <div id="dinamis-area">
          <div class="empty">Pilih jenis permohonan pada langkah 2 terlebih dahulu…</div>
        </div>
        <div class="flex spread" style="margin-top:16px">
          <button type="button" class="btn btn-outline" onclick="gotoStep(2)">← Kembali</button>
          <button type="button" class="btn btn-primary" onclick="nextStep(3)">Lanjut → Jadwal Verifikasi</button>
        </div>
      </div>
    </div>

    <!-- STEP 4 -->
    <div class="card wstep" data-step="4" style="display:none">
      <div class="hd"><h5>Langkah 4 — Pilih Tanggal Verifikasi Berkas</h5></div>
      <div class="bd">
        <p class="small muted mt0">Tanggal yang tersedia menyesuaikan kuota pemohon harian per jenis layanan. Harap datang pada tanggal terpilih dengan membawa berkas asli.</p>
        <div id="date-area" class="date-grid"><div class="empty">Muat jadwal…</div></div>
        <div class="flex spread" style="margin-top:16px">
          <button type="button" class="btn btn-outline" onclick="gotoStep(3)">← Kembali</button>
          <button type="button" class="btn btn-primary" onclick="if(!$id('tgl_verifikasi').value){alert('Pilih tanggal verifikasi');}else{gotoStep(5);renderReview();}">Lanjut → Konfirmasi</button>
        </div>
      </div>
    </div>

    <!-- STEP 5 -->
    <div class="card wstep" data-step="5" style="display:none">
      <div class="hd"><h5>Langkah 5 — Periksa Ulang &amp; Kirim Permohonan</h5></div>
      <div class="bd">
        <div id="review-box" class="box"></div>
        <div class="alert alert-info" style="margin-top:14px">Dengan mengirim formulir ini Anda menyatakan data dan dokumen yang diberikan adalah benar dan dapat dipertanggungjawabkan.</div>
        <div class="flex spread">
          <button type="button" class="btn btn-outline" onclick="gotoStep(4)">← Kembali</button>
          <button type="submit" class="btn btn-gold" style="padding:12px 26px">✓ Kirim Permohonan</button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
const JENIS_DATA = <?= json_encode([
    'fields' => array_reduce($jenis_list, function($carry, $j){
        $carry[$j['id']] = rows("SELECT nama_field,label,tipe,options,wajib FROM field_isian WHERE jenis_id=? ORDER BY urutan", [$j['id']]);
        return $carry; }, []),
    'syarat' => array_reduce($jenis_list, function($carry, $j){
        $carry[$j['id']] = rows("SELECT id,nama_syarat,deskripsi,wajib FROM syarat_dokumen WHERE jenis_id=? ORDER BY urutan", [$j['id']]);
        return $carry; }, []),
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

let kuotaCache = {};

function esc(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

function pickJenis(el, id){
  document.querySelectorAll('.jenis-card').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');
  $id('jenis_id').value = id;
  renderDinamis(id);
}

function renderDinamis(jid){
  const area = $id('dinamis-area');
  const f = JENIS_DATA.fields[jid] || [], s = JENIS_DATA.syarat[jid] || [];
  let html = '<h5>Data Permohonan</h5><div class="form-row">';
  for(const x of f){
    const req = x.wajib==1;
    html += `<div class="fgroup"><label class="flbl ${req?'req':''}">${esc(x.label)}</label>`;
    const nm = `name="f_${esc(x.nama_field)}" ${req?'data-req="1"':''}`;
    if(x.tipe==='select'){
      html += `<select class="inp" ${nm}>${req?'<option value="">-- pilih --</option>':''}`;
      (x.options||'').split(',').forEach(o=>{ o=o.trim(); if(o) html+=`<option>${esc(o)}</option>`; });
      html += '</select>';
    } else if(x.tipe==='textarea'){
      html += `<textarea class="inp" rows="3" ${nm}></textarea>`;
    } else {
      const t = x.tipe==='number'?'number':(x.tipe==='date'?'date':'text');
      html += `<input class="inp" type="${t}" ${nm}>`;
    }
    html += '</div>';
  }
  html += '</div><h5 style="margin-top:18px">Unggah Syarat Dokumen <span class="small muted">(PDF/JPG/PNG maks 4 MB)</span></h5>';
  for(const y of s){
    const req = y.wajib==1;
    html += `<div class="upload-item">
      <label class="flbl ${req?'req':''}" style="margin-bottom:4px">${esc(y.nama_syarat)}</label>
      ${y.deskripsi?`<div class="hint" style="margin:0 0 6px">${esc(y.deskripsi)}</div>`:''}
      <input type="file" name="syarat[${y.id}]" accept=".pdf,.jpg,.jpeg,.png" onchange="previewUpload(this, this.nextElementSibling)" ${req?'data-req="1"':''} style="font-size:.85rem">
      <span class="small muted">Belum ada file dipilih</span></div>`;
  }
  area.innerHTML = html;
  // required file validation via data-req handled custom: file inputs need different check
  area.querySelectorAll('input[type=file][data-req]').forEach(inp=>{
    inp.removeAttribute('data-req');
    inp.dataset.reqFile = '1';
  });
}

// override validateStep utk support file required & select
const _validateStep = validateStep;
validateStep = function(n){
  const stepEl = document.querySelector('.wstep[data-step="'+n+'"]');
  let ok = _validateStep(n);
  stepEl.querySelectorAll('[data-req-file="1"]').forEach(el=>{
    if(!el.files.length){ el.style.borderColor='#dc2626'; ok=false; } else el.style.borderColor='';
  });
  if(!ok) alert('Lengkapi isian / unggahan yang ditandai merah.');
  return ok;
};

async function loadKuota(){
  const jid = $id('jenis_id').value;
  if(!jid) return;
  const area = $id('date-area');
  area.innerHTML = '<div class="empty">Memuat jadwal…</div>';
  try{
    if(!kuotaCache[jid]){
      const r = await fetch('permohonan.php?ajax=kuota&jenis='+jid);
      kuotaCache[jid] = await r.json();
    }
    const days = kuotaCache[jid];
    let html='';
    for(const d of days){
      const dt = new Date(d.tanggal+'T00:00:00');
      const hari = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][dt.getDay()];
      const bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][dt.getMonth()];
      const full = d.sisa<=0;
      html += `<div class="date-card ${full?'full':''} ${$id('tgl_verifikasi').value===d.tanggal?'sel':''}"
        onclick="pickDate(this,'${d.tanggal}')">
        <div class="dw">${hari}, ${dt.getDate()} ${bln}</div>
        <div class="dd">${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}</div>
        <div class="quota">${full?'PENUH':'Sisa kuota: '+d.sisa}</div></div>`;
    }
    area.innerHTML = html;
  }catch(e){ area.innerHTML='<div class="empty">Gagal memuat jadwal.</div>'; }
}

function renderReview(){
  const jid = $id('jenis_id').value;
  const jcard = document.querySelector('.jenis-card.sel b');
  const f = JENIS_DATA.fields[jid] || [];
  let html = `<table class="grid"><tr><th>Pemohon</th><td>${esc(document.querySelector('[name=nama]').value)}</td></tr>
   <tr><th>NIK</th><td>${esc(document.querySelector('[name=nik]').value)}</td></tr>
   <tr><th>Email</th><td>${esc(document.querySelector('[name=email]').value)}</td></tr>
   <tr><th>No. HP</th><td>${esc(document.querySelector('[name=no_hp]').value)}</td></tr>
   <tr><th>Jenis Permohonan</th><td>${jcard?esc(jcard.textContent):'-'}</td></tr>`;
  for(const x of f){
    const el = document.querySelector(`[name="f_${x.nama_field}"]`);
    html += `<tr><th>${esc(x.label)}</th><td>${el?esc(el.value):'-'}</td></tr>`;
  }
  html += `<tr><th>Tanggal Verifikasi</th><td><b>${esc($id('tgl_verifikasi').value)}</b></td></tr></table>`;
  $id('review-box').innerHTML = html;
}

// init: jika jenis sudah terisi (halaman ulang krn error), render dinamis + fokus step
window.addEventListener('DOMContentLoaded', ()=>{
  const jid = $id('jenis_id').value;
  if(jid){ renderDinamis(jid); }
  const sel = document.querySelector('.jenis-card.sel');
  if(sel && jid){ /* tetap di step 1 agar captcha diisi ulang */ }
  gotoStep(1);
});
</script>
<?php include __DIR__ . '/app/partials/footer_public.php'; ?>
