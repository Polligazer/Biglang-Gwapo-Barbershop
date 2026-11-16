'use strict';
/* Public site: browsing + booking. Talks only to api/public/* (no login). */
const $ = (s, r = document) => r.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const t12 = t => { const [h, m] = t.split(':'); return `${(+h % 12) || 12}:${m} ${+h < 12 ? 'AM' : 'PM'}`; };
const CSRF = $('meta[name=csrf]')?.content || '';
const store = {
  get() { try { return JSON.parse(sessionStorage.getItem('sg') || '{}'); } catch { return {}; } },
  set(o) { try { sessionStorage.setItem('sg', JSON.stringify({ ...this.get(), ...o })); } catch { /* storage unavailable */ } },
};
async function call(path, { params, body } = {}) {
  const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '';
  const res = await fetch('api/public/' + path + qs, body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(body) } : {});
  let d; try { d = await res.json(); } catch { d = { ok: false, error: 'Unexpected server response' }; }
  if (!res.ok || !d.ok) throw d;
  return d;
}
const catalog = (r, params = {}) => call('catalog.php', { params: { r, ...params } });
const fail = (el, e) => { el.innerHTML = `<p class="text-sm text-rust">${esc(e.error || 'Could not load data. Please refresh.')}</p>`; };
const btnP = 'bg-coral text-white font-bold uppercase tracking-wider text-xs px-5 py-3 rounded hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed';
const inputCls = 'w-full bg-white border border-sand3 rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-coral/50';
const BARS = ['bg-navy', 'bg-coral', 'bg-ink'];

const serviceCard = (s, i, extra = '') => `<article class="bg-white rounded-lg shadow-sm overflow-hidden flex flex-col"><div class="h-1 ${BARS[i % 3]}"></div>
  <div class="p-5 flex-1 flex flex-col"><div class="flex justify-between gap-2"><h3 class="font-serif text-xl">${esc(s.name)}</h3><span class="text-[10px] font-bold bg-sand rounded px-2 py-1 h-fit whitespace-nowrap">${s.duration_min} min</span></div>
  <p class="text-sm text-ink/70 mt-2 flex-1">${esc(s.description)}</p>
  <div class="flex items-end justify-between mt-4"><span class="font-serif text-2xl">${money(s.price)}</span>${extra}</div></div></article>`;

