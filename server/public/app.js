'use strict';

/* Morrow & Blade - API console.
   Plain browser JavaScript: no build step, no framework. It exists to show the
   Express API being driven the way the brief asks for - front-end JavaScript
   talking to a JavaScript back end - and it doubles as a manual test harness. */

// Served by the API itself, so relative paths normally work. Pass ?api=... to
// point the console at a service on another origin.
const API_BASE = (new URLSearchParams(location.search).get('api') || document.body.dataset.api || '').replace(/\/+$/, '');

const state = {
  token: localStorage.getItem('mb.token') || '',
  user: null,
  view: 'services',
  manage: false,
  nearest: null,
};

const $ = (selector, scope) => (scope || document).querySelector(selector);
const $$ = (selector, scope) => Array.from((scope || document).querySelectorAll(selector));

const esc = (value) => String(value === null || value === undefined ? '' : value)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const money = (pence) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' })
  .format((Number(pence) || 0) / 100);

const duration = (minutes) => {
  const value = Number(minutes) || 0;
  if (value < 60) return value + ' min';
  const hours = Math.floor(value / 60);
  const rest = value % 60;
  return rest ? hours + 'h ' + rest + 'm' : hours + 'h';
};

const when = (value) => {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T') + 'Z');
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-GB', {
    weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  });
};

async function api(path, options) {
  const opts = options || {};
  const headers = { Accept: 'application/json' };
  if (opts.body !== undefined) headers['Content-Type'] = 'application/json';
  if (state.token) headers.Authorization = 'Bearer ' + state.token;

  const response = await fetch(API_BASE + path, {
    method: opts.method || 'GET',
    headers,
    body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
  });

  const text = await response.text();
  let payload = null;
  if (text) {
    try { payload = JSON.parse(text); } catch (error) { payload = { ok: false, error: text }; }
  }

  if (!response.ok) {
    if (response.status === 401 && state.token) signOut(false);
    throw new Error((payload && payload.error) || ('Request failed with ' + response.status));
  }
  return payload || { ok: true };
}

let toastTimer = null;
function toast(message, bad) {
  const el = $('#toast');
  el.textContent = message;
  el.classList.toggle('bad', Boolean(bad));
  el.hidden = false;
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => { el.hidden = true; }, 4000);
}

const isStaff = () => Boolean(state.user && state.user.kind === 'staff');
const canManage = () => Boolean(state.user && (state.user.role === 'admin' || state.user.role === 'manager'));

const TABS = [
  { id: 'services', label: 'Treatments', show: () => true },
  { id: 'team', label: 'Team', show: () => true },
  { id: 'branches', label: 'Branches', show: () => true },
  { id: 'bookings', label: 'My bookings', show: () => Boolean(state.user && state.user.kind === 'customer') },
  { id: 'desk', label: 'Salon desk', show: () => isStaff() },
];

function renderTabs() {
  const nav = $('#tabs');
  nav.innerHTML = TABS.filter((tab) => tab.show()).map((tab) => (
    '<button type="button" data-view="' + tab.id + '" class="' + (state.view === tab.id ? 'on' : '') + '">'
    + esc(tab.label) + '</button>'
  )).join('');
}

function renderSession() {
  const el = $('#session');
  if (!state.user) {
    el.innerHTML = '<span class="who">Not signed in</span><button type="button" class="primary" id="sign-in">Sign in</button>';
    return;
  }
  const label = state.user.name || state.user.full_name || state.user.email;
  el.innerHTML = '<span class="who">' + esc(label) + '</span>'
    + '<span class="role">' + esc(state.user.role) + '</span>'
    + '<button type="button" class="ghost" id="sign-out">Sign out</button>';
}

function signOut(announce) {
  state.token = '';
  state.user = null;
  state.manage = false;
  localStorage.removeItem('mb.token');
  renderSession();
  renderTabs();
  go('services');
  if (announce !== false) toast('Signed out.');
}

async function go(view) {
  state.view = view;
  renderTabs();
  const el = $('#view');
  el.innerHTML = '<p class="lede">Loading...</p>';
  try {
    await VIEWS[view]();
  } catch (error) {
    el.innerHTML = '<h2>That did not load</h2><p class="lede">' + esc(error.message) + '</p>';
  }
}

const VIEWS = {};

/* ---------------------------------------------------------------- treatments */

