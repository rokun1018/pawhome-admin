/* =========================================================
   PawHome Admin — app.js
   Front-end behaviour only. Anything marked "DEMO" is a
   stand-in for the PHP backend and should be removed/replaced
   once the matching PHP handler exists.
   ========================================================= */
(function () {
    'use strict';

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const camel = (s) => s.replace(/_([a-z])/g, (_, c) => c.toUpperCase());

    /* ---------- Toast notifications ---------- */
    function toast(message, type = 'success') {
        let wrap = $('.toast-container');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'toast-container';
            wrap.setAttribute('role', 'status');
            wrap.setAttribute('aria-live', 'polite');
            document.body.appendChild(wrap);
        }
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = message;
        wrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 250);
        }, 3200);
    }
    window.PawHome = { toast };

    function setLoading(btn, loading, text) {
        if (!btn) return;
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.classList.add('is-loading');
            if (text) btn.textContent = text;
        } else if (btn.dataset.originalHtml) {
            btn.classList.remove('is-loading');
            btn.innerHTML = btn.dataset.originalHtml;
        }
    }

    /* ---------- Sidebar (mobile) ---------- */
    $$('[data-sidebar-toggle]').forEach((btn) =>
        btn.addEventListener('click', () => document.body.classList.toggle('sidebar-open'))
    );
    $$('[data-sidebar-close]').forEach((el) =>
        el.addEventListener('click', () => document.body.classList.remove('sidebar-open'))
    );

    /* ---------- Dropdowns ---------- */
    $$('[data-dropdown-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = btn.closest('.dropdown');
            const willOpen = !dd.classList.contains('open');
            $$('.dropdown.open').forEach((d) => d.classList.remove('open'));
            dd.classList.toggle('open', willOpen);
            btn.setAttribute('aria-expanded', String(willOpen));
        });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) $$('.dropdown.open').forEach((d) => d.classList.remove('open'));
    });
    $$('[data-mark-read]').forEach((btn) =>
        btn.addEventListener('click', () => {
            $$('.notif-dot').forEach((d) => d.remove());
            toast('All notifications marked as read');
        })
    );

    /* ---------- Modals ---------- */
    function fillModal(modal, trigger) {
        const row = trigger ? trigger.closest('tr') : null;
        const form = $('form', modal);
        const title = $('.modal-title', modal);
        if (title && trigger && trigger.dataset.modalTitle) title.textContent = trigger.dataset.modalTitle;
        modal._row = row;

        if (form) {
            form.reset();
            $$('.has-error', form).forEach((g) => g.classList.remove('has-error'));
        }
        if (!row) return;

        // Fill inputs whose name matches a data-* attribute on the row
        // e.g. <input name="full_name"> <- <tr data-full-name="...">
        if (form) {
            $$('[name]', form).forEach((field) => {
                const val = row.dataset[camel(field.name)];
                if (val !== undefined && field.type !== 'file' && field.type !== 'password') field.value = val;
            });
        }
        // Fill read-only text: <span data-field="applicantName">
        $$('[data-field]', modal).forEach((el) => {
            const val = row.dataset[el.dataset.field];
            if (val !== undefined) el.textContent = val;
        });
    }

    function openModal(id, trigger) {
        const modal = document.getElementById(id);
        if (!modal) return;
        fillModal(modal, trigger);
        modal.classList.add('open');
        document.body.classList.add('modal-open');
        modal._trigger = trigger;
        const first = $('input:not([type=hidden]), select, textarea', modal);
        if (first) setTimeout(() => first.focus(), 60);
    }

    function closeModal(modal) {
        modal.classList.remove('open');
        if (!$('.modal.open')) document.body.classList.remove('modal-open');
        if (modal._trigger) modal._trigger.focus();
    }

    $$('[data-modal-open]').forEach((btn) =>
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(btn.dataset.modalOpen, btn);
        })
    );
    $$('.modal').forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.closest('[data-modal-close]')) closeModal(modal);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = $$('.modal.open').pop();
        if (open) closeModal(open);
        $$('.dropdown.open').forEach((d) => d.classList.remove('open'));
        document.body.classList.remove('sidebar-open');
    });
    // Open a modal from the URL, e.g. pets.html#open-petModal
    if (location.hash.startsWith('#open-')) openModal(location.hash.slice(6));

    /* ---------- Form validation helpers ---------- */
    function validateForm(form) {
        let ok = true;
        $$('.has-error', form).forEach((g) => g.classList.remove('has-error'));
        $$('[data-match]', form).forEach((field) => {
            const other = form.elements[field.dataset.match];
            if (other && field.value !== other.value) {
                field.closest('.form-group').classList.add('has-error');
                ok = false;
            }
        });
        if (!form.checkValidity()) {
            form.reportValidity();
            ok = false;
        }
        return ok;
    }

    /* DEMO: forms with data-demo-submit don't post anywhere yet.
       When the PHP handler is ready, remove data-demo-submit and set
       the form's action (e.g. action="actions/save-pet.php"). */
    $$('form[data-demo-submit]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!validateForm(form)) return;
            const btn = $('[type=submit]', form) || $('[form="' + form.id + '"][type=submit]');
            setLoading(btn, true);
            setTimeout(() => {
                setLoading(btn, false);
                const modal = form.closest('.modal');
                if (modal) closeModal(modal);
                toast(form.dataset.success || 'Changes saved');
                if (!modal && form.dataset.keep === undefined) {
                    $$('input[type=password]', form).forEach((p) => (p.value = ''));
                }
            }, 600);
        });
    });

    /* ---------- Password show / hide ---------- */
    $$('[data-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = $('input', btn.closest('.input-icon'));
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.classList.toggle('showing', show);
        });
    });

    /* ---------- Table filtering (search, selects, tabs) ---------- */
    const filterTables = new Set($$('[data-filter-table]').map((el) => el.dataset.filterTable));

    function applyFilters(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const controls = $$('[data-filter-table="' + tableId + '"]');
        const rows = $$('tbody tr:not(.empty-row)', table);
        let visible = 0;

        rows.forEach((row) => {
            let show = true;
            controls.forEach((ctrl) => {
                const key = ctrl.dataset.filter;
                if (ctrl.classList.contains('tabs')) {
                    const active = $('.tab.active', ctrl);
                    const val = active ? active.dataset.value : 'all';
                    if (val !== 'all' && row.dataset[key] !== val) show = false;
                } else if (key === 'search') {
                    const q = ctrl.value.trim().toLowerCase();
                    if (q && !row.textContent.toLowerCase().includes(q)) show = false;
                } else if (ctrl.value !== 'all' && row.dataset[key] !== ctrl.value) {
                    show = false;
                }
            });
            row.hidden = !show;
            if (show) visible++;
        });

        // Empty state
        let empty = $('.empty-row', table);
        if (!empty) {
            empty = document.createElement('tr');
            empty.className = 'empty-row';
            empty.innerHTML = '<td colspan="' + $$('thead th', table).length +
                '">No records match these filters. Try clearing the search or choosing "All".</td>';
            $('tbody', table).appendChild(empty);
        }
        empty.hidden = visible > 0;

        // Result count text
        $$('[data-result-count="' + tableId + '"]').forEach((el) => {
            el.textContent = 'Showing ' + visible + ' of ' + rows.length + ' ' + (el.dataset.noun || 'records');
        });

        // Tab counters
        $$('.tabs[data-filter-table="' + tableId + '"] .tab').forEach((tab) => {
            const c = $('.tab-count', tab);
            if (!c) return;
            const key = tab.parentElement.dataset.filter;
            const val = tab.dataset.value;
            c.textContent = val === 'all' ? rows.length : rows.filter((r) => r.dataset[key] === val).length;
        });
    }

    filterTables.forEach((tableId) => {
        $$('[data-filter-table="' + tableId + '"]').forEach((ctrl) => {
            if (ctrl.classList.contains('tabs')) {
                $$('.tab', ctrl).forEach((tab) =>
                    tab.addEventListener('click', () => {
                        $$('.tab', ctrl).forEach((t) => {
                            t.classList.remove('active');
                            t.setAttribute('aria-selected', 'false');
                        });
                        tab.classList.add('active');
                        tab.setAttribute('aria-selected', 'true');
                        applyFilters(tableId);
                    })
                );
            } else {
                ctrl.addEventListener('input', () => applyFilters(tableId));
                ctrl.addEventListener('change', () => applyFilters(tableId));
            }
        });
        applyFilters(tableId);
    });

    function refreshAllFilters() { filterTables.forEach(applyFilters); }

    /* ---------- Delete with confirmation ---------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        e.preventDefault();
        if (!window.confirm(btn.dataset.confirm)) return;
        const target = btn.closest('tr, .review-card');
        if (target) {
            target.style.transition = 'opacity .2s';
            target.style.opacity = '0';
            setTimeout(() => {
                target.remove();
                refreshAllFilters();
                document.dispatchEvent(new CustomEvent('pawhome:removed'));
            }, 200);
        }
        toast(btn.dataset.success || 'Deleted');
    });

    /* ---------- Change a row's status (approve / reject etc.) ---------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-set-status]');
        if (!btn) return;
        const modal = btn.closest('.modal');
        const row = btn.closest('tr') || (modal && modal._row);
        if (!row) return;
        const status = btn.dataset.setStatus;
        const label = btn.dataset.label || status;
        row.dataset.status = status;
        const badge = $('[data-status-badge]', row);
        if (badge) {
            badge.className = 'badge badge-' + status;
            badge.textContent = label;
        }
        if (modal) closeModal(modal);
        refreshAllFilters();
        toast(btn.dataset.success || 'Status updated to ' + label);
    });

    /* ---------- Tabbed panels (settings) ---------- */
    $$('[data-tabs]').forEach((nav) => {
        const buttons = $$('[data-tab]', nav);
        const show = (name) => {
            buttons.forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
            $$('[data-panel]').forEach((p) => (p.hidden = p.dataset.panel !== name));
        };
        buttons.forEach((b) =>
            b.addEventListener('click', () => {
                show(b.dataset.tab);
                history.replaceState(null, '', '#' + b.dataset.tab);
            })
        );
        const fromHash = location.hash.slice(1);
        show(buttons.some((b) => b.dataset.tab === fromHash) ? fromHash : buttons[0].dataset.tab);
    });

    /* ---------- Report / export buttons (DEMO) ---------- */
    $$('[data-report]').forEach((btn) =>
        btn.addEventListener('click', () => {
            setLoading(btn, true);
            setTimeout(() => {
                setLoading(btn, false);
                toast(btn.dataset.report + ' is ready. Real files will download once the PHP backend is connected.');
            }, 900);
        })
    );

    /* ---------- Login (DEMO) ----------
       Replace with: <form action="login.php" method="post"> and PHP
       password_verify() against the users table. */
    const loginForm = $('#loginForm');
    if (loginForm) {
        const DEMO_EMAIL = 'admin@pawhome.org';
        const DEMO_PASS = 'admin123';
        const alertBox = $('#loginAlert');
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            alertBox.hidden = true;
            if (!loginForm.checkValidity()) { loginForm.reportValidity(); return; }
            const email = loginForm.email.value.trim().toLowerCase();
            const pass = loginForm.password.value;
            const btn = $('[type=submit]', loginForm);
            setLoading(btn, true, 'Signing in…');
            setTimeout(() => {
                if (email === DEMO_EMAIL && pass === DEMO_PASS) {
                    window.location.href = 'index.html';
                } else {
                    setLoading(btn, false);
                    alertBox.hidden = false;
                    loginForm.password.value = '';
                    loginForm.password.focus();
                }
            }, 700);
        });
    }

    /* ---------- Forgot password (DEMO) ---------- */
    const forgotForm = $('#forgotForm');
    if (forgotForm) {
        forgotForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!forgotForm.checkValidity()) { forgotForm.reportValidity(); return; }
            const btn = $('[type=submit]', forgotForm);
            setLoading(btn, true, 'Sending…');
            setTimeout(() => {
                setLoading(btn, false);
                $('#forgotEmailShown').textContent = forgotForm.email.value.trim();
                $('#forgotSuccess').hidden = false;
                forgotForm.hidden = true;
            }, 800);
        });
    }

    /* ---------- Generate quiz (DEMO) ---------- */
    const genForm = $('#generateForm');
    if (genForm) {
        genForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!genForm.checkValidity()) { genForm.reportValidity(); return; }
            setLoading($('[type=submit]', genForm), true, 'Generating questions…');
            setTimeout(() => (window.location.href = 'review-quiz.html'), 1400);
        });
    }

    /* ---------- Review generated questions ---------- */
    const reviewList = $('#reviewList');
    if (reviewList) {
        const renumber = () => {
            const cards = $$('.review-card', reviewList);
            cards.forEach((c, i) => ($('.q-number', c).textContent = 'Question ' + (i + 1)));
            $$('[data-question-count]').forEach((el) => (el.textContent = cards.length));
            const saveBtn = $('#saveAllBtn');
            if (saveBtn) saveBtn.disabled = cards.length === 0;
        };
        document.addEventListener('pawhome:removed', renumber);

        // Pick the recommended answer
        reviewList.addEventListener('click', (e) => {
            const li = e.target.closest('.options-container li');
            if (!li || li.closest('.review-card').classList.contains('editing')) return;
            $$('li', li.parentElement).forEach((o) => o.classList.remove('recommended'));
            li.classList.add('recommended');
        });

        // Inline edit mode
        $$('[data-edit-card]', reviewList).forEach((btn) => {
            btn.addEventListener('click', () => {
                const card = btn.closest('.review-card');
                const editing = card.classList.toggle('editing');
                $$('.question-text, .options-container li', card).forEach((el) => (el.contentEditable = editing));
                btn.lastChild.textContent = editing ? ' Done' : ' Edit';
                if (editing) $('.question-text', card).focus();
                else toast('Question updated');
            });
        });

        $('#discardAllBtn')?.addEventListener('click', () => {
            if (window.confirm('Discard all generated questions? This cannot be undone.')) {
                window.location.href = 'generate-quiz.html';
            }
        });
        $('#regenerateBtn')?.addEventListener('click', (e) => {
            setLoading(e.currentTarget, true, 'Regenerating…');
            setTimeout(() => window.location.reload(), 1200);
        });
        $('#saveAllBtn')?.addEventListener('click', (e) => {
            const count = $$('.review-card', reviewList).length;
            setLoading(e.currentTarget, true, 'Saving…');
            setTimeout(() => {
                toast(count + ' questions saved to the question bank');
                setTimeout(() => (window.location.href = 'quiz-bank.html'), 900);
            }, 700);
        });
        renumber();
    }
})();
