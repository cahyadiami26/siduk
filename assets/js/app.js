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