VIEWS.services = async function services() {
  const result = await api('/api/services?active=all');
  const rows = result.data || [];
  state.manage = state.manage && canManage();

  const cards = rows.length ? rows.map((row) => (
    '<article class="card">'
    + '<h3>' + esc(row.name) + '</h3>'
    + '<p class="meta">' + esc(row.category_name || 'Uncategorised') + ' &middot; ' + duration(row.duration_minutes) + '</p>'
    + '<p><span class="price">' + money(row.price_pence) + '</span>'
    + (row.featured ? ' <span class="chip">Featured</span>' : '')
    + (row.active ? '' : ' <span class="chip">Hidden</span>') + '</p>'
    + (row.description ? '<p class="body">' + esc(row.description) + '</p>' : '')
    + (state.manage ? '<div class="row">'
      + '<button type="button" data-edit="service" data-id="' + row.id + '">Edit</button>'
      + '<button type="button" class="danger" data-remove="service" data-id="' + row.id + '">Remove</button>'
      + '</div>' : '')
    + '</article>'
  )).join('') : '<p class="empty">No treatments yet.</p>';

  $('#view').innerHTML = '<h2>Treatments</h2>'
    + '<p class="lede">Read straight from <code>GET /api/services</code>, the same rows the PHP site renders.</p>'
    + (canManage() ? '<div class="toolbar">'
      + '<button type="button" id="add-service" class="primary">New treatment</button>'
      + '<button type="button" id="toggle-manage">' + (state.manage ? 'Stop editing' : 'Edit list') + '</button>'
      + '</div>' : '')
    + '<div class="grid">' + cards + '</div>';

  $('#view').dataset.kind = 'service';
  $('#view').dataset.rows = JSON.stringify(rows);
};

/* ---------------------------------------------------------------------- team */

VIEWS.team = async function team() {
  const result = await api('/api/barbers?active=all');
  const rows = result.data || [];

  const cards = rows.length ? rows.map((row) => {
    let specialties = [];
    try { specialties = JSON.parse(row.specialties || '[]'); } catch (error) { specialties = []; }
    return '<article class="card">'
      + '<h3>' + esc(row.name) + '</h3>'
      + '<p class="meta">' + esc(row.role || 'Barber') + (row.salon_name ? ' &middot; ' + esc(row.salon_name) : '') + '</p>'
      + '<p class="meta">' + esc(String(row.rating)) + '&#9733; from ' + esc(row.review_count) + ' reviews &middot; '
      + esc(row.years_experience) + ' yrs</p>'
      + specialties.map((item) => '<span class="chip">' + esc(item) + '</span>').join('')
      + (row.bio ? '<p class="body">' + esc(row.bio) + '</p>' : '')
      + (state.manage ? '<div class="row">'
        + '<button type="button" data-edit="barber" data-id="' + row.id + '">Edit</button>'
        + '<button type="button" class="danger" data-remove="barber" data-id="' + row.id + '">Remove</button>'
        + '</div>' : '')
      + '</article>';
  }).join('') : '<p class="empty">Nobody on the team yet.</p>';

  $('#view').innerHTML = '<h2>The team</h2>'
    + '<p class="lede">From <code>GET /api/barbers</code>. Each card knows whether they offer a treatment and when they work.</p>'
    + (canManage() ? '<div class="toolbar">'
      + '<button type="button" id="add-barber" class="primary">New team member</button>'
      + '<button type="button" id="toggle-manage">' + (state.manage ? 'Stop editing' : 'Edit list') + '</button>'
      + '</div>' : '')
    + '<div class="grid">' + cards + '</div>';

  $('#view').dataset.kind = 'barber';
  $('#view').dataset.rows = JSON.stringify(rows);
};

/* ------------------------------------------------------------------ branches */

VIEWS.branches = async function branches() {
  const result = await api('/api/branches?active=all');
  const rows = result.data || [];
  state.rows = rows;

  const list = renderBranchCards(rows, state.nearest);

  $('#view').innerHTML = '<h2>Branches</h2>'
    + '<p class="lede">"Use my location" posts your coordinates to <code>GET /api/branches/nearest</code>, which sorts the branches in SQL.</p>'
    + '<div class="toolbar">'
    + '<button type="button" id="use-location" class="primary">Use my location</button>'
    + '<span id="locator-status" class="meta"></span>'
    + '<span class="spacer"></span>'
    + '</div>'
    + '<div class="grid" id="branch-list">' + list + '</div>';

  $('#use-location').addEventListener('click', findNearest);
};

