'use strict';
/* Sovereign Grooming admin: vanilla JS, Fetch API, generic CRUD driven by config. */
const CSRF = document.querySelector('meta[name=csrf]').content;
const $ = (s, r = document) => r.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const t12 = t => { const [h, m] = t.split(':'); return `${(+h % 12) || 12}:${m} ${+h < 12 ? 'AM' : 'PM'}`; };
const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const LIMIT = 10;
const API = document.querySelector('meta[name=api]')?.content || 'api/';

async function api(path, { method = 'GET', body, params } = {}) {
  const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '';
  const res = await fetch(API + path + qs, {
    method, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data;
  try { data = await res.json(); } catch { data = { ok: false, error: 'Unexpected server response' }; }
  if (res.status === 401) { location.href = 'login.php'; throw data; }
  if (!res.ok || !data.ok) throw data;
  return data;
}

function toast(msg, err = false) {
  const d = document.createElement('div');
  d.className = `px-4 py-3 rounded shadow-lg text-sm font-semibold text-white ${err ? 'bg-rust' : 'bg-navy'}`;
  d.textContent = msg;
  $('#toasts').append(d);
  setTimeout(() => d.remove(), 3500);
}

/* ---------- Modal ---------- */
const btnP = 'bg-coral text-white font-bold uppercase tracking-wider text-xs px-5 py-3 rounded hover:opacity-90 disabled:opacity-50';
const btnG = 'text-sm font-semibold px-4 py-3 rounded hover:bg-sand3/60';
const inputCls = 'w-full bg-cream border border-sand3 rounded px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-coral/50';
function openModal(eyebrow, title, html) {
  const m = $('#modal');
  m.innerHTML = `<div class="bg-white w-full max-w-xl rounded-lg shadow-2xl border-t-4 border-coral overflow-hidden">
    <div class="bg-sand px-6 py-4 flex justify-between items-start">
      <div><p class="text-[10px] font-bold tracking-widest uppercase text-rust">${esc(eyebrow)}</p><h2 class="font-serif text-2xl">${esc(title)}</h2></div>
      <button data-close aria-label="Close" class="text-2xl leading-none px-2">&times;</button></div>
    <div class="p-6">${html}</div></div>`;
  m.classList.replace('hidden', 'flex');
  const first = m.querySelector('input,select,textarea,button:not([data-close])'); first && first.focus();
}
const closeModal = () => { const m = $('#modal'); m.classList.replace('flex', 'hidden'); m.innerHTML = ''; };

/* ---------- Form helpers (client-side validation mirrors server rules) ---------- */
function fieldHtml(f, v) {
  const val = v ?? f.def ?? '';
  const a = `name="${f.name}" id="f_${f.name}"`;
  let ctl;
  if (f.type === 'select') ctl = `<select ${a} class="${inputCls}">${f.opts().map(o => `<option value="${esc(o.v)}" ${String(o.v) === String(val) ? 'selected' : ''}>${esc(o.l)}</option>`).join('')}</select>`;
  else if (f.type === 'textarea') ctl = `<textarea ${a} rows="3" maxlength="${f.max || 1000}" class="${inputCls}">${esc(val)}</textarea>`;
  else if (f.type === 'toggle') ctl = `<label class="flex items-center gap-2 text-sm"><input type="checkbox" ${a} ${+val ? 'checked' : ''} class="h-4 w-4 accent-coral"> ${esc(f.hint || 'Active')}</label>`;
  else ctl = `<input type="${f.type}" ${a} value="${esc(val)}" ${f.max ? `maxlength="${f.max}"` : ''} ${f.type === 'number' ? `min="${f.min ?? ''}" max="${f.max ?? ''}" step="${f.dec ? '0.01' : '1'}"` : ''} ${f.type === 'time' ? 'step="900" min="09:00" max="17:45"' : ''} class="${inputCls}">`;
  return `<div class="${f.span === 2 ? 'sm:col-span-2' : ''}"><label for="f_${f.name}" class="block text-[10px] font-bold tracking-widest uppercase text-ink/70 mb-1">${esc(f.label)}${f.req ? ' *' : ''}</label>${ctl}<p class="text-xs text-rust mt-1 min-h-[1rem]" data-err="${f.name}"></p></div>`;
}
const readForm = (form, fields) => Object.fromEntries(fields.map(f => {
  const el = form.elements[f.name];
  return [f.name, f.type === 'toggle' ? (el.checked ? 1 : 0) : el.value.trim()];
}));
function checkForm(data, fields) {
  const e = {};
  for (const f of fields) {
    const v = data[f.name];
    if (f.req && (v === '' || v == null)) { e[f.name] = 'Required'; continue; }
    if (v === '' || f.type === 'toggle') continue;
    if (f.type === 'number') {
      const n = Number(v);
      if (Number.isNaN(n)) e[f.name] = 'Must be a number';
      else if (f.min != null && n < f.min) e[f.name] = `Minimum ${f.min}`;
      else if (f.max != null && n > f.max) e[f.name] = `Maximum ${f.max}`;
      else if (!f.dec && !Number.isInteger(n)) e[f.name] = 'Whole numbers only';
      else if (f.dec && !/^\d+(\.\d{1,2})?$/.test(v)) e[f.name] = 'Max 2 decimals';
    } else if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) e[f.name] = 'Invalid email address';
    else if (f.type === 'time') {
      const m = /^(\d{2}):(\d{2})$/.exec(v);
      const mins = m ? +m[1] * 60 + +m[2] : -1;
      if (mins < 540 || mins > 1065 || mins % 15) e[f.name] = 'Pick a 15-min slot between 09:00 and 17:45';
    } else if (f.max && v.length > f.max) e[f.name] = `Max ${f.max} characters`;
  }
  return e;
}
function showErrors(form, errs) {
  form.querySelectorAll('[data-err]').forEach(p => p.textContent = errs[p.dataset.err] || '');
}

/* ---------- Resource configs ---------- */
const S = {
  view: '', barbers: [], services: [], branches: [], dirty: true,
  lists: {
    barbers: blank(), availability: blank(), admins: blank(),
    appointments: blank(), services: blank(), inventory: blank(),
  },
  calendar: { date: new Date(), rows: [] },
  audit: { rows: [], total: 0, page: 1, limit: 25 },
  pendingAdd: false,
};
function blank() { return { rows: [], total: 0, page: 1, q: '', status: '', date: '', category: '' }; }
const badge = (txt, cls) => `<span class="inline-block px-2.5 py-1 rounded-sm text-[10px] font-bold uppercase tracking-wider ${cls}">${esc(txt)}</span>`;
const STATUS = { confirmed: 'bg-navy text-white', pending: 'bg-coral/20 text-rust', completed: 'bg-ink text-white', cancelled: 'bg-sand3 text-ink/70' };
const stockBadge = r => r.stock <= 0 ? badge('Out', 'bg-rust text-white') : r.stock <= r.min_threshold ? badge('Low', 'bg-coral/20 text-rust') : badge('In stock', 'bg-navy/10 text-navy');
const cell = (a, b) => `<div class="font-semibold">${esc(a)}</div><div class="text-xs text-ink/60">${esc(b)}</div>`;
const opts = list => () => list.map(x => ({ v: x, l: x }));

const R = {
  barbers: {
    label: 'Barber', eyebrow: 'Staff registry', title: 'Barbers',
    sub: 'Add, deactivate, and assign barbers to branches.',
    filters: [],
    cols: [
      ['Barber', r => cell(r.name, r.title || 'Barber')],
      ['Branch', r => esc(r.branch)],
      ['Chair', r => r.chair],
      ['Status', r => +r.active ? badge('Active', 'bg-navy/10 text-navy') : badge('Unavailable', 'bg-sand3 text-ink/70')],
    ],
    fields: () => [
      { name: 'name', label: 'Barber name', type: 'text', req: 1, max: 100 },
      { name: 'branch_id', label: 'Branch', type: 'select', req: 1, opts: () => S.branches.map(b => ({ v: b.id, l: b.name })) },
      { name: 'chair', label: 'Chair', type: 'number', req: 1, min: 1, max: 255 },
      { name: 'title', label: 'Title', type: 'text', max: 60 },
      { name: 'active', label: 'Status', type: 'toggle', def: 1, hint: 'Bookable by clients' },
    ],
  },
  availability: {
    label: 'Unavailable period', eyebrow: 'Barber availability', title: 'Unavailable Dates',
    sub: 'Block a barber for a whole day or a specific time range.',
    filters: [],
    cols: [
      ['Barber', r => cell(r.barber_name, r.branch)],
      ['Dates', r => `${fmtDate(r.start_date)} – ${fmtDate(r.end_date)}`],
      ['Time', r => r.start_time ? `${t12(r.start_time)} – ${t12(r.end_time)}` : 'All day'],
      ['Reason', r => esc(r.reason || '')],
    ],
    prep: r => ({ ...r, start_time: r.start_time ? r.start_time.slice(0, 5) : '', end_time: r.end_time ? r.end_time.slice(0, 5) : '' }),
    fields: () => [
      { name: 'barber_id', label: 'Barber', type: 'select', req: 1, opts: () => S.barbers.filter(b => +b.active).map(b => ({ v: b.id, l: `${b.name} — ${b.branch}` })) },
      { name: 'start_date', label: 'Start date', type: 'date', req: 1 },
      { name: 'end_date', label: 'End date', type: 'date', req: 1 },
      { name: 'start_time', label: 'Start time (optional)', type: 'time' },
      { name: 'end_time', label: 'End time (optional)', type: 'time' },
      { name: 'reason', label: 'Reason', type: 'text', max: 255, span: 2 },
    ],
  },
  admins: {
    label: 'Admin user', eyebrow: 'Access control', title: 'Admin Users',
    sub: 'Create global administrators and branch managers.',
    filters: [],
    cols: [
      ['Username', r => cell(r.username, r.name)],
      ['Role', r => r.role === 'global_admin' ? badge('Global admin', 'bg-navy text-white') : badge('Branch manager', 'bg-coral/20 text-rust')],
      ['Branch', r => esc(r.branch || 'All branches')],
    ],
    fields: editing => [
      { name: 'username', label: 'Username', type: 'text', req: 1, max: 50 },
      { name: 'name', label: 'Display name', type: 'text', req: 1, max: 100 },
      { name: 'role', label: 'Role', type: 'select', req: 1, opts: () => [{ v: 'global_admin', l: 'Global admin' }, { v: 'branch_manager', l: 'Branch manager' }] },
      { name: 'branch_id', label: 'Assigned branch', type: 'select', opts: () => [{ v: '', l: 'All branches / none' }, ...S.branches.map(b => ({ v: b.id, l: b.name }))] },
      { name: 'password', label: editing ? 'New password (optional)' : 'Password', type: 'password', req: !editing, max: 200 },
    ],
  },
  appointments: {
    label: 'Appointment', eyebrow: 'Atelier floor registry', title: 'Appointments',
    sub: 'Book, reschedule, and track every client visit.',
    filters: [
      { name: 'status', type: 'select', opts: [['', 'All statuses'], ['pending', 'Pending'], ['confirmed', 'Confirmed'], ['completed', 'Completed'], ['cancelled', 'Cancelled']] },
      { name: 'date', type: 'date' },
    ],
    cols: [
      ['Booking', r => cell('#SVG-' + String(r.id).padStart(4, '0'), r.booking_ref ? 'Ref ' + r.booking_ref + ' · online' : '')],
      ['Client', r => cell(r.customer_name, r.customer_email || r.customer_phone || '')],
      ['Service', r => cell(r.service_name, `${r.duration_min} min · ${money(r.price)}`)],
      ['Barber', r => cell(r.barber_name, `${r.branch_name} · Chair ${r.chair}`)],
      ['When', r => cell(fmtDate(r.appt_date), `${t12(r.start_time)} – ${t12(r.end_time)}`)],
      ['Status', r => badge(r.status, STATUS[r.status])],
    ],
    fields: () => [
      { name: 'customer_name', label: 'Client name', type: 'text', req: 1, max: 120 },
      { name: 'customer_email', label: 'Email', type: 'email', max: 150 },
      { name: 'customer_phone', label: 'Phone', type: 'text', max: 30 },
      { name: 'service_id', label: 'Service', type: 'select', req: 1, opts: () => S.services.filter(s => +s.active).map(s => ({ v: s.id, l: `${s.name} (${money(s.price)}, ${s.duration_min} min)` })), span: 2 },
      { name: 'barber_id', label: 'Master barber', type: 'select', req: 1, opts: () => S.barbers.map(b => ({ v: b.id, l: `${b.name} — ${b.branch}, Chair ${b.chair}` })), span: 2 },
      { name: 'appt_date', label: 'Date', type: 'date', req: 1 },
      { name: 'start_time', label: 'Start time', type: 'time', req: 1 },
      { name: 'status', label: 'Status', type: 'select', req: 1, def: 'confirmed', opts: opts(['pending', 'confirmed', 'completed', 'cancelled']) },
      { name: 'notes', label: 'Notes (internal)', type: 'textarea', max: 1000, span: 2 },
    ],
    prep: r => ({ ...r, start_time: r.start_time.slice(0, 5) }),
  },
  services: {
    label: 'Service', eyebrow: 'Registry configuration', title: 'Service Menu',
    sub: 'Everything clients can book, with duration and tariff.',
    filters: [{ name: 'category', type: 'select', opts: [['', 'All categories'], ...['Haircut', 'Beard & Shave', 'Facial & Care', 'Packages'].map(c => [c, c])] }],
    cols: [
      ['Service', r => cell(r.name, (r.description || '').slice(0, 70))],
      ['Category', r => esc(r.category)],
      ['Duration', r => `${r.duration_min} min`],
      ['Price', r => `<span class="font-serif text-lg">${money(r.price)}</span>`],
      ['Status', r => +r.active ? badge('Active', 'bg-navy/10 text-navy') : badge('Hidden', 'bg-sand3 text-ink/70')],
    ],
    fields: () => [
      { name: 'name', label: 'Service name', type: 'text', req: 1, max: 120, span: 2 },
      { name: 'description', label: 'Description', type: 'textarea', max: 1000, span: 2 },
      { name: 'duration_min', label: 'Duration (min)', type: 'number', req: 1, min: 15, max: 240, def: 45 },
      { name: 'price', label: 'Price (PHP)', type: 'number', req: 1, min: 0, max: 9999, dec: 1 },
      { name: 'category', label: 'Category', type: 'select', req: 1, opts: opts(['Haircut', 'Beard & Shave', 'Facial & Care', 'Packages']) },
      { name: 'active', label: 'Visibility', type: 'toggle', def: 1, hint: 'Bookable by clients' },
    ],
  },
  inventory: {
    label: 'Product', eyebrow: 'Apothecary & dispensary registry', title: 'Inventory & Stock',
    sub: 'Retail goods and back-bar supplies with low-stock tracking.',
    adjust: true,
    filters: [{ name: 'category', type: 'select', opts: [['', 'All categories'], ...['Styling', 'Shave', 'Beard', 'Skincare', 'Tools', 'Consumables'].map(c => [c, c])] }],
    cols: [
      ['Product', r => cell(r.name, r.sku)],
      ['Branch', r => esc(S.branches.find(b => +b.id === +r.branch_id)?.name || '')],
      ['Category', r => esc(r.category)],
      ['Price', r => money(r.price)],
      ['On hand', r => `<span class="font-serif text-lg mr-2">${r.stock}</span>${stockBadge(r)}`],
      ['Min', r => r.min_threshold],
    ],
    fields: editing => [
      { name: 'branch_id', label: 'Branch', type: 'select', req: 1, opts: () => S.branches.map(b => ({ v: b.id, l: b.name })) },
      { name: 'name', label: 'Product name', type: 'text', req: 1, max: 150, span: 2 },
      { name: 'sku', label: 'SKU', type: 'text', req: 1, max: 32 },
      { name: 'category', label: 'Category', type: 'select', req: 1, opts: opts(['Styling', 'Shave', 'Beard', 'Skincare', 'Tools', 'Consumables']) },
      { name: 'price', label: 'Price (PHP)', type: 'number', req: 1, min: 0, max: 9999, dec: 1 },
      { name: 'min_threshold', label: 'Low-stock threshold', type: 'number', req: 1, min: 0, max: 100000, def: 5 },
      ...(editing ? [] : [{ name: 'stock', label: 'Opening stock', type: 'number', req: 1, min: 0, max: 100000, def: 0 }]),
    ],
  },
};

/* ---------- State updates (DOM re-render without reload) ---------- */
function upsert(key, row) {
  const L = S.lists[key], i = L.rows.findIndex(r => r.id === row.id);
  if (i >= 0) L.rows[i] = row; else { L.rows.unshift(row); L.total++; if (L.rows.length > LIMIT) L.rows.pop(); }
  if (key === 'services') S.services = S.services.some(s => s.id === row.id) ? S.services.map(s => s.id === row.id ? row : s) : [...S.services, row];
  S.dirty = true;
  if (S.view === key) renderRows(key);
}
async function removeRow(key, id) {
  await api('delete.php', { method: 'POST', body: { r: key, id } });
  const L = S.lists[key];
  L.rows = L.rows.filter(r => r.id !== id); L.total--;
  if (key === 'services') S.services = S.services.filter(s => s.id !== id);
  S.dirty = true;
  if (!L.rows.length && L.page > 1) { L.page--; return loadList(key); }
  renderRows(key);
}

/* ---------- Generic form modal ---------- */
function openForm(key, row) {
  const cfg = R[key], editing = !!row, fields = cfg.fields(editing), src = row ? (cfg.prep ? cfg.prep(row) : row) : {};
  openModal(cfg.eyebrow, `${editing ? 'Edit' : 'Add'} ${cfg.label}`, `<form novalidate class="grid sm:grid-cols-2 gap-x-4">
    ${fields.map(f => fieldHtml(f, src[f.name])).join('')}
    <p class="sm:col-span-2 text-sm text-rust min-h-[1.25rem]" data-formerr role="alert"></p>
    <div class="sm:col-span-2 flex justify-end gap-2"><button type="button" data-close class="${btnG}">Cancel</button><button class="${btnP}">${editing ? 'Save changes' : 'Create'}</button></div></form>`);
  const form = $('#modal form');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const data = readForm(form, fields), errs = checkForm(data, fields);
    showErrors(form, errs); $('[data-formerr]', form).textContent = '';
    if (Object.keys(errs).length) return;
    const btn = form.querySelector('button:not([data-close])'); btn.disabled = true;
    try {
      const res = editing
        ? await api('update.php', { method: 'POST', body: { r: key, id: row.id, data } })
        : await api('create.php', { method: 'POST', body: { r: key, data } });
      upsert(key, res.row); closeModal(); toast(`${cfg.label} ${editing ? 'updated' : 'created'}`);
    } catch (err) {
      showErrors(form, err.errors || {}); $('[data-formerr]', form).textContent = err.error || 'Request failed';
    } finally { btn.disabled = false; }
  });
}

