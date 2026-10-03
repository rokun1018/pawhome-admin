/* =====================================================================
   backend.js
   Connects the design's front-end (app.js) to the PHP backend.
   app.js still handles menus, modals, filters and toasts; this file only
   makes forms and buttons save to the database. Load it after app.js.
   ===================================================================== */
(function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var currentRow = null; // the table row whose modal is open

    function toast(msg) {
        if (window.PawHome && PawHome.toast) PawHome.toast(msg); else alert(msg);
    }

    function post(url, data) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(url, {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'The server sent an unexpected reply.' }; });
        });
    }

    function snake(key) {
        return key.replace(/[A-Z]/g, function (m) { return '_' + m.toLowerCase(); });
    }

    /* 1. Real form submits ---------------------------------------------
       Forms marked data-server go straight to PHP. Stopping the event here
       (capture phase) keeps app.js's demo handlers from faking the save. */
    window.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form.matches || !form.matches('form[data-server]')) return;
        ev.stopPropagation();

        // "Confirm password" boxes
        var bad = null;
        form.querySelectorAll('[data-match]').forEach(function (input) {
            var other = form.querySelector('[name="' + input.dataset.match + '"]');
            input.setCustomValidity(other && other.value !== input.value ? 'Passwords do not match.' : '');
            if (!bad && !input.checkValidity()) bad = input;
        });
        if (bad) { ev.preventDefault(); bad.reportValidity(); return; }

        if (form.dataset.confirm && !confirm(form.dataset.confirm)) { ev.preventDefault(); return; }

        var btn = form.querySelector('button[type="submit"], button:not([type])');
        if (btn) setTimeout(function () { btn.disabled = true; }, 0);
    }, true);

    // Clear the "passwords do not match" message as the person types
    document.addEventListener('input', function (ev) {
        if (ev.target.matches('[data-match]')) ev.target.setCustomValidity('');
    });

    // Search links like pets.php?q=Luna: run the page's own filter once it has loaded
    window.addEventListener('load', function () {
        setTimeout(function () {
            document.querySelectorAll('input[data-filter="search"]').forEach(function (input) {
                if (!input.value) return;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('keyup', { bubbles: true }));
            });
        }, 0);
    });

    /* 2. Fill edit forms from the row's data-* attributes ----------------- */
    function fillForm(form, row) {
        form.reset();
        var idField = form.querySelector('input[name="id"]');
        if (idField) idField.value = row ? (row.dataset.id || '') : '';

        // Fields that are only required when adding (e.g. a new user's password)
        form.querySelectorAll('[data-required-on-add]').forEach(function (el) {
            el.required = !row;
        });
        if (!row) return;

        Object.keys(row.dataset).forEach(function (key) {
            var value = row.dataset[key];
            var name = snake(key);

            if (name === 'options') { // quiz answers: ["A","B",...]
                var list = [];
                try { list = JSON.parse(value); } catch (e) {}
                form.querySelectorAll('[name="options[]"]').forEach(function (input, i) {
                    input.value = list[i] || '';
                });
                return;
            }
            form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
                if (el.type === 'file' || el.type === 'password') return;
                if (el.type === 'radio') el.checked = (el.value === value);
                else if (el.type === 'checkbox') el.checked = (value === '1' || value === 'true');
                else el.value = value;
            });
        });
    }

    document.addEventListener('click', function (ev) {
        var trigger = ev.target.closest('[data-modal-open]');
        if (!trigger) return;
        var modal = document.getElementById(trigger.dataset.modalOpen);
        currentRow = trigger.closest('tr[data-id]');
        // Run after app.js has opened the modal
        setTimeout(function () {
            if (!modal) return;
            var form = modal.querySelector('form[data-server]');
            if (form) fillForm(form, currentRow);
            var notes = modal.querySelector('textarea[name="admin_notes"]');
            if (notes) notes.value = currentRow ? (currentRow.dataset.adminNotes || '') : '';
        }, 0);
    });

    /* 3. Adoption decisions (approve / reject / under review) ----------- */
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-set-status]');
        if (!btn || !document.getElementById('appsTable')) return;
        var row = btn.closest('tr[data-id]') || currentRow;
        if (!row) return;
        var data = { action: 'status', id: row.dataset.id, status: btn.dataset.setStatus };
        var notes = btn.closest('.modal') && btn.closest('.modal').querySelector('textarea[name="admin_notes"]');
        if (notes) { data.admin_notes = notes.value; row.dataset.adminNotes = notes.value; }

        post('adoptions.php', data).then(function (res) {
            if (!res.ok) { alert(res.error || 'That change was not saved. Refresh the page and try again.'); location.reload(); }
            else row.dataset.status = btn.dataset.setStatus;
        });
    });

    // Internal notes save when you click away from the box
    document.addEventListener('change', function (ev) {
        if (!ev.target.matches('textarea[name="admin_notes"]') || !currentRow) return;
        var value = ev.target.value;
        post('adoptions.php', { action: 'notes', id: currentRow.dataset.id, admin_notes: value }).then(function (res) {
            if (res.ok) { currentRow.dataset.adminNotes = value; toast('Notes saved'); }
            else alert(res.error || 'Notes were not saved.');
        });
    });

    /* 4. Review AI questions: save, regenerate, discard ------------------ */
    function textOf(el) {
        if (!el) return '';
        var input = el.querySelector('input, textarea');
        return (input ? input.value : el.textContent).trim();
    }

    window.addEventListener('click', function (ev) {
        var btn = ev.target.closest('#saveAllBtn, #regenerateBtn, #discardAllBtn');
        if (!btn || !document.getElementById('reviewList')) return;
        ev.stopPropagation();
        ev.preventDefault();

        if (btn.id === 'regenerateBtn') {
            if (confirm('Replace these questions with a new set?')) {
                btn.disabled = true;
                document.getElementById('regenerateForm').submit();
            }
            return;
        }
        if (btn.id === 'discardAllBtn') {
            if (confirm('Discard all generated questions?')) document.getElementById('discardForm').submit();
            return;
        }

        var questions = [];
        document.querySelectorAll('#reviewList .review-card').forEach(function (card) {
            var items = Array.prototype.slice.call(card.querySelectorAll('.options-container li'));
            var options = items.map(textOf);
            var recommended = items.findIndex(function (li) { return li.classList.contains('recommended'); });
            questions.push({
                question_text: textOf(card.querySelector('.question-text')),
                category: card.dataset.category,
                pet_type: card.dataset.petType,
                difficulty: card.dataset.difficulty,
                options: options,
                recommended_option: recommended < 0 ? 0 : recommended
            });
        });
        if (!questions.length) { alert('There are no questions left to save.'); return; }

        btn.disabled = true;
        var status = (document.getElementById('saveStatus') || {}).value || 'draft';
        post('review-quiz.php', { action: 'save', status: status, questions: JSON.stringify(questions) }).then(function (res) {
            if (res.ok) location.href = 'quiz-bank.php';
            else { btn.disabled = false; alert(res.error || 'The questions were not saved.'); }
        });
    }, true);
})();