function renderBranchCards(rows, nearest) {
  if (!rows.length) return '<p class="empty">No branches yet.</p>';
  const distances = {};
  (nearest || []).forEach((branch) => { distances[branch.id] = branch.distance_km; });

  return rows.map((row) => (
    '<article class="card">'
    + '<h3>' + esc(row.name) + (row.is_primary ? ' <span class="chip">Main</span>' : '') + '</h3>'
    + '<p class="meta">' + esc(row.address_line_1)
    + (row.address_line_2 ? ', ' + esc(row.address_line_2) : '')
    + ', ' + esc(row.city) + ' ' + esc(row.postcode) + '</p>'
    + (distances[row.id] !== undefined ? '<p class="price">' + esc(distances[row.id]) + ' km away</p>' : '')
    + (row.phone ? '<p class="meta">' + esc(row.phone) + '</p>' : '')
    + (row.email ? '<p class="meta">' + esc(row.email) + '</p>' : '')
    + '<a class="btn" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat='
    + encodeURIComponent(row.latitude) + '&mlon=' + encodeURIComponent(row.longitude) + '#map=16/'
    + encodeURIComponent(row.latitude) + '/' + encodeURIComponent(row.longitude) + '">Map</a>'
    + '</article>'
  )).join('');
}

async function findNearest() {
  const status = $('#locator-status');
  const button = $('#use-location');
  if (!navigator.geolocation) { toast('This browser has no location support.', true); return; }

  status.textContent = 'Finding you...';
  button.disabled = true;

  try {
    const position = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000 });
    });
    const { latitude, longitude } = position.coords;
    const result = await api('/api/branches/nearest?lat=' + encodeURIComponent(latitude) + '&lng=' + encodeURIComponent(longitude));
    state.nearest = result.data;
    $('#branch-list').innerHTML = renderBranchCards(result.data, result.data);
    status.textContent = 'Sorted by distance from you.';
    toast('Nearest branch: ' + (result.data[0] ? result.data[0].name : 'none found'));
  } catch (error) {
    status.textContent = '';
    toast(error.message || 'Could not read your location.', true);
  } finally {
    button.disabled = false;
  }
}
/* ------------------------------------------------------------------ bookings */

VIEWS.bookings = async function bookings() {
  const result = await api('/api/appointments');
  const rows = result.data || [];

  const body = rows.length ? rows.map((row) => (
    '<tr>'
    + '<td><b>' + esc(row.reference) + '</b></td>'
    + '<td>' + esc(row.service_name) + '</td>'
    + '<td>' + esc(row.barber_name) + '</td>'
    + '<td>' + esc(when(row.starts_at)) + '</td>'
    + '<td><span class="status ' + esc(row.status) + '">' + esc(row.status.replace('_', ' ')) + '</span></td>'
    + '<td>' + (row.status === 'pending' || row.status === 'confirmed'
      ? '<button type="button" class="danger" data-cancel="' + row.id + '">Cancel</button>' : '') + '</td>'
    + '</tr>'
  )).join('') : '<tr><td colspan="6">No bookings yet.</td></tr>';

  $('#view').innerHTML = '<h2>My bookings</h2>'
    + '<p class="lede"><code>GET /api/appointments</code> is scoped to your account by the token, not by a query string.</p>'
    + '<div class="toolbar"><button type="button" id="new-booking" class="primary">Book an appointment</button></div>'
    + '<table><thead><tr><th>Reference</th><th>Treatment</th><th>With</th><th>When</th><th>Status</th><th></th></tr></thead>'
    + '<tbody>' + body + '</tbody></table>';
};

/* --------------------------------------------------------------- salon desk */