/* ---------- page modules ---------- */
const pages = {
  async home() {
    const [s, b] = await Promise.all([catalog('services'), catalog('branches')]);
    $('#featured').innerHTML = s.rows.slice(0, 3).map((x, i) => serviceCard(x, i, `<button data-book="${x.id}" class="${btnP} !py-2">Book this</button>`)).join('');
    $('#branch-list').innerHTML = b.rows.map(branchCard).join('');
  },
  async services() {
    const [s, p] = await Promise.all([catalog('services'), catalog('products')]);
    const groups = {};
    s.rows.forEach(x => (groups[x.category] ||= []).push(x));
    $('#catalog').innerHTML = Object.entries(groups).map(([cat, list]) => `<div><h2 class="font-serif text-3xl mb-4">${esc(cat)}</h2>
      <div class="grid md:grid-cols-3 gap-5">${list.map((x, i) => serviceCard(x, i, `<button data-toggle="${x.id}" class="text-xs font-bold uppercase tracking-wider px-3 py-2 rounded border border-coral" aria-pressed="false">Add</button>`)).join('')}</div></div>`).join('');
    const total = () => {
      const ids = store.get().services || [], sel = s.rows.filter(x => ids.includes(x.id));
      document.querySelectorAll('[data-toggle]').forEach(b => {
        const on = ids.includes(+b.dataset.toggle);
        b.textContent = on ? '✓ Added' : 'Add'; b.setAttribute('aria-pressed', on);
        b.className = `text-xs font-bold uppercase tracking-wider px-3 py-2 rounded border border-coral ${on ? 'bg-coral text-white' : ''}`;
      });
      $('#cartbar').classList.toggle('hidden', !sel.length);
      $('#cartinfo').textContent = `${sel.length} service${sel.length === 1 ? '' : 's'} · ${sel.reduce((a, x) => a + x.duration_min, 0)} min · ${money(sel.reduce((a, x) => a + +x.price, 0))}`;
    };
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-toggle]'); if (!b) return;
      const id = +b.dataset.toggle, ids = new Set(store.get().services || []);
      ids.has(id) ? ids.delete(id) : ids.add(id); store.set({ services: [...ids] }); total();
    });
    total();
    $('#products').innerHTML = p.rows.map(x => `<div class="bg-white rounded-lg shadow-sm p-4"><p class="text-[10px] font-bold uppercase tracking-widest text-ink/60">${esc(x.category)}</p>
      <p class="font-semibold mt-1">${esc(x.name)}</p><div class="flex justify-between items-center mt-3"><span class="font-serif text-xl">${money(x.price)}</span>
      <span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${x.in_stock ? 'bg-navy/10 text-navy' : 'bg-sand3 text-ink/60'}">${x.in_stock ? 'In stock' : 'Sold out'}</span></div></div>`).join('')
      || '<p class="text-sm text-ink/60">No products listed yet.</p>';
  },
  async branches() {
    const [b, m] = await Promise.all([catalog('branches'), catalog('barbers')]);
    $('#branches').innerHTML = b.rows.map(x => branchCard(x, m.rows.filter(y => y.branch_id === x.id))).join('');
  },
};
function branchCard(b, barbers = []) {
  return `<article class="bg-white rounded-lg shadow-sm p-5 border-t-4 border-navy flex flex-col"><h3 class="font-serif text-2xl">${esc(b.name)}</h3>
    <p class="text-sm mt-2">${esc(b.address)}</p><p class="text-sm text-ink/70">${esc(b.hours)}${b.phone ? ' · ' + esc(b.phone) : ''}</p>
    ${barbers.length ? `<ul class="mt-3 text-sm space-y-1">${barbers.map(x => `<li><b>${esc(x.name)}</b> <span class="text-ink/60">· ${esc(x.title || 'Barber')} · Chair ${x.chair}</span></li>`).join('')}</ul>` : ''}
    <a href="book.php?branch=${b.id}" class="${btnP} mt-5 text-center">Book at this branch</a></article>`;
}

