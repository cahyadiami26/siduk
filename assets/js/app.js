/* SIDUK helpers JS (vanilla) */
function $id(x){return document.getElementById(x);}

// Buka/tutup modal sederhana
function openModal(id){ $id(id).classList.add('open'); }
function closeModal(id){ $id(id).classList.remove('open'); }
document.addEventListener('click', e=>{
  if(e.target.classList && e.target.classList.contains('modal-bg')) e.target.classList.remove('open');
});

// Refresh captcha
function refreshCaptcha(img){ img.src = (window.BASE_URL||'') + 'captcha.php?t=' + Date.now(); }

// Preview file upload
function previewUpload(input, labelEl){
  if(input.files && input.files[0]){
    const f = input.files[0];
    if(f.size > 4*1024*1024){ alert('Ukuran file maksimal 4 MB'); input.value=''; return; }
    labelEl.textContent = '✓ ' + f.name + ' (' + Math.round(f.size/1024) + ' KB)';
    input.closest('.upload-item').classList.add('ok');
  }
}

// Wizard form permohonan: navigasi step via hidden radio + scroll
function gotoStep(n){
  document.querySelectorAll('.wstep').forEach(el=>{
    el.style.display = (el.dataset.step == n) ? '' : 'none';
  });
  document.querySelectorAll('.step').forEach(el=>{
    const s = parseInt(el.dataset.n);
    el.classList.toggle('on', s === n);
    el.classList.toggle('done', s < n);
  });
  window.scrollTo({top:0, behavior:'smooth'});
}

// Pilih kartu jenis / tanggal
function pickJenis(el, id){
  document.querySelectorAll('.jenis-card').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');
  const inp = $id('jenis_id'); if(inp) inp.value = id;
}
function pickDate(el, val){
  if(el.classList.contains('full')) return;
  document.querySelectorAll('.date-card').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');
  $id('tgl_verifikasi').value = val;
}

// Validasi per-step sebelum lanjut
function validateStep(n){
  const stepEl = document.querySelector('.wstep[data-step="'+n+'"]');
  let ok = true;
  stepEl.querySelectorAll('[data-req="1"]').forEach(el=>{
    if(!el.value.trim()){
      el.style.borderColor = '#dc2626'; ok=false;
    } else el.style.borderColor='';
  });
  // validasi nik
  const nik = stepEl.querySelector('input[name="nik"]');
  if(nik && nik.value && !/^\d{16}$/.test(nik.value)){ nik.style.borderColor='#dc2626'; ok=false; alert('NIK harus 16 digit angka'); }
  const email = stepEl.querySelector('input[type="email"]');
  if(email && email.value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)){ email.style.borderColor='#dc2626'; ok=false; alert('Format email tidak valid'); }
  if(!ok) alert('Lengkapi isian yang ditandai merah terlebih dahulu.');
  return ok;
}
function nextStep(n){ if(validateStep(n)) gotoStep(n+1); }

// Submit wizard: kumpulkan data lalu POST
function submitWizard(form){
  if(!$id('tgl_verifikasi').value){ alert('Pilih tanggal verifikasi berkas terlebih dahulu.'); return false; }
  form.loading = true;
  const btn = form.querySelector('button[type=submit]');
  if(btn){ btn.disabled = true; btn.innerHTML = 'Memproses…'; }
  return true;
}

// Toggle field penjamin saat penyerahan diwakilkan
function togglePenjamin(sel){
  const box = $id('box-penjamin');
  if(box) box.style.display = sel.value === 'diwakilkan' ? '' : 'none';
}

/* ---------- Drawer sidebar dashboard (mobile) ---------- */
document.addEventListener('DOMContentLoaded', ()=>{
  const hb = document.querySelector('.hamburger');
  if(hb) hb.addEventListener('click', ()=> document.body.classList.toggle('nav-open'));
  const bd = document.querySelector('.sidebar-backdrop');
  if(bd) bd.addEventListener('click', ()=> document.body.classList.remove('nav-open'));
  document.querySelectorAll('.menu a').forEach(a=> a.addEventListener('click', ()=> document.body.classList.remove('nav-open')));
});

/* ---------- Komponen upload: sumber Kamera / Galeri / Dokumen (HP) ----------
   Membuat <input type=file> tersembunyi per sumber sehingga browser mobile
   menawarkan opsi kamera, galeri foto, atau dokumen secara langsung.     */
const UPL_SOURCES = [
  {key:'kamera', label:'Kamera', capture:'camera', accept:'image/*',
   icon:'<path d="M3 8a2 2 0 012-2h1.5l1-2h5l1 2H19a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/>'},
  {key:'galeri', label:'Galeri', capture:null, accept:'image/*',
   icon:'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M3 17l5-4 4 3 3-3 6 5"/>'},
  {key:'dokumen', label:'Dokumen', capture:null, accept:'application/pdf,image/jpeg,image/png',
   icon:'<path d="M7 3h7l4 4v14H7zM14 3v4h4"/>'},
];