VIEWS.desk = async function desk() {
  const [appointments, reports] = await Promise.all([
    api('/api/appointments?upcoming=1'),
    canManage() ? api('/api/reports/summary').catch(() => null) : Promise.resolve(null),
  ]);

  const rows = appointments.data || [];
  const statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

  const stats = reports ? (function () {
    const data = reports.data;
    const total = data.totals.bookings;
    const byStatus = {};
    data.totals.by_status.forEach((row) => { byStatus[row.status] = row.bookings; });
    return '<div class="stats">'
      + '<div class="stat"><b>' + esc(total) + '</b><span>bookings, last 30 days</span></div>'
      + '<div class="stat"><b>' + money(data.revenue_pence) + '</b><span>completed revenue</span></div>'
      + '<div class="stat"><b>' + esc(data.upcoming.upcoming) + '</b><span>still to come</span></div>'
      + '<div class="stat"><b>' + esc(byStatus.completed || 0) + '</b><span>completed</span></div>'
      + '</div>';
  })() : '';

  const body = rows.length ? rows.map((row) => (
    '<tr>'
    + '<td>' + esc(when(row.starts_at)) + '</td>'
    + '<td><b>' + esc(row.customer_name) + '</b><br><span class="meta">' + esc(row.customer_email) + '</span></td>'
    + '<td>' + esc(row.service_name) + '</td>'
    + '<td>' + esc(row.barber_name) + '</td>'
    + '<td>' + money(row.price_pence_snapshot) + '</td>'
    + '<td><select data-status="' + row.id + '">'
    + statuses.map((status) => '<option value="' + status + '"' + (status === row.status ? ' selected' : '') + '>'
      + status.replace('_', ' ') + '</option>').join('')
    + '</select></td>'
    + '</tr>'
  )).join('') : '<tr><td colspan="6">Nothing on the book.</td></tr>';

  $('#view').innerHTML = '<h2>Salon desk</h2>'
    + '<p class="lede">The staff view: every upcoming booking, plus the report figures when you manage the salon.</p>'
    + stats
    + '<table><thead><tr><th>When</th><th>Customer</th><th>Treatment</th><th>With</th><th>Price</th><th>Status</th></tr></thead>'
    + '<tbody>' + body + '</tbody></table>';
};

/* ------------------------------------------------------------- item editing */

const SPECS = {
  service: {
    noun: 'treatment',
    path: '/api/services',
    back: 'services',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'description', label: 'Description', type: 'textarea' },
      { name: 'duration_minutes', label: 'Duration (minutes)', type: 'number', min: 10, max: 240, required: true },
      { name: 'price_pence', label: 'Price in pence', type: 'number', min: 1, required: true },
      { name: 'featured', label: 'Featured on the home page', type: 'checkbox' },
      { name: 'active', label: 'Visible on the site', type: 'checkbox', fallback: true },
      { name: 'display_order', label: 'Display order', type: 'number', min: 0, fallback: 0 },
    ],
  },
  barber: {
    noun: 'team member',
    path: '/api/barbers',
    back: 'team',
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'role', label: 'Role', type: 'text', fallback: 'Barber' },
      { name: 'staff_kind', label: 'Kind', type: 'select', options: ['barber', 'beautician', 'nail_technician', 'therapist'], fallback: 'barber' },
      { name: 'bio', label: 'Bio', type: 'textarea' },
      { name: 'specialties', label: 'Specialties (comma separated)', type: 'list' },
      { name: 'years_experience', label: 'Years of experience', type: 'number', min: 0, fallback: 0 },
      { name: 'rating', label: 'Rating (0 to 5)', type: 'number', min: 0, max: 5, step: 0.1, fallback: 5 },
      { name: 'review_count', label: 'Review count', type: 'number', min: 0, fallback: 0 },
      { name: 'photo_url', label: 'Photo URL', type: 'text' },
      { name: 'active', label: 'Visible on the site', type: 'checkbox', fallback: true },
      { name: 'display_order', label: 'Display order', type: 'number', min: 0, fallback: 0 },
    ],
  },
};

