<?php require __DIR__ . '/lib/layout.php'; page_head('Manage Booking', 'status'); ?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 pt-10">
  <p class="text-[10px] font-bold tracking-widest uppercase text-coral">Booking reference</p>
  <h1 class="font-serif text-4xl mt-2 mb-3">Manage your appointment</h1>
  <p class="text-sm text-ink/70 mb-2">Use the reference and phone number from your booking confirmation to check its status, reschedule, or cancel your appointment.</p>
  <p class="text-sm text-rust mb-6"><b>Late arrival policy:</b> Arriving 15 minutes late may result in your appointment time being forfeited. Payment is collected in-store; no deposit is required.</p>
  <form id="lookup" class="bg-white rounded-lg shadow-sm p-5 grid sm:grid-cols-2 gap-4">
    <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1" for="ref">Reference</label><input id="ref" name="ref" maxlength="8" required class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm uppercase"></div>
    <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1" for="phone">Phone number</label><input id="phone" name="phone" maxlength="30" required class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm"></div>
    <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase tracking-widest mb-1" for="token">Private booking token <span class="font-normal normal-case">(required to reschedule or cancel)</span></label><input id="token" name="token" maxlength="64" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm font-mono"><p class="text-xs text-ink/60 mt-1">This token is shown after booking. Keep it private.</p></div>
    <div class="sm:col-span-2"><button class="bg-coral text-white font-bold uppercase tracking-wider text-xs px-5 py-3 rounded">Find booking</button></div>
  </form>
  <div id="result" class="mt-6" aria-live="polite"></div>
