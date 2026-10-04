/* PawHome staff login / sign up
 * ------------------------------------------------------------------
 * Validation runs in the browser either way. The server must repeat it.
 *
 * USE_BACKEND = false  -> demo mode: no real accounts, any password logs in.
 * USE_BACKEND = true   -> forms submit normally to:
 *     auth/login.php     (email, password)
 *     auth/register.php  (full_name, email, role, password, password_confirm)
 *   PHP should hash passwords with password_hash(), check with password_verify(),
 *   start a session, then redirect to the staff panel.
 */

(() => {
  'use strict';

  const USE_BACKEND = true; // forms go to staff/auth/login.php and staff/auth/register.php
  const SESSION_KEY = 'pawhome_staff';
  const DEMO_ACCOUNTS_KEY = 'pawhome_demo_accounts'; // name + role only, never passwords

  const loginTab = document.getElementById('loginTab');
  const signupTab = document.getElementById('signupTab');
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  const notice = document.getElementById('authNotice');

  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


  /* ---------- Tabs ---------- */

  function showTab(which, { focus = false } = {}) {
    const isLogin = which === 'login';
    loginTab.setAttribute('aria-selected', String(isLogin));
    signupTab.setAttribute('aria-selected', String(!isLogin));
    loginTab.tabIndex = isLogin ? 0 : -1;
    signupTab.tabIndex = isLogin ? -1 : 0;
    loginForm.hidden = !isLogin;
    signupForm.hidden = isLogin;
    [loginForm, signupForm].forEach(form =>
      form.querySelectorAll('.field input, .field select').forEach(el => setError(el, ''))
    );
    document.title = (isLogin ? 'Staff Login' : 'Create Staff Account') + ' — PawHome';
    history.replaceState(null, '', isLogin ? location.pathname : '#signup');
    if (focus) (isLogin ? loginForm : signupForm).querySelector('input').focus();
  }

  loginTab.addEventListener('click', () => { hideNotice(); showTab('login'); });
  signupTab.addEventListener('click', () => { hideNotice(); showTab('signup'); });

  // Arrow keys move between tabs
  [loginTab, signupTab].forEach(tab => {
    tab.addEventListener('keydown', e => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      const next = tab === loginTab ? signupTab : loginTab;
      next.click();
      next.focus();
    });
  });

  document.querySelectorAll('[data-switch]').forEach(btn => {
    btn.addEventListener('click', () => {
      hideNotice();
      showTab(btn.dataset.switch, { focus: true });
    });
  });

  if (location.hash === '#signup') showTab('signup');


  /* ---------- Notice ---------- */

  function showNotice(message, type = 'success') {
    notice.textContent = message;
    notice.className = 'notice' + (type === 'error' ? ' error' : '');
    notice.hidden = false;
  }

  function hideNotice() {
    notice.hidden = true;
  }


  /* ---------- Validation ---------- */

  function setError(input, message) {
    const field = input.closest('.field');
    field.classList.toggle('has-error', Boolean(message));
    field.querySelector('.error').textContent = message || '';
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
  }

  function runChecks(checks) {
    let firstInvalid = null;
    checks.forEach(([input, message]) => {
      setError(input, message);
      if (message && !firstInvalid) firstInvalid = input;
    });
    if (firstInvalid) firstInvalid.focus();
    return !firstInvalid;
  }

  // Clear a field's error as soon as the user edits it
  [loginForm, signupForm].forEach(form => {
    ['input', 'change'].forEach(evt => {
      form.addEventListener(evt, e => {
        if (e.target.closest('.field')) setError(e.target, '');
      });
    });
  });

  function validateLogin() {
    const email = loginForm.email.value.trim();
    const password = loginForm.password.value;
    return runChecks([
      [loginForm.email,
        !email ? 'Enter your email address.' :
        !EMAIL_RE.test(email) ? 'Enter a valid email address, like name@pawhome.org.' : ''],
      [loginForm.password, !password ? 'Enter your password.' : '']
    ]);
  }

  function validateSignup() {
    const f = signupForm;
    const name = f.full_name.value.trim();
    const email = f.email.value.trim();
    const password = f.password.value;
    const confirm = f.password_confirm.value;
    return runChecks([
      [f.full_name, name.length < 2 ? 'Enter your full name.' : ''],
      [f.email,
        !email ? 'Enter your email address.' :
        !EMAIL_RE.test(email) ? 'Enter a valid email address, like name@pawhome.org.' : ''],
      [f.role, !f.role.value ? 'Choose your role.' : ''],
      [f.password,
        !password ? 'Create a password.' :
        password.length < 8 ? 'Use at least 8 characters.' : ''],
      [f.password_confirm,
        !confirm ? 'Re-enter your password.' :
        confirm !== password ? "Passwords don't match." : '']
    ]);
  }


  /* ---------- Demo helpers (removed once PHP handles accounts) ---------- */

  function demoAccounts() {
    try { return JSON.parse(localStorage.getItem(DEMO_ACCOUNTS_KEY)) || {}; }
    catch { return {}; }
  }

  function nameFromEmail(email) {
    return email.split('@')[0]
      .split(/[._-]+/)
      .filter(Boolean)
      .map(p => p[0].toUpperCase() + p.slice(1))
      .join(' ') || 'Staff Member';
  }

  function setBusy(form, busy, label) {
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = busy;
    btn.textContent = label;
  }


  /* ---------- Submit ---------- */

  loginForm.addEventListener('submit', e => {
    e.preventDefault();
    hideNotice();
    if (!validateLogin()) return;

    if (USE_BACKEND) {
      setBusy(loginForm, true, 'Logging in…');
      loginForm.submit();
      return;
    }

    setBusy(loginForm, true, 'Logging in…');
    const email = loginForm.email.value.trim().toLowerCase();
    const account = demoAccounts()[email];
    const staff = account || { name: nameFromEmail(email), role: 'Staff' };

    try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(staff)); } catch { /* storage unavailable */ }
    setTimeout(() => { location.href = 'index.html'; }, 400);
  });

  signupForm.addEventListener('submit', e => {
    e.preventDefault();
    hideNotice();
    if (!validateSignup()) return;

    if (USE_BACKEND) {
      setBusy(signupForm, true, 'Creating account…');
      signupForm.submit();
      return;
    }

    setBusy(signupForm, true, 'Creating account…');
    const email = signupForm.email.value.trim().toLowerCase();
    const accounts = demoAccounts();
    accounts[email] = { name: signupForm.full_name.value.trim(), role: signupForm.role.value };
    try { localStorage.setItem(DEMO_ACCOUNTS_KEY, JSON.stringify(accounts)); } catch { /* storage unavailable */ }

    setTimeout(() => {
      setBusy(signupForm, false, 'Create account');
      signupForm.reset();
      showTab('login');
      loginForm.email.value = email;
      loginForm.password.focus();
      showNotice('Account created. Log in to continue.');
    }, 400);
  });

  // Reset buttons if the user comes back with the browser's back button
  window.addEventListener('pageshow', () => {
    setBusy(loginForm, false, 'Log in');
    setBusy(signupForm, false, 'Create account');
  });

  // Messages passed back by PHP, e.g. login.php?error=invalid or ?registered=1
  const params = new URLSearchParams(location.search);
  if (params.get('error') === 'invalid') showNotice('Email or password is incorrect.', 'error');
  if (params.get('error') === 'exists') { showTab('signup'); showNotice('An account with this email already exists.', 'error'); }
  if (params.get('error') === 'pending') showNotice('Your account is waiting for a Super Admin to approve it. Try again once they have.', 'error');
  if (params.get('error') === 'inactive') showNotice('This account has been switched off. Ask a Super Admin if you need access.', 'error');
  if (params.get('error') === 'expired') showNotice('The page expired. Please try again.', 'error');
  if (params.get('error') === 'invalid_signup') { showTab('signup'); showNotice('Please check the form and try again.', 'error'); }
  if (params.get('registered') === '1') showNotice('Account created. A Super Admin needs to approve it before you can log in.');
  if (params.get('logged_out') === '1') showNotice('You have been logged out.');

})();