function fieldValue(field, record) {
  const raw = record ? record[field.name] : undefined;
  if (field.type === 'list') {
    let list = [];
    try { list = JSON.parse(raw || '[]'); } catch (error) { list = []; }
    return Array.isArray(list) ? list.join(', ') : '';
  }
  if (raw !== undefined && raw !== null) {
    if (field.type === 'checkbox') return Number(raw) === 1 ? 'checked' : '';
    return String(raw).replace(/"/g, '&quot;');
  }
  return field.fallback === true ? 'checked' : (field.fallback === undefined ? '' : String(field.fallback));
}

function openItemDialog(kind, id) {
  const spec = SPECS[kind];
  const rows = JSON.parse($('#view').dataset.rows || '[]');
  const record = id ? rows.find((row) => row.id === id) : null;

  $('#item-title').textContent = (record ? 'Edit ' : 'New ') + spec.noun;
  $('#item-fields').innerHTML = spec.fields.map((field) => {
    const value = fieldValue(field, record);
    if (field.type === 'checkbox') {
      return '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="'
        + field.name + '" ' + value + '> ' + esc(field.label) + '</label>';
    }
    if (field.type === 'textarea') {
      return '<label>' + esc(field.label) + '<textarea name="' + field.name + '" rows="3">' + value + '</textarea></label>';
    }
    if (field.type === 'select') {
      return '<label>' + esc(field.label) + '<select name="' + field.name + '">'
        + field.options.map((option) => '<option value="' + option + '"'
          + (option === value ? ' selected' : '') + '>' + option.replace('_', ' ') + '</option>').join('')
        + '</select></label>';
    }
    return '<label>' + esc(field.label) + '<input type="' + (field.type === 'number' ? 'number' : 'text')
      + '" name="' + field.name + '" value="' + value + '"'
      + (field.min === undefined ? '' : ' min="' + field.min + '"')
      + (field.max === undefined ? '' : ' max="' + field.max + '"')
      + (field.step === undefined ? '' : ' step="' + field.step + '"') + '></label>';
  }).join('');

  $('#item-error').hidden = true;
  const dialog = $('#item-dialog');
  dialog.dataset.kind = kind;
  dialog.dataset.id = record ? record.id : '';
  dialog.showModal();
}

async function saveItem() {
  const dialog = $('#item-dialog');
  const spec = SPECS[dialog.dataset.kind];
  const error = $('#item-error');
  const body = {};

  spec.fields.forEach((field) => {
    const input = $('#item-fields [name="' + field.name + '"]');
    if (field.type === 'checkbox') { body[field.name] = input.checked; return; }
    const value = input.value.trim();
    if (field.type === 'number') { body[field.name] = value === '' ? undefined : Number(value); return; }
    if (field.type === 'list') {
      body[field.name] = value === '' ? [] : value.split(',').map((item) => item.trim()).filter(Boolean);
      return;
    }
    body[field.name] = value;
  });

  Object.keys(body).forEach((key) => { if (body[key] === undefined) delete body[key]; });

  try {
    if (dialog.dataset.id) {
      await api(spec.path + '/' + dialog.dataset.id, { method: 'PATCH', body });
    } else {
      await api(spec.path, { method: 'POST', body });
    }
    dialog.close();
    toast('Saved.');
    go(spec.back);
  } catch (err) {
    error.textContent = err.message;
    error.hidden = false;
  }
}

/* -------------------------------------------------------------- sign-in box */

function openAuth(register) {
  const dialog = $('#auth-dialog');
  $('#auth-form').reset();
  $('#auth-error').hidden = true;
  dialog.dataset.mode = 'customer';
  setAuthMode('customer', Boolean(register));
  dialog.showModal();
}

function setAuthMode(mode, register) {
  const dialog = $('#auth-dialog');
  dialog.dataset.mode = mode;
  dialog.dataset.register = register ? '1' : '';
  $$('#auth-mode button').forEach((button) => button.classList.toggle('on', button.dataset.mode === mode));
  $('#register-fields').hidden = !register || mode === 'staff';
  $('#auth-register').hidden = mode === 'staff' || register;
  $('#auth-register').textContent = 'Create account';
  $('#auth-submit').textContent = register ? 'Create account' : 'Sign in';
  $('#auth-title').textContent = register ? 'Create your account' : 'Sign in';
}

async function submitAuth() {
  const dialog = $('#auth-dialog');
  const form = $('#auth-form');
  const error = $('#auth-error');
  const mode = dialog.dataset.mode;
  const register = dialog.dataset.register === '1';
  const data = Object.fromEntries(new FormData(form).entries());

  try {
    let result;
    if (register && mode === 'customer') {
      result = await api('/api/auth/register', { method: 'POST', body: data });
    } else if (mode === 'staff') {
      result = await api('/api/auth/staff/login', { method: 'POST', body: data });
    } else {
      result = await api('/api/auth/login', { method: 'POST', body: data });
    }

    state.token = result.data.token;
    state.user = result.data.user;
    localStorage.setItem('mb.token', state.token);
    dialog.close();
    renderSession();
    renderTabs();
    toast('Signed in as ' + (state.user.name || state.user.email) + '.');
    go(state.user.kind === 'staff' ? 'desk' : 'bookings');
  } catch (err) {
    error.textContent = err.message;
    error.hidden = false;
  }
}

/* ------------------------------------------------------------ booking sheet */

async function openBooking() {
  const [services, barbers] = await Promise.all([api('/api/services'), api('/api/barbers')]);
  $('#booking-service').innerHTML = (services.data || []).map((row) => (
    '<option value="' + row.id + '">' + esc(row.name) + ' - ' + money(row.price_pence) + '</option>'
  )).join('');
  $('#booking-barber').innerHTML = (barbers.data || []).map((row) => (
    '<option value="' + row.id + '">' + esc(row.name) + '</option>'
  )).join('');

  const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);
  tomorrow.setHours(10, 0, 0, 0);
  $('#booking-when').value = new Date(tomorrow.getTime() - tomorrow.getTimezoneOffset() * 60000)
    .toISOString().slice(0, 16);

  $('#booking-error').hidden = true;
  $('#booking-dialog').showModal();
}