/* ---------- booking flow: barber → services & time → details → confirmation ---------- */
pages.book = async function () {
  const q = new URLSearchParams(location.search), saved = store.get(), root = $('#flow');
  let init;
  try {
    const [b, m, s] = await Promise.all([catalog('branches'), catalog('barbers'), catalog('services')]);
    init = { branches: b.rows, barbers: m.rows, services: s.rows, today: b.today };
  } catch (e) { return fail(root, e); }
  const preBarber = +q.get('barber') || saved.barber || 0;
  const S = {
    ...init, step: 1, slots: [], slot: '', msg: '', details: {}, done: null,
    barber: preBarber, date: '',
    branch: +q.get('branch') || saved.branch || init.barbers.find(x => x.id === preBarber)?.branch_id || init.branches[0]?.id || 0,
    picked: new Set(saved.services || (q.get('service') ? [+q.get('service')] : [])),
  };
  const sel = () => S.services.filter(x => S.picked.has(x.id));
  const sum = k => sel().reduce((a, x) => a + (k === 'price' ? +x.price : x.duration_min), 0);
  const who = () => S.barbers.find(x => x.id === S.barber);
  const brn = () => S.branches.find(x => x.id === S.branch);
  const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const days = Array.from({ length: 7 }, (_, i) => { const d = new Date(S.today + 'T00:00:00'); d.setDate(d.getDate() + i); return d; });

  const stepper = () => $('#steps').innerHTML = ['Barber', 'Services & time', 'Your details', 'Confirmed'].map((l, i) =>
    `<span class="px-3 py-1.5 rounded ${S.step === i + 1 ? 'bg-navy text-white' : 'bg-sand3 text-ink/60'}">${i + 1}. ${l}</span>`).join('');

  function render() {
    stepper();
    if (S.step === 1) {
      root.innerHTML = `<div class="bg-sand rounded-lg p-4 mb-5"><label class="text-[10px] font-bold uppercase tracking-widest" for="branch">Selected atelier</label>
        <select id="branch" class="${inputCls} mt-1">${S.branches.map(x => `<option value="${x.id}" ${x.id === S.branch ? 'selected' : ''}>${esc(x.name)} — ${esc(x.address)}</option>`).join('')}</select></div>
        <h2 class="font-serif text-2xl mb-3">Select your master barber</h2>
        <div class="space-y-3">${S.barbers.filter(x => x.branch_id === S.branch).map(x => `<button data-barber="${x.id}" class="w-full text-left bg-white rounded-lg p-4 shadow-sm border-2 ${x.id === S.barber ? 'border-navy' : 'border-transparent'} flex justify-between items-center" aria-pressed="${x.id === S.barber}">
          <span><span class="font-serif text-xl block">${esc(x.name)}</span><span class="text-sm text-navy">${esc(x.title || 'Barber')}</span></span>
          <span class="text-xs font-bold bg-sand rounded px-2 py-1">CHAIR ${x.chair}${x.id === S.barber ? ' ✓' : ''}</span></button>`).join('') || '<p class="text-sm text-ink/60">No barbers at this branch yet.</p>'}</div>
        <div class="mt-6 text-right"><button data-go="2" class="${btnP}" ${S.barber && who()?.branch_id === S.branch ? '' : 'disabled'}>Continue to services &amp; time →</button></div>`;
    } else if (S.step === 2) {
      root.innerHTML = `<p class="text-sm mb-4">Barber: <b>${esc(who().name)}</b> · ${esc(brn().name)} · Chair ${who().chair} <button data-go="1" class="underline text-navy ml-2">Change</button></p>
        <h2 class="font-serif text-2xl mb-3">Tailor your services <span class="text-xs font-sans font-bold text-ink/60">multi-select</span></h2>
        <div class="space-y-2">${S.services.map(x => `<label class="flex gap-3 items-start bg-white rounded-lg p-4 shadow-sm cursor-pointer"><input type="checkbox" data-svc="${x.id}" ${S.picked.has(x.id) ? 'checked' : ''} class="mt-1 h-4 w-4 accent-navy">
          <span class="flex-1"><b>${esc(x.name)}</b><span class="block text-xs text-ink/70">${esc(x.description)}</span></span><span class="text-right text-sm font-semibold">${money(x.price)}<span class="block text-xs font-normal text-ink/60">${x.duration_min} min</span></span></label>`).join('')}</div>
        <div class="bg-sand3 rounded-lg p-4 mt-3 flex justify-between items-center"><span class="text-sm font-semibold" id="total"></span></div>
        <h2 class="font-serif text-2xl mt-8 mb-3">Schedule appointment</h2>
        <div class="flex gap-2 overflow-x-auto pb-2">${days.map(d => `<button data-date="${iso(d)}" class="shrink-0 rounded-lg px-4 py-3 text-center border-2 ${iso(d) === S.date ? 'bg-navy text-white border-navy' : 'bg-white border-transparent'}">
          <span class="block text-[10px] uppercase font-bold">${d.toLocaleDateString('en-US', { weekday: 'short' })}</span><span class="font-serif text-lg">${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span></button>`).join('')}
          <input type="date" id="more" min="${S.today}" max="${iso(new Date(days[0].getTime() + 60 * 864e5))}" value="${S.date}" aria-label="Pick another date" class="${inputCls} !w-auto shrink-0 self-center"></div>
        <div id="slots" class="mt-4"></div>
        <div class="mt-6 flex justify-between"><button data-go="1" class="text-sm font-semibold px-4 py-3">← Back</button><button data-go="3" class="${btnP}" id="next2" disabled>Review &amp; reserve →</button></div>`;
      paintTotal(); paintSlots();
    } else if (S.step === 3) {
      const t = sel();
      root.innerHTML = `<div class="bg-white rounded-lg shadow-sm p-5 mb-5 text-sm"><h2 class="font-serif text-2xl mb-2">Your reservation</h2>
        <p><b>${esc(who().name)}</b> · ${esc(brn().name)} · Chair ${who().chair}</p>
        <p>${new Date(S.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}, ${t12(S.slot.start)} – ${t12(S.slot.end)}</p>
        <ul class="mt-2 list-disc pl-5">${t.map(x => `<li>${esc(x.name)} (${x.duration_min} min, ${money(x.price)})</li>`).join('')}</ul><p class="font-serif text-2xl mt-2">Total ${money(sum('price'))}</p></div>
        <form id="details" novalidate class="grid sm:grid-cols-2 gap-x-4">
          ${[['customer_name', 'Full name *', 'text', 120, 2], ['customer_phone', 'Phone *', 'tel', 30, 1], ['customer_email', 'Email (optional)', 'email', 150, 1]].map(([n, l, ty, mx, sp]) => `<div class="${sp === 2 ? 'sm:col-span-2' : ''}">
            <label for="d_${n}" class="block text-[10px] font-bold uppercase tracking-widest mb-1">${l}</label><input id="d_${n}" name="${n}" type="${ty}" maxlength="${mx}" value="${esc(S.details[n] || '')}" class="${inputCls}"><p class="text-xs text-rust min-h-[1rem] mt-1" data-err="${n}"></p></div>`).join('')}
          <div class="sm:col-span-2"><label for="d_notes" class="block text-[10px] font-bold uppercase tracking-widest mb-1">Notes for your barber (optional)</label><textarea id="d_notes" name="notes" maxlength="500" rows="3" class="${inputCls}">${esc(S.details.notes || '')}</textarea></div>
          <div class="absolute -left-[9999px]" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
          <p class="sm:col-span-2 text-sm text-rust min-h-[1.25rem] mt-2" data-formerr role="alert"></p>
          <div class="sm:col-span-2 flex justify-between"><button type="button" data-go="2" class="text-sm font-semibold px-4 py-3">← Back</button><button class="${btnP}">Confirm booking request</button></div></form>`;
    } else {
      const d = S.done;
      root.innerHTML = `<div class="bg-white rounded-lg shadow-sm border-t-4 border-navy p-6"><p class="text-[10px] font-bold uppercase tracking-widest text-rust">Request received</p>
        <h2 class="font-serif text-3xl mt-1">Thank you, ${esc(S.details.customer_name)}.</h2><p class="mt-2 text-sm">Reference <b class="font-mono text-lg">${esc(d.ref)}</b>. Your booking is <b>pending</b> until the atelier confirms it${S.details.customer_email ? ' — we will email you' : ''} or call you.</p>
        <p class="mt-3 text-sm bg-sand rounded p-3">Save this private cancellation token: <b class="font-mono break-all">${esc(d.token)}</b>. You need it with your reference and phone number to reschedule or cancel this booking.</p>
        <p class="mt-3 text-sm text-rust"><b>Payment:</b> Pay in-store after your service. Arriving 15 minutes late may result in your appointment time being forfeited.</p>
        <ul class="mt-4 text-sm space-y-1">${d.items.map(i => `<li>${t12(i.start_time.slice(0, 5))} – ${t12(i.end_time.slice(0, 5))} · ${esc(i.service)} with ${esc(i.barber)}</li>`).join('')}</ul>
        <p class="text-sm text-ink/70 mt-3">${esc(d.items[0]?.branch || '')}</p>
        <div class="mt-6 flex gap-3"><a href="index.php" class="${btnP}">Back to home</a><a href="book.php" class="text-sm font-semibold px-4 py-3">Book another</a></div></div>`;
    }
  }
  function paintTotal() { const t = $('#total'); if (t) t.textContent = sel().length ? `${sel().length} service${sel().length > 1 ? 's' : ''} · ${sum('dur')} min · ${money(sum('price'))}` : 'Select at least one service'; }
  async function paintSlots() {
    const box = $('#slots'), next = $('#next2'); if (!box) return;
    S.slot = S.slot && S.slots.some(x => x.start === S.slot.start) ? S.slot : ''; if (next) next.disabled = true;
    if (!sel().length || !S.date) { box.innerHTML = `<p class="text-sm text-ink/60">${sel().length ? 'Pick a date to see open times.' : 'Choose your services first.'}</p>`; return; }
    box.innerHTML = '<p class="text-sm text-ink/60">Checking availability…</p>';
    const key = S.date + S.barber + [...S.picked].join();
    try {
      const d = await call('slots.php', { params: { barber_id: S.barber, date: S.date, services: [...S.picked].join(',') } });
      if (key !== S.date + S.barber + [...S.picked].join()) return; // stale response
      S.slots = d.slots; S.slot = S.slots.find(x => x.start === S.slot?.start) || '';
    } catch (e) { box.innerHTML = `<p class="text-sm text-rust">${esc(e.errors?.date || e.errors?.services || e.error || 'Could not load times')}</p>`; return; }
    box.innerHTML = S.slots.length ? `<p class="text-[10px] font-bold uppercase tracking-widest mb-2">Available times (${sum('dur')} min window)</p><div class="grid grid-cols-2 sm:grid-cols-3 gap-2">${S.slots.map(x => `<button data-slot="${x.start}" class="rounded-lg p-3 text-left text-sm border-2 ${S.slot?.start === x.start ? 'bg-rust text-white border-rust' : 'bg-white border-transparent'}"><b>${t12(x.start)}</b><span class="block text-xs opacity-80">Finishes ${t12(x.end)}</span></button>`).join('')}</div>`
      : `<p class="text-sm text-rust">No openings that day for ${esc(who().name)}. Try another date or <button data-go="1" class="underline">switch barber</button>.</p>`;
    if (next) next.disabled = !S.slot;
  }
  const persist = () => store.set({ branch: S.branch, barber: S.barber, services: [...S.picked] });

  root.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.barber) { S.barber = +b.dataset.barber; persist(); render(); }
    else if (b.dataset.go) {
      const to = +b.dataset.go;
      if (to === 3 && (!S.slot || !sel().length)) return;
      S.step = to; render(); scrollTo(0, 0);
    } else if (b.dataset.date) { S.date = b.dataset.date; S.slot = ''; render(); }
    else if (b.dataset.slot) { S.slot = S.slots.find(x => x.start === b.dataset.slot); render(); }
  });
  root.addEventListener('change', e => {
    if (e.target.id === 'branch') { S.branch = +e.target.value; if (who()?.branch_id !== S.branch) S.barber = 0; persist(); render(); }
    else if (e.target.dataset.svc) { const id = +e.target.dataset.svc; S.picked.has(id) ? S.picked.delete(id) : S.picked.add(id); S.slot = ''; persist(); paintTotal(); paintSlots(); }
    else if (e.target.id === 'more') { S.date = e.target.value; S.slot = ''; render(); }
  });
  root.addEventListener('input', e => { if (e.target.form?.id === 'details') S.details[e.target.name] = e.target.value; });
  root.addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, v = Object.fromEntries(new FormData(f)), errs = {};
    if (!v.customer_name.trim()) errs.customer_name = 'Required';
    if (!/^[0-9+()\-\s.]{7,30}$/.test(v.customer_phone.trim())) errs.customer_phone = 'Enter a valid phone number';
    if (v.customer_email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.customer_email.trim())) errs.customer_email = 'Invalid email address';
    const show = x => f.querySelectorAll('[data-err]').forEach(p => p.textContent = x[p.dataset.err] || '');
    show(errs); if (Object.keys(errs).length) return;
    const btn = f.querySelector('button:not([type=button])'); btn.disabled = true; $('[data-formerr]', f).textContent = '';
    try {
      S.done = await call('book.php', { body: { ...v, barber_id: S.barber, appt_date: S.date, start_time: S.slot.start, services: [...S.picked] } });
      store.set({ services: [] }); S.step = 4; render(); scrollTo(0, 0);
    } catch (err) {
      if (err.errors?.start_time) { S.step = 2; S.slot = ''; render(); const bx = $('#slots'); if (bx) bx.insertAdjacentHTML('beforebegin', `<p class="text-sm text-rust mb-2" role="alert">${esc(err.errors.start_time)} Please pick another time.</p>`); return; }
      show(err.errors || {}); $('[data-formerr]', f).textContent = err.error || 'Booking failed';
    } finally { btn.disabled = false; }
  });
  render();
};

/* ---------- global wiring ---------- */
document.addEventListener('click', e => {
  if (e.target.closest('[data-menu]')) $('#mnav').classList.toggle('hidden');
  const b = e.target.closest('[data-book]');
  if (b) { store.set({ services: [+b.dataset.book] }); location.href = 'book.php'; }
});
const run = pages[document.body.dataset.page];
if (run) run().catch(e => { const t = $('#featured, #catalog, #branches, #branch-list'); if (t) fail(t, e || {}); });