function buildUpload(inp){
  if(inp.dataset.uplBuilt) return;
  inp.dataset.uplBuilt = '1';
  const baseAccept = inp.getAttribute('accept') || '';
  const holder = document.createElement('div');
  inp.after(holder);

  // tombol sumber (hanya tampil via CSS pada layar <=991px / HP)
  let btns = '<div class="upl-sumber">';
  for(const s of UPL_SOURCES){
    btns += `<button type="button" class="upl-btn" data-key="${s.key}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${s.icon}</svg>${s.label}</button>`;
  }
  btns += '</div><div class="upl-thumbs"></div>';
  holder.innerHTML = btns;

  // input file tersembunyi per sumber → trigger native picker kamera/galeri/dokumen
  for(const s of UPL_SOURCES){
    const hidden = document.createElement('input');
    hidden.type = 'file';
    hidden.accept = baseAccept || s.accept;
    if(s.capture) hidden.setAttribute('capture', s.capture);
    hidden.style.display = 'none';
    hidden.addEventListener('change', ()=>{
      if(hidden.files[0]){
        try{ inp.files = hidden.files; }catch(e){ /* Safari lama */ }
        inp.dispatchEvent(new Event('change', {bubbles:true}));
      }
      hidden.value = '';
    });
    holder.appendChild(hidden);
    holder.querySelector(`.upl-btn[data-key="${s.key}"]`).addEventListener('click', ()=> hidden.click());
  }

  // thumbnail gambar terpilih
  inp.addEventListener('change', ()=>{
    const th = holder.querySelector('.upl-thumbs'); th.innerHTML = '';
    for(const f of inp.files){
      if(/^image\//.test(f.type)){
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.onload = ()=> URL.revokeObjectURL(img.src);
        th.appendChild(img);
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  document.querySelectorAll('input[type="file"]').forEach(buildUpload);
  initWorkdayInputs();
  initFormatNomor();
});

/* ---------- Pengaturan format nomor pendaftaran: sisip placeholder + preview live ---------- */
function initFormatNomor(){
  const inp = $id('format_nomor'); if(!inp) return;
  const selKode = $id('contoh_kode'), prev = $id('nomor_preview');
  // mapping placeholder → nilai contoh (kode jenis dipilih admin di dropdown)
  function renderPreview(){
    if(!prev) return;
    const now = new Date(), kode = (selKode ? selKode.value : 'KK');
    const p2 = n => String(n).padStart(2,'0'), p3 = n => String(n).padStart(3,'0'), p4 = n => String(n).padStart(4,'0');
    let out = inp.value
      .replaceAll('{KODE}', kode.toUpperCase())
      .replaceAll('{Kode}', kode.charAt(0) + kode.slice(1).toLowerCase())
      .replaceAll('{kode}', kode.toLowerCase())
      .replaceAll('{yyyy}', now.getFullYear())
      .replaceAll('{yy}', p2(now.getFullYear()%100))
      .replaceAll('{mm}', p2(now.getMonth()+1))
      .replaceAll('{m}',   now.getMonth()+1)
      .replaceAll('{dd}',  p2(now.getDate()))
      .replaceAll('{d}',   now.getDate())
      .replaceAll('{nnnn}', p4(27)).replaceAll('{nnn}', p3(27)).replaceAll('{nn}', p2(27)).replaceAll('{n}', 27);
    prev.textContent = out;
  }
  inp.addEventListener('input', renderPreview);
  if(selKode){ selKode.removeAttribute('onchange'); selKode.addEventListener('change', renderPreview); }
  document.querySelectorAll('.chip.ph').forEach(b=>{
    b.addEventListener('click', ()=>{
      const ph = b.dataset.ph;
      const s = inp.selectionStart ?? inp.value.length, epos = inp.selectionEnd ?? s;
      inp.value = inp.value.slice(0,s) + ph + inp.value.slice(epos);
      inp.focus();
      inp.setSelectionRange(s + ph.length, s + ph.length);
      renderPreview();
    });
  });
  renderPreview();
}
// untuk input yang dirender dinamis (wizard step 3) — hook global
window.initUploadWidgets = function(scope){
  (scope || document).querySelectorAll('input[type="file"]').forEach(buildUpload);
};

/* Input tanggal dengan atribut data-workday: tolak Sabtu/Minggu saat dipilih */
function initWorkdayInputs(){
  document.querySelectorAll('input[type="date"][data-workday]').forEach(inp=>{
    const check = ()=>{
      if(!inp.value) return;
      const d = new Date(inp.value + 'T00:00:00');
      const day = d.getDay(); // 0=Minggu, 6=Sabtu
      if(day === 0 || day === 6){
        inp.setCustomValidity('Tanggal harus hari kerja (Senin–Jumat). Sabtu/Minggu tidak dilayani.');
        let t = d;
        while(t.getDay() === 0 || t.getDay() === 6){ t.setDate(t.getDate()+1); }
        inp.value = t.getFullYear()+'-'+String(t.getMonth()+1).padStart(2,'0')+'-'+String(t.getDate()).padStart(2,'0');
      } else {
        inp.setCustomValidity('');
      }
    };
    inp.addEventListener('change', check);
    inp.addEventListener('input', check);
  });
}