function confirmDelete(key, row) {
  const cfg = R[key], name = row.customer_name || row.name;
  const action = cfg.soft_delete ? 'Deactivate' : 'Delete';
  openModal('Confirm', `${action} ${cfg.label.toLowerCase()}?`, `<p class="text-sm mb-6">This ${cfg.soft_delete ? 'makes' : 'permanently removes'} <strong>${esc(name)}</strong> ${cfg.soft_delete ? 'unavailable for new bookings while preserving history.' : 'and cannot be undone.'}</p>
    <p data-formerr class="text-sm text-rust min-h-[1.25rem]"></p>
    <div class="flex justify-end gap-2"><button data-close class="${btnG}">Cancel</button><button data-confirm class="${btnP} !bg-rust">Delete</button></div>`);
  $('[data-confirm]').onclick = async e => {
    e.target.disabled = true;
    try { await removeRow(key, row.id); closeModal(); toast(cfg.soft_delete ? 'Deactivated' : 'Deleted'); }
    catch (err) { $('[data-formerr]').textContent = err.error || `${action} failed`; e.target.disabled = false; }
  };
}

/* ---------- Stock adjustment modal ---------- */
const ADJ = [['receive', 'Receive shipment (+)', 'Supplier delivery intake'], ['waste', 'Damaged / waste (−)', 'Broken seal or spillage'],
  ['correction', 'Manual correction', 'Set to physical count'], ['transfer', 'Back-bar transfer (−)', 'Moved to chair-side use']];