async function submitBooking() {
  const error = $('#booking-error');
  const local = $('#booking-when').value;

  try {
    // The field gives local wall-clock time; toISOString() makes the instant
    // unambiguous before the API converts it to the naive UTC it stores.
    const startsAt = new Date(local).toISOString();
    const result = await api('/api/appointments', {
      method: 'POST',
      body: {
        service_id: $('#booking-service').value,
        barber_id: $('#booking-barber').value,
        starts_at: startsAt,
        notes: $('#booking-form [name="notes"]').value,
      },
    });
    $('#booking-dialog').close();
    toast('Booked. Reference ' + result.data.reference + '.');
    go('bookings');
  } catch (err) {
    error.textContent = err.message;
    error.hidden = false;
  }
}

/* ------------------------------------------------------------------ wiring */

$('#tabs').addEventListener('click', (event) => {
  const button = event.target.closest('[data-view]');
  if (button) go(button.dataset.view);
});

$('#session').addEventListener('click', (event) => {
  if (event.target.id === 'sign-in') openAuth(false);
  if (event.target.id === 'sign-out') signOut(true);
});

document.addEventListener('click', async (event) => {
  const target = event.target;

  if (target.dataset.close !== undefined) { target.closest('dialog').close(); return; }

  if (target.id === 'add-service') { openItemDialog('service'); return; }
  if (target.id === 'add-barber') { openItemDialog('barber'); return; }
  if (target.id === 'toggle-manage') { state.manage = !state.manage; go(state.view); return; }
  if (target.id === 'new-booking') { openBooking(); return; }

  if (target.dataset.edit) { openItemDialog(target.dataset.edit, target.dataset.id); return; }

  if (target.dataset.remove) {
    const kind = target.dataset.remove;
    const spec = SPECS[kind];
    if (!window.confirm('Remove this ' + spec.noun + '?')) return;
    try {
      await api(spec.path + '/' + target.dataset.id, { method: 'DELETE' });
      toast('Removed.');
      go(spec.back);
    } catch (error) { toast(error.message, true); }
    return;
  }

  if (target.dataset.cancel) {
    try {
      await api('/api/appointments/' + target.dataset.cancel, { method: 'DELETE' });
      toast('Booking cancelled.');
      go('bookings');
    } catch (error) { toast(error.message, true); }
  }
});

document.addEventListener('change', async (event) => {
  const select = event.target.closest('[data-status]');
  if (!select) return;
  try {
    await api('/api/appointments/' + select.dataset.status, {
      method: 'PATCH',
      body: { status: select.value },
    });
    toast('Status updated.');
  } catch (error) {
    toast(error.message, true);
    go('desk');
  }
});

$('#auth-mode').addEventListener('click', (event) => {
  const button = event.target.closest('[data-mode]');
  if (button) setAuthMode(button.dataset.mode, false);
});

$('#auth-submit').addEventListener('click', submitAuth);
$('#auth-register').addEventListener('click', () => setAuthMode('customer', true));
$('#booking-submit').addEventListener('click', submitBooking);
$('#item-submit').addEventListener('click', saveItem);

(async function init() {
  renderSession();

  if (state.token) {
    try {
      const me = await api('/api/auth/me');
      state.user = me.data;
    } catch (error) {
      state.token = '';
      localStorage.removeItem('mb.token');
    }
  }

  renderSession();
  renderTabs();
  await go('services');
})();