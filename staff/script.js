/* PawHome Staff Panel
 * ------------------------------------------------------------------
 * Connected to the same database as the admin panel. Every server call
 * goes through API.request() to a PHP file in staff/api/ (JSON in, JSON out):
 *   POST api/applications_update.php   { id, status }        -> { ok: true }
 *   POST api/boarding_update.php       { id, status }        -> { ok: true }
 *   POST api/care_logs_create.php      { time, pet, caretaker, activity, notes }
 *                                      -> { ok: true, id, pet_name, caretaker_name, last_checkup? }
 *   POST api/pet_health_update.php     { id, health_status } -> { ok: true, label }
 *   GET  api/reports.php?period=...    -> { kpis: {...}, staff: [...] }
 * A 401 response sends the user back to login.php. Errors come back as
 * { ok: false, error: "message" } and are shown in a toast.
 */

(() => {
  'use strict';

  const API = {
    enabled: true,
    csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
    base: 'api/',

    async request(path, { method = 'GET', data } = {}) {
      if (!this.enabled) {
        await new Promise(r => setTimeout(r, 250));
        return { ok: true };
      }

      const headers = { 'X-Requested-With': 'fetch', 'X-CSRF-Token': this.csrf, 'Accept': 'application/json' };
      if (data) headers['Content-Type'] = 'application/json';

      const res = await fetch(this.base + path, {
        method,
        credentials: 'same-origin',
        headers,
        body: data ? JSON.stringify(data) : undefined
      });

      if (res.status === 401) {
        location.href = 'login.php';
        throw new Error('Not logged in');
      }
      const body = await res.json().catch(() => null);
      // The server explains what went wrong (e.g. "Kennel C-04 is already booked")
      if (!res.ok || !body || body.ok === false) {
        throw new Error((body && body.error) || 'Request failed: ' + res.status);
      }
      return body;
    }
  };


  /* ---------- Session ---------- */
  // PHP checks the login and prints the user's name, role and initials.

  document.getElementById('logoutBtn').addEventListener('click', () => {
    location.href = 'logout.php';
  });


  /* ---------- Helpers ---------- */

  function initials(name) {
    return name.trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase();
  }

  function escapeHTML(str) {
    return String(str).replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
  }

  function formatTime(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    const suffix = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;
    return `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
  }

  const toastRegion = document.getElementById('toastRegion');

  function toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    toastRegion.append(el);
    setTimeout(() => {
      el.classList.add('leaving');
      setTimeout(() => el.remove(), 300);
    }, 3500);
  }


  /* ---------- Panel tabs (keep selected panel on refresh) ---------- */

  const PANEL_TITLES = {
    applications: 'Applications',
    pets: 'Pets Under Care',
    boarding: 'Boarding Requests',
    logs: 'Daily Care Logs',
    reports: 'Performance Reports'
  };

  function syncTitle(key) {
    document.title = `${PANEL_TITLES[key] || 'Staff Panel'} — PawHome`;
  }

  const hashKey = location.hash.slice(1);
  const hashRadio = document.getElementById(hashKey + '-tab');
  if (hashRadio) hashRadio.checked = true;

  const checked = document.querySelector('input[name="panel"]:checked');
  syncTitle(checked.id.replace('-tab', ''));

  document.querySelectorAll('input[name="panel"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const key = radio.id.replace('-tab', '');
      history.replaceState(null, '', '#' + key);
      syncTitle(key);
      window.scrollTo(0, 0);
    });
  });


  /* ---------- Status filters ---------- */

  function applyFilter(select) {
    const panel = select.closest('.panel');
    const tbody = panel.querySelector('tbody');
    const value = select.value;
    let visible = 0;

    tbody.querySelectorAll('tr[data-status]').forEach(row => {
      const show = value === 'all' || row.dataset.status === value;
      row.hidden = !show;
      if (show) visible++;
    });

    let empty = tbody.querySelector('.empty-row');
    if (visible === 0) {
      if (!empty) {
        empty = document.createElement('tr');
        empty.className = 'empty-row';
        const cols = panel.querySelectorAll('thead th').length;
        empty.innerHTML = `<td colspan="${cols}"></td>`;
        tbody.append(empty);
      }
      empty.firstElementChild.textContent = value === 'all'
        ? 'Nothing here yet.'
        : (select.dataset.empty || 'Nothing to show.');
    } else if (empty) {
      empty.remove();
    }
  }

  document.querySelectorAll('select[data-filter]').forEach(select => {
    select.addEventListener('change', () => applyFilter(select));
    applyFilter(select);
  });


  /* ---------- Confirm dialog ---------- */

  const confirmDialog = document.getElementById('confirmDialog');
  const confirmTitle = document.getElementById('confirmTitle');
  const confirmMessage = document.getElementById('confirmMessage');
  const confirmOk = document.getElementById('confirmOk');

  function confirmAction({ title, message, confirmLabel, danger }) {
    return new Promise(resolve => {
      confirmTitle.textContent = title;
      confirmMessage.textContent = message;
      confirmOk.textContent = confirmLabel;
      confirmOk.className = 'btn ' + (danger ? 'danger' : 'primary');
      confirmDialog.returnValue = '';
      confirmDialog.showModal();
      confirmDialog.addEventListener('close', () => {
        resolve(confirmDialog.returnValue === 'confirm');
      }, { once: true });
    });
  }


  /* ---------- Approve / reject / confirm / decline ---------- */

  const BADGE = {
    pending:   { label: 'Pending',   cls: 'pending' },
    approved:  { label: 'Approved',  cls: 'approved' },
    rejected:  { label: 'Rejected',  cls: 'rejected' },
    confirmed: { label: 'Confirmed', cls: 'approved' },
    declined:  { label: 'Declined',  cls: 'rejected' },
    healthy:    { label: 'Healthy',           cls: 'approved' },
    monitoring: { label: 'Monitoring',        cls: 'pending' },
    medical:    { label: 'Medical Attention', cls: 'rejected' }
  };

  const DECISIONS = {
    approve: {
      status: 'approved',
      endpoint: 'applications_update.php',
      title: 'Approve application?',
      message: r => `${r.dataset.applicant}'s application to adopt ${r.dataset.pet} will be marked as approved.`,
      confirmLabel: 'Approve application',
      done: r => `Application from ${r.dataset.applicant} approved.`
    },
    reject: {
      status: 'rejected',
      endpoint: 'applications_update.php',
      title: 'Reject application?',
      message: r => `${r.dataset.applicant}'s application to adopt ${r.dataset.pet} will be marked as rejected.`,
      confirmLabel: 'Reject application',
      danger: true,
      done: r => `Application from ${r.dataset.applicant} rejected.`
    },
    confirm: {
      status: 'confirmed',
      endpoint: 'boarding_update.php',
      title: 'Confirm boarding stay?',
      message: r => `${r.dataset.pet}'s stay for ${r.dataset.owner} (${r.dataset.dates}) will be confirmed.`,
      confirmLabel: 'Confirm stay',
      done: r => `Boarding stay for ${r.dataset.pet} confirmed.`
    },
    decline: {
      status: 'declined',
      endpoint: 'boarding_update.php',
      title: 'Decline boarding request?',
      message: r => `${r.dataset.owner}'s request to board ${r.dataset.pet} (${r.dataset.dates}) will be declined.`,
      confirmLabel: 'Decline request',
      danger: true,
      done: r => `Boarding request for ${r.dataset.pet} declined.`
    }
  };

  async function handleDecision(button, cfg) {
    const row = button.closest('tr');

    const ok = await confirmAction({
      title: cfg.title,
      message: cfg.message(row),
      confirmLabel: cfg.confirmLabel,
      danger: cfg.danger
    });
    if (!ok) return;

    const buttons = row.querySelectorAll('.actions .btn');
    buttons.forEach(b => { b.disabled = true; });

    try {
      await API.request(cfg.endpoint, {
        method: 'POST',
        data: { id: row.dataset.id, status: cfg.status }
      });

      row.dataset.status = cfg.status;
      const badge = row.querySelector('.badge');
      badge.className = 'badge ' + BADGE[cfg.status].cls;
      badge.textContent = BADGE[cfg.status].label;
      row.querySelector('.actions').innerHTML = '<span class="decided">Reviewed</span>';

      const filter = row.closest('.panel').querySelector('select[data-filter]');
      if (filter) applyFilter(filter);

      toast(cfg.done(row));
    } catch (err) {
      buttons.forEach(b => { b.disabled = false; });
      toast(err.message.startsWith('Request failed') || err.message === 'Failed to fetch'
        ? "Couldn't save the change. Check your connection and try again." : err.message, 'error');
    }
  }


  /* ---------- Pet details ---------- */

  const petDialog = document.getElementById('petDialog');
  const logsBody = document.getElementById('logsBody');

  let currentPetRow = null;
  const healthForm = document.getElementById('healthForm'); // only shown to vets and senior staff

  function openPet(row) {
    currentPetRow = row;
    const d = row.dataset;
    if (healthForm) healthForm.health_status.value = d.status;
    const badgeSource = row.querySelector('.badge');

    document.getElementById('petName').textContent = d.name;
    document.getElementById('petBreed').textContent = d.breed;
    document.getElementById('petKennel').textContent = d.kennel;
    document.getElementById('petCaretaker').textContent = d.caretaker;
    document.getElementById('petCheckup').textContent = d.checkup;
    document.getElementById('petStatus').innerHTML = badgeSource ? badgeSource.outerHTML : '';

    const entries = [...logsBody.querySelectorAll('tr[data-pet-id]')]
      .filter(r => r.dataset.petId === d.id);

    const list = document.getElementById('petCare');
    if (entries.length === 0) {
      list.innerHTML = `<li class="care-empty">No care activity logged for ${escapeHTML(d.name)} today.</li>`;
    } else {
      list.innerHTML = entries.map(r => {
        const cells = r.children;
        return `<li>
          <span class="care-time">${escapeHTML(cells[0].textContent)}</span>
          <div>
            ${cells[3].innerHTML.trim()}
            <p>${escapeHTML(cells[4].textContent)} — ${escapeHTML(cells[2].textContent)}</p>
          </div>
        </li>`;
      }).join('');
    }

    petDialog.showModal();
  }

  if (healthForm) {
    healthForm.addEventListener('submit', async e => {
      e.preventDefault();
      const row = currentPetRow;
      const status = healthForm.health_status.value;
      if (!row || status === row.dataset.status) { petDialog.close(); return; }

      const btn = healthForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        await API.request('pet_health_update.php', { method: 'POST', data: { id: row.dataset.id, health_status: status } });
        row.dataset.status = status;
        const badge = row.querySelector('.badge');
        badge.className = 'badge ' + BADGE[status].cls;
        badge.textContent = BADGE[status].label;
        document.getElementById('petStatus').innerHTML = badge.outerHTML;
        const filter = row.closest('.panel').querySelector('select[data-filter]');
        if (filter) applyFilter(filter);
        toast(`${row.dataset.name} is now marked ${BADGE[status].label}.`);
      } catch (err) {
        toast(err.message || "Couldn't update the health status.", 'error');
      } finally {
        btn.disabled = false;
      }
    });
  }


  /* ---------- Add log entry ---------- */

  const logDialog = document.getElementById('logDialog');
  const logForm = document.getElementById('logForm');
  const saveLogBtn = logForm.querySelector('button[type="submit"]');

  const ACTIVITY_LABELS = {
    feeding: 'Feeding',
    walk: 'Walk',
    playtime: 'Playtime',
    grooming: 'Grooming',
    medication: 'Medication',
    health: 'Health Check'
  };

  function setFieldError(el, message) {
    const field = el.closest('.field');
    if (!field) return;
    field.classList.toggle('has-error', Boolean(message));
    const error = field.querySelector('.error');
    if (error) error.textContent = message || '';
    el.setAttribute('aria-invalid', message ? 'true' : 'false');
  }

  function validateRequired(form) {
    let firstInvalid = null;
    form.querySelectorAll('[required]').forEach(el => {
      const message = el.value.trim() ? '' : (el.dataset.error || 'This field is required.');
      setFieldError(el, message);
      if (message && !firstInvalid) firstInvalid = el;
    });
    if (firstInvalid) firstInvalid.focus();
    return !firstInvalid;
  }

  function openLogForm() {
    logForm.reset();
    logForm.querySelectorAll('[required]').forEach(el => setFieldError(el, ''));
    const now = new Date();
    document.getElementById('logTime').value =
      `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const caretaker = document.getElementById('logCaretaker');
    caretaker.value = caretaker.dataset.default || '';
    logDialog.showModal();
  }

  ['input', 'change'].forEach(evt => {
    logForm.addEventListener(evt, e => {
      if (e.target.matches('[required]') && e.target.value.trim()) setFieldError(e.target, '');
    });
  });

  logForm.addEventListener('submit', async e => {
    e.preventDefault();
    if (!validateRequired(logForm)) return;

    const entry = Object.fromEntries(new FormData(logForm));
    entry.notes = entry.notes.trim();

    saveLogBtn.disabled = true;
    saveLogBtn.textContent = 'Saving…';

    try {
      const saved = await API.request('care_logs_create.php', { method: 'POST', data: entry });

      const tr = document.createElement('tr');
      tr.dataset.time = entry.time;
      tr.dataset.petId = entry.pet;
      tr.className = 'row-new';
      tr.innerHTML = `
        <td>${formatTime(entry.time)}</td>
        <td class="applicant">${escapeHTML(saved.pet_name)}</td>
        <td>${escapeHTML(saved.caretaker_name)}</td>
        <td><span class="activity ${escapeHTML(entry.activity)}">${ACTIVITY_LABELS[entry.activity]}</span></td>
        <td>${escapeHTML(entry.notes)}</td>`;

      const emptyRow = logsBody.querySelector('.empty-row');
      if (emptyRow) emptyRow.remove();

      // A health check updates the pet's "Last checkup"
      if (saved.last_checkup) {
        const petRow = document.querySelector(`.pets-panel tr[data-id="${CSS.escape(String(entry.pet))}"]`);
        if (petRow) {
          petRow.dataset.checkup = saved.last_checkup;
          petRow.querySelector('.checkup').textContent = saved.last_checkup;
        }
      }

      // Keep newest first
      const later = [...logsBody.querySelectorAll('tr[data-time]')]
        .find(r => r.dataset.time <= entry.time);
      logsBody.insertBefore(tr, later || null);

      logDialog.close();
      toast(`Log entry saved for ${saved.pet_name}.`);
    } catch (err) {
      toast(err.message.startsWith('Request failed') || err.message === 'Failed to fetch'
        ? "Couldn't save the log entry. Check your connection and try again." : err.message, 'error');
    } finally {
      saveLogBtn.disabled = false;
      saveLogBtn.textContent = 'Save entry';
    }
  });


  /* ---------- Reports ---------- */

  const reportPeriod = document.getElementById('reportPeriod');
  const staffBody = document.getElementById('staffBody');

  function renderReport(report) {
    Object.entries(report.kpis).forEach(([key, kpi]) => {
      const card = document.querySelector(`.kpi[data-kpi="${key}"]`);
      if (!card) return;
      card.querySelector('.kpi-label').textContent = kpi.label;
      card.querySelector('.kpi-value').textContent = kpi.value;
      const change = card.querySelector('.kpi-change');
      change.textContent = kpi.change;
      change.className = 'kpi-change ' + (kpi.good ? 'good-text' : 'bad-text');
    });

    if (!report.staff.length) {
      staffBody.innerHTML = '<tr class="empty-row"><td colspan="6">No staff activity in this period.</td></tr>';
      return;
    }
    staffBody.innerHTML = report.staff.map(([name, ...rest]) =>
      `<tr><td class="applicant">${escapeHTML(name)}</td>${rest.map(v => `<td>${escapeHTML(v)}</td>`).join('')}</tr>`
    ).join('');
  }

  if (reportPeriod) reportPeriod.addEventListener('change', async () => {
    const period = reportPeriod.value;
    reportPeriod.disabled = true;
    try {
      renderReport(await API.request('reports.php?period=' + encodeURIComponent(period)));
    } catch (err) {
      toast("Couldn't load the report. Try again in a moment.", 'error');
    } finally {
      reportPeriod.disabled = false;
    }
  });


  /* ---------- Click handling ---------- */

  document.addEventListener('click', e => {
    const closeBtn = e.target.closest('[data-close]');
    if (closeBtn) {
      closeBtn.closest('dialog').close();
      return;
    }

    const btn = e.target.closest('[data-action]');
    if (!btn || btn.disabled) return;

    const action = btn.dataset.action;
    if (DECISIONS[action]) handleDecision(btn, DECISIONS[action]);
    else if (action === 'view-pet') openPet(btn.closest('tr'));
    else if (action === 'add-log') openLogForm();
  });

  // Close pet/log dialogs when clicking the backdrop
  [petDialog, logDialog].forEach(dialog => {
    dialog.addEventListener('click', e => {
      if (e.target === dialog) dialog.close();
    });
  });

})();