function openAdjust(item, type = 'receive') {
  openModal('Ledger adjustment · ' + item.sku, 'Update stock ledger', `<form novalidate>
    <p class="text-sm mb-4">${esc(item.name)} — <strong>${item.stock}</strong> on hand (minimum ${item.min_threshold})</p>
    <div class="grid sm:grid-cols-2 gap-2 mb-4">${ADJ.map(([v, l, d]) => `<label class="flex gap-2 items-start bg-sand rounded p-3 cursor-pointer text-sm"><input type="radio" name="type" value="${v}" ${v === type ? 'checked' : ''} class="mt-1 accent-navy"><span><b>${l}</b><br><span class="text-xs text-ink/60">${d}</span></span></label>`).join('')}</div>
    <div class="grid sm:grid-cols-2 gap-x-4">
      <div><label class="block text-[10px] font-bold tracking-widest uppercase mb-1" for="qty">Quantity</label><input id="qty" name="quantity" type="number" min="0" max="100000" step="1" value="1" class="${inputCls}"><p class="text-xs text-rust min-h-[1rem] mt-1" data-err="quantity"></p></div>
      <div class="bg-sand2 rounded p-3 mb-4"><p class="text-[10px] font-bold tracking-widest uppercase text-ink/60">Projected total</p><p class="text-sm"><span class="font-serif text-3xl" data-proj></span> units</p></div>
      <div><label class="block text-[10px] font-bold tracking-widest uppercase mb-1" for="b">Batch reference</label><input id="b" name="batch_ref" maxlength="120" class="${inputCls}"></div>
      <div><label class="block text-[10px] font-bold tracking-widest uppercase mb-1" for="n">Note</label><input id="n" name="note" maxlength="255" class="${inputCls}"></div>
    </div>
    <p data-formerr class="text-sm text-rust min-h-[1.25rem] mt-2" role="alert"></p>
    <div class="flex justify-end gap-2"><button type="button" data-close class="${btnG}">Discard</button><button class="${btnP}">Apply stock update</button></div></form>`);
  const form = $('#modal form');
  const project = () => {
    const q = parseInt(form.quantity.value, 10) || 0, t = form.type.value;
    const p = t === 'receive' ? item.stock + q : t === 'correction' ? q : item.stock - q;
    $('[data-proj]').textContent = p; $('[data-proj]').className = 'font-serif text-3xl ' + (p < 0 ? 'text-rust' : '');
    return p;
  };
  form.addEventListener('input', project); project();
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const q = form.quantity.value.trim(), errs = {};
    if (!/^\d+$/.test(q)) errs.quantity = 'Whole number required';
    else if (form.type.value !== 'correction' && +q < 1) errs.quantity = 'Must be at least 1';
    else if (project() < 0) errs.quantity = `Only ${item.stock} units on hand`;
    showErrors(form, errs); if (Object.keys(errs).length) return;
    const btn = form.querySelector('button:not([data-close])'); btn.disabled = true;
    try {
      const res = await api('adjust_stock.php', { method: 'POST', body: { id: item.id, type: form.type.value, quantity: +q, batch_ref: form.batch_ref.value, note: form.note.value } });
      upsert('inventory', res.row); closeModal(); toast('Stock updated');
      if (S.view === 'dashboard') renderDashboard();
    } catch (err) { showErrors(form, err.errors || {}); $('[data-formerr]', form).textContent = err.error || 'Request failed'; }
    finally { btn.disabled = false; }
  });
}