</section>
<script>
const form = document.getElementById('lookup'), result = document.getElementById('result');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const statusClass = s => s === 'confirmed' ? 'bg-navy text-white' : s === 'cancelled' ? 'bg-sand3 text-ink/70' : 'bg-coral/20 text-rust';
async function loadBooking() {
  const q = new URLSearchParams(new FormData(form));
  result.innerHTML = '<p class="text-sm text-ink/60">Checking booking…</p>';
  try {
    const res = await fetch('api/public/booking.php?' + q);
    const data = await res.json();
    if (!res.ok || !data.ok) throw data;
    result.innerHTML = `<div class="bg-white rounded-lg shadow-sm p-5"><div class="flex justify-between gap-3 items-center"><h2 class="font-serif text-2xl">Reference ${esc(data.ref)}</h2><span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${statusClass(data.status)}">${esc(data.status)}</span></div>
      <ul class="mt-4 text-sm space-y-2">${data.items.map(i => `<li>${esc(i.appt_date)} · ${esc(i.start_time.slice(0,5))}–${esc(i.end_time.slice(0,5))} · ${esc(i.service)} with ${esc(i.barber)} at ${esc(i.branch)}</li>`).join('')}</ul>
      ${['pending','confirmed'].includes(data.status) ? '<div class="mt-5 flex flex-wrap gap-3"><button id="reschedule" class="bg-navy text-white text-xs font-bold uppercase tracking-wider rounded px-4 py-2">Reschedule</button><button id="cancel" class="text-xs font-bold uppercase tracking-wider text-rust border border-rust rounded px-4 py-2">Cancel booking</button></div><div id="reschedule-form" class="hidden mt-5 border-t border-sand3 pt-5"></div>' : ''}</div>`;
    const reschedule = document.getElementById('reschedule');
    if (reschedule) reschedule.onclick = () => showReschedule(data);
    const cancel = document.getElementById('cancel');
    if (cancel) cancel.onclick = async () => {
      if (!confirm('Cancel this booking?')) return;
      cancel.disabled = true;
      try {
        if (!form.token.value.trim()) { result.insertAdjacentHTML('afterbegin', '<p class="text-sm text-rust mb-3">Enter your cancellation token first.</p>'); cancel.disabled = false; return; }
        const r = await fetch('api/public/booking.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf]').content}, body:JSON.stringify({...Object.fromEntries(new FormData(form)), action:'cancel'})});
        const d = await r.json(); if (!r.ok || !d.ok) throw d;
        await loadBooking();
      } catch (e) { result.insertAdjacentHTML('afterbegin', `<p class="text-sm text-rust mb-3">${esc(e.error || 'Could not cancel booking')}</p>`); cancel.disabled = false; }
    };
  } catch (e) { result.innerHTML = `<p class="text-sm text-rust">${esc(e.error || 'Booking not found')}</p>`; }
}
async function showReschedule(data) {
  const box = document.getElementById('reschedule-form');
  if (!box) return;
  box.classList.remove('hidden');
  box.innerHTML = '<p class="text-sm text-ink/60">Loading rescheduling options…</p>';
  try {
    const [branches, barbers, services] = await Promise.all([
      fetch('api/public/catalog.php?r=branches').then(r => r.json()),
      fetch('api/public/catalog.php?r=barbers').then(r => r.json()),
      fetch('api/public/catalog.php?r=services').then(r => r.json())
    ]);
    const first = data.items[0], selectedServices = data.items.map(i => i.service_id);
    box.innerHTML = `<form id="reschedule-fields" class="grid sm:grid-cols-2 gap-4">
      <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1">Branch</label><select id="rbranch" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm">${branches.rows.map(b => `<option value="${b.id}">${esc(b.name)}</option>`).join('')}</select></div>
      <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1">Barber</label><select id="rbarber" name="barber_id" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm"></select></div>
      <div class="sm:col-span-2"><label class="block text-[10px] font-bold uppercase tracking-widest mb-1">Services</label><div id="rservices" class="grid sm:grid-cols-2 gap-2">${services.rows.map(s => `<label class="flex gap-2 items-center text-sm"><input type="checkbox" name="service" value="${s.id}" ${selectedServices.includes(+s.id) ? 'checked' : ''}> ${esc(s.name)} (${s.duration_min} min)</label>`).join('')}</div></div>
      <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1" for="rdate">Date</label><input id="rdate" name="appt_date" type="date" min="${new Date().toISOString().slice(0,10)}" max="${new Date(Date.now() + 60 * 864e5).toISOString().slice(0,10)}" value="${esc(first.appt_date)}" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm"></div>
      <div><label class="block text-[10px] font-bold uppercase tracking-widest mb-1">Available time</label><select id="rtime" name="start_time" class="w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm"><option value="">Choose a date first</option></select></div>
      <p id="rerr" class="sm:col-span-2 text-sm text-rust"></p>
      <div class="sm:col-span-2"><button class="bg-coral text-white text-xs font-bold uppercase tracking-wider rounded px-4 py-2">Confirm reschedule</button></div>
    </form>`;
    const branch = document.getElementById('rbranch'), barber = document.getElementById('rbarber'), date = document.getElementById('rdate'), time = document.getElementById('rtime');
    branch.value = String(first.branch_id);
    const paintBarbers = () => {
      barber.innerHTML = barbers.rows.filter(b => +b.branch_id === +branch.value).map(b => `<option value="${b.id}">${esc(b.name)} · Chair ${b.chair}</option>`).join('');
      barber.value = String(first.barber_id);
      if (!barber.value || !barber.options.length) barber.value = barber.options[0]?.value || '';
      loadTimes();
    };
    const loadTimes = async () => {
      const ids = [...document.querySelectorAll('input[name=service]:checked')].map(x => x.value).join(',');
      if (!barber.value || !date.value || !ids) return;
      try {
        const d = await fetch(`api/public/slots.php?barber_id=${encodeURIComponent(barber.value)}&date=${encodeURIComponent(date.value)}&services=${encodeURIComponent(ids)}`).then(r => r.json());
        time.innerHTML = d.slots.map(s => `<option value="${s.start}">${s.start}–${s.end}</option>`).join('') || '<option value="">No available times</option>';
      } catch (e) { document.getElementById('rerr').textContent = e.error || 'Could not load available times'; }
    };
    branch.onchange = paintBarbers; barber.onchange = loadTimes; date.onchange = loadTimes;
    document.querySelectorAll('input[name=service]').forEach(x => x.onchange = loadTimes);
    document.getElementById('reschedule-fields').onsubmit = async e => {
      e.preventDefault();
      const ids = [...document.querySelectorAll('input[name=service]:checked')].map(x => +x.value);
      try {
        const r = await fetch('api/public/booking.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf]').content}, body:JSON.stringify({ref:form.ref.value, phone:form.phone.value, token:form.token.value, action:'reschedule', barber_id:+barber.value, appt_date:date.value, start_time:time.value, services:ids})});
        const d = await r.json(); if (!r.ok || !d.ok) throw d;
        await loadBooking();
      } catch (e) { document.getElementById('rerr').textContent = e.error || 'Could not reschedule booking'; }
    };
    paintBarbers();
  } catch (e) { box.innerHTML = `<p class="text-sm text-rust">${esc(e.error || 'Could not load rescheduling options')}</p>`; }
}
form.addEventListener('submit', e => { e.preventDefault(); loadBooking(); });
</script>
<?php page_foot(); ?>