/* ---------- List views ---------- */
const ICON_BTN = 'text-xs font-bold uppercase tracking-wider px-2 py-1 rounded hover:bg-sand3';
function renderShell(key) {
  const c = R[key], L = S.lists[key];
  $('#main').innerHTML = `<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div><p class="text-[10px] font-bold tracking-widest uppercase text-rust">${esc(c.eyebrow)}</p><h1 class="font-serif text-4xl">${esc(c.title)}</h1><p class="text-sm text-ink/70 mt-1">${esc(c.sub)}</p></div>
    <button data-action="add" class="${btnP}">+ Add ${esc(c.label)}</button></div>
    <div class="bg-white rounded-lg p-4 flex flex-wrap gap-3 mb-4 shadow-sm">
      <input data-filter="q" type="search" placeholder="Search…" value="${esc(L.q)}" aria-label="Search" class="${inputCls} !w-64">
      ${c.filters.map(f => f.type === 'select'
        ? `<select data-filter="${f.name}" aria-label="${f.name}" class="${inputCls} !w-auto">${f.opts.map(([v, l]) => `<option value="${esc(v)}" ${L[f.name] === v ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`
        : `<input data-filter="${f.name}" type="${f.type}" value="${esc(L[f.name])}" aria-label="${f.name}" class="${inputCls} !w-auto">`).join('')}
    </div>
    <div class="bg-white rounded-lg shadow-sm overflow-x-auto"><table class="w-full text-sm"><thead class="bg-sand text-[10px] uppercase tracking-widest text-ink/70"><tr>
      ${c.cols.map(([h]) => `<th class="text-left font-bold px-4 py-3">${h}</th>`).join('')}<th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody data-rows></tbody></table></div>
    <div data-pager class="flex items-center justify-between mt-4 text-sm"></div>`;
}
function renderRows(key) {
  const c = R[key], L = S.lists[key];
  $('[data-rows]').innerHTML = L.rows.length ? L.rows.map(r => `<tr class="border-t border-sand3/60 align-top">
    ${c.cols.map(([, f]) => `<td class="px-4 py-3">${f(r)}</td>`).join('')}
    <td class="px-4 py-3 text-right whitespace-nowrap" data-key="${key}" data-id="${r.id}">
      ${c.adjust ? `<button data-action="adjust" class="${ICON_BTN} text-navy">Adjust</button>` : ''}
      <button data-action="edit" class="${ICON_BTN}">Edit</button><button data-action="delete" class="${ICON_BTN} text-rust">Delete</button></td></tr>`).join('')
    : `<tr><td colspan="${c.cols.length + 1}" class="px-4 py-10 text-center text-ink/60">No records match.</td></tr>`;
  const pages = Math.max(1, Math.ceil(L.total / LIMIT));
  $('[data-pager]').innerHTML = `<span class="text-ink/70">${L.total} record${L.total === 1 ? '' : 's'}</span>
    <div class="flex items-center gap-2"><button data-action="page" data-dir="-1" ${L.page <= 1 ? 'disabled' : ''} class="${ICON_BTN} disabled:opacity-40">Previous</button>
    <span>Page ${L.page} of ${pages}</span><button data-action="page" data-dir="1" ${L.page >= pages ? 'disabled' : ''} class="${ICON_BTN} disabled:opacity-40">Next</button></div>`;
}
async function loadList(key) {
  const L = S.lists[key];
  try {
    const d = await api('read.php', { params: { r: key, q: L.q, status: L.status, date: L.date, category: L.category, page: L.page, limit: LIMIT } });
    L.rows = d.rows; L.total = d.total; L.page = d.page;
    if (S.view === key) renderRows(key);
  } catch (e) { toast(e.error || 'Could not load data', true); }
}

/* ---------- Dashboard ---------- */
async function renderDashboard() {
  S.view = 'dashboard';
  let d;
  try { d = await api('stats.php'); } catch (e) { return toast(e.error || 'Could not load dashboard', true); }
  if (S.view !== 'dashboard') return;
  S.dirty = false;
  const hr = new Date().getHours(), greet = hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : 'Good evening';
  const kpi = (label, val, bar, extra = '') => `<div class="bg-white rounded-lg shadow-sm p-5 relative overflow-hidden"><div class="absolute inset-x-0 top-0 h-1 ${bar}"></div>
    <p class="text-[10px] font-bold tracking-widest uppercase text-ink/70">${label}</p><p class="font-serif text-4xl font-bold mt-1">${val}</p>${extra}</div>`;
  $('#main').innerHTML = `<p class="text-[10px] font-bold tracking-widest uppercase text-rust">Live studio floor · ${fmtDate(d.today)}</p>
    <h1 class="font-serif text-4xl mb-6">${greet}, <em class="font-normal">${esc(d.admin)}</em>.</h1>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      ${kpi('Appointments today', d.booked, 'bg-navy')}${kpi('Revenue today', money(d.revenue), 'bg-coral')}
      ${kpi('Pending requests', d.pending, 'bg-rust', `<button data-action="goto" data-view="appointments" data-status="pending" class="text-xs font-bold text-navy mt-2">Review all →</button>`)}
      ${kpi('Active barbers', d.barbers, 'bg-blue')}</div>
    <div class="grid lg:grid-cols-12 gap-6">
      <section class="lg:col-span-7 bg-white rounded-lg shadow-sm p-6"><h2 class="font-serif text-2xl mb-4">Today's schedule</h2>
        ${d.schedule.length ? d.schedule.map(r => `<div class="flex items-center justify-between gap-3 bg-sand/60 rounded p-3 mb-2">
          <div><p class="font-semibold">${esc(r.customer_name)}</p><p class="text-xs text-ink/70">${esc(r.service_name)} · Chair ${r.chair} (${esc(r.barber_name)})</p></div>
          <div class="text-right shrink-0"><p class="text-sm font-semibold">${t12(r.start_time)}</p>${badge(r.status, STATUS[r.status])}</div></div>`).join('')
          : '<p class="text-sm text-ink/60">No appointments today.</p>'}</section>
      <section class="lg:col-span-5 bg-white rounded-lg shadow-sm p-6"><h2 class="font-serif text-2xl mb-4">Low stock <span class="text-xs font-sans font-bold bg-coral/20 text-rust rounded-full px-2 py-0.5 align-middle">${d.low.length}</span></h2>
        ${d.low.length ? d.low.map(i => `<div class="flex items-center justify-between gap-3 bg-sand/70 rounded p-3 mb-2"><div><p class="font-semibold">${esc(i.name)}</p>
          <p class="text-[10px] font-bold uppercase tracking-wider text-rust">${i.stock} left · minimum ${i.min_threshold}</p></div>
          <button data-action="reorder" data-item='${esc(JSON.stringify(i))}' class="bg-blue text-white text-[10px] font-bold uppercase tracking-wider px-3 py-2 rounded">Re-order</button></div>`).join('')
          : '<p class="text-sm text-ink/60">All stock levels healthy.</p>'}</section></div>`;
}

/* ---------- Calendar ---------- */
function calendarMonthLabel(date) {
  return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function calendarDateKey(date) {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-');
}

async function renderCalendar() {
  S.view = 'calendar';
  const date = S.calendar.date;
  $('#main').innerHTML = `<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div><p class="text-[10px] font-bold tracking-widest uppercase text-rust">Studio schedule</p>
      <h1 class="font-serif text-4xl">Calendar</h1><p class="text-sm text-ink/70 mt-1">See every appointment across the month at a glance.</p></div>
    <button data-action="goto" data-view="appointments" data-add="1" class="${btnP}">+ Add appointment</button>
  </div>
  <section class="bg-white rounded-lg shadow-sm overflow-hidden">
    <div class="flex items-center justify-between gap-3 p-4 border-b border-sand3/60">
      <div class="flex items-center gap-2">
        <button data-action="calendar-prev" class="${ICON_BTN}" aria-label="Previous month">←</button>
        <button data-action="calendar-today" class="${ICON_BTN}">Today</button>
        <button data-action="calendar-next" class="${ICON_BTN}" aria-label="Next month">→</button>
      </div>
      <h2 class="font-serif text-2xl">${calendarMonthLabel(date)}</h2>
      <span data-calendar-count class="text-xs text-ink/60">${S.calendar.rows.length} appointment${S.calendar.rows.length === 1 ? '' : 's'}</span>
    </div>
    <div data-calendar class="p-3"></div>
  </section>`;
  try {
    const data = await api('calendar.php', { params: { year: date.getFullYear(), month: date.getMonth() + 1 } });
    S.calendar.rows = data.rows;
    if (S.view === 'calendar') {
      renderCalendarGrid(data);
      $('[data-calendar-count]').textContent = `${data.rows.length} appointment${data.rows.length === 1 ? '' : 's'}`;
    }
  } catch (e) {
    toast(e.error || 'Could not load calendar', true);
  }
}

function renderCalendarGrid(data) {
  const first = new Date(data.start + 'T00:00:00');
  const rowsByDate = data.rows.reduce((groups, row) => {
    (groups[row.appt_date] ||= []).push(row);
    return groups;
  }, {});
  const headings = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  let html = `<div class="grid grid-cols-7 border-l border-t border-sand3/60">
    ${headings.map(day => `<div class="p-2 text-[10px] font-bold uppercase tracking-widest text-ink/60 border-r border-b border-sand3/60">${day}</div>`).join('')}`;
  for (let i = 0; i < 42; i++) {
    const day = new Date(first);
    day.setDate(first.getDate() + i);
    const key = calendarDateKey(day), outside = day.getMonth() !== S.calendar.date.getMonth();
    const events = rowsByDate[key] || [];
    html += `<div class="min-h-28 p-2 border-r border-b border-sand3/60 ${outside ? 'bg-sand/40 text-ink/40' : ''}">
      <div class="text-xs font-bold mb-1">${day.getDate()}</div>
      <div class="space-y-1">${events.map(row => `<button data-action="calendar-event" data-id="${row.id}" class="w-full text-left rounded px-2 py-1 text-[10px] leading-tight ${STATUS[row.status] || 'bg-sand3 text-ink'}">
        <span class="font-bold">${esc(t12(row.start_time))}</span> ${esc(row.customer_name)}<br><span class="opacity-80">${esc(row.barber_name)}</span>
      </button>`).join('')}</div>
    </div>`;
  }
  $('[data-calendar]').innerHTML = html + '</div>';
}

/* ---------- Audit log ---------- */
function auditDate(value) {
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleString('en-PH');
}

function auditDetails(value) {
  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
    return JSON.stringify(parsed);
  } catch {
    return String(value || '');
  }
}

async function renderAudit() {
  S.view = 'audit';
  const A = S.audit;
  $('#main').innerHTML = `<div class="mb-6">
    <p class="text-[10px] font-bold tracking-widest uppercase text-rust">Security &amp; accountability</p>
    <h1 class="font-serif text-4xl">Audit Log</h1>
    <p class="text-sm text-ink/70 mt-1">Review administrative actions and system activity.</p>
  </div>
  <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
    <table class="w-full text-sm"><thead class="bg-sand text-[10px] uppercase tracking-widest text-ink/70"><tr>
      <th class="text-left font-bold px-4 py-3">When</th><th class="text-left font-bold px-4 py-3">Admin</th>
      <th class="text-left font-bold px-4 py-3">Action</th><th class="text-left font-bold px-4 py-3">Resource</th>
      <th class="text-left font-bold px-4 py-3">Details</th></tr></thead>
      <tbody data-audit-rows></tbody>
    </table>
  </div>
  <div data-audit-pager class="flex items-center justify-between mt-4 text-sm"></div>`;
  await loadAudit();
}

async function loadAudit() {
  try {
    const data = await api('audit.php', { params: { page: S.audit.page } });
    S.audit.rows = data.rows; S.audit.total = data.total; S.audit.page = data.page;
    if (S.view !== 'audit') return;
    const A = S.audit;
    $('[data-audit-rows]').innerHTML = A.rows.length
      ? A.rows.map(row => `<tr class="border-t border-sand3/60 align-top">
          <td class="px-4 py-3 whitespace-nowrap">${esc(auditDate(row.created_at))}</td>
          <td class="px-4 py-3">${esc(row.admin_name)}</td>
          <td class="px-4 py-3">${badge(row.action, 'bg-navy/10 text-navy')}</td>
          <td class="px-4 py-3">${esc(row.resource)}${row.record_id ? ` <span class="text-xs text-ink/60">#${esc(row.record_id)}</span>` : ''}</td>
          <td class="px-4 py-3 max-w-md break-words text-xs text-ink/70">${esc(auditDetails(row.details))}</td>
        </tr>`).join('')
      : '<tr><td colspan="5" class="px-4 py-10 text-center text-ink/60">No audit entries yet.</td></tr>';
    const pages = Math.max(1, Math.ceil(A.total / A.limit));
    $('[data-audit-pager]').innerHTML = `<span class="text-ink/70">${A.total} entr${A.total === 1 ? 'y' : 'ies'}</span>
      <div class="flex items-center gap-2"><button data-action="audit-page" data-dir="-1" ${A.page <= 1 ? 'disabled' : ''} class="${ICON_BTN} disabled:opacity-40">Previous</button>
      <span>Page ${A.page} of ${pages}</span><button data-action="audit-page" data-dir="1" ${A.page >= pages ? 'disabled' : ''} class="${ICON_BTN} disabled:opacity-40">Next</button></div>`;
  } catch (e) {
    toast(e.error || 'Could not load audit log', true);
  }
}

/* ---------- Router & events ---------- */
async function route() {
  const key = (location.hash || '#dashboard').slice(1);
  document.querySelectorAll('[data-nav]').forEach(a => a.classList.toggle('on', a.dataset.nav === key));
  if (key === 'calendar') await renderCalendar();
  else if (key === 'audit') await renderAudit();
  else if (R[key]) {
    S.view = key; renderShell(key); renderRows(key); await loadList(key);
    if (key === 'appointments' && S.pendingAdd) { S.pendingAdd = false; openForm(key); }
  }
  else renderDashboard();
}
document.addEventListener('click', async e => {
  if (e.target === $('#modal') || e.target.closest('[data-close]')) return closeModal();
  const b = e.target.closest('[data-action]'); if (!b) return;
  const act = b.dataset.action, host = b.closest('[data-key]'), key = host?.dataset.key || S.view;
  const row = host && S.lists[key]?.rows.find(r => r.id === +host.dataset.id);
  if (act === 'calendar-prev' || act === 'calendar-next' || act === 'calendar-today') {
    if (act === 'calendar-today') S.calendar.date = new Date();
    else S.calendar.date = new Date(S.calendar.date.getFullYear(), S.calendar.date.getMonth() + (act === 'calendar-next' ? 1 : -1), 1);
    renderCalendar();
  } else if (act === 'calendar-event') {
    const event = S.calendar.rows.find(r => r.id === +b.dataset.id);
    if (event) {
      S.lists.appointments.date = event.appt_date;
      location.hash = 'appointments';
    }
  } else if (act === 'audit-page') {
    S.audit.page += +b.dataset.dir;
    loadAudit();
  } else if (act === 'add') openForm(key);
  else if (act === 'edit' && row) openForm(key, row);
  else if (act === 'delete' && row) confirmDelete(key, row);
  else if (act === 'adjust' && row) openAdjust(row);
  else if (act === 'request-reorder' && row) openReorder(row);
  else if (act === 'reorder') openAdjust(JSON.parse(b.dataset.item), 'receive');
  else if (act === 'page') { S.lists[key].page += +b.dataset.dir; loadList(key); }
  else if (act === 'goto') {
    S.lists[b.dataset.view].status = b.dataset.status || '';
    S.pendingAdd = b.dataset.add === '1';
    location.hash = b.dataset.view;
  }
  else if (act === 'logout') { try { await api('auth.php', { method: 'POST', body: { action: 'logout' } }); } finally { location.href = 'login.php'; } }
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
let timer;
document.addEventListener('input', e => {
  const f = e.target.dataset.filter; if (!f) return;
  const key = S.view, L = S.lists[key]; L[f] = e.target.value; L.page = 1;
  clearTimeout(timer); timer = setTimeout(() => loadList(key), 250);
});
window.addEventListener('hashchange', route);

(async function boot() {
  try {
    const [b, s, branches] = await Promise.all([
      api('read.php', { params: { r: 'barbers', limit: 100 } }),
      api('read.php', { params: { r: 'services', limit: 100 } }),
      api('read.php', { params: { r: 'branches', limit: 100 } }),
    ]);
    S.barbers = b.rows; S.services = s.rows;
    S.branches = branches.rows;
  } catch (e) { toast(e.error || 'Could not load lookups', true); }
  route();
})();
