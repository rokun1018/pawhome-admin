// ============================================
// ELIGIBILITY QUIZ (one question at a time)
// Questions come from the admin Quiz Management; quiz.php prints them all,
// and this file shows the ones that fit the chosen kind of pet.
// ============================================
(function () {
    const form = document.getElementById('quizForm');
    if (!form) return;

    const first = form.querySelector('[data-first]');
    const bank = [...form.querySelectorAll('[data-step][data-pet-type]')];
    const bar = document.getElementById('quizProgressBar');
    const text = document.getElementById('quizProgressText');
    let steps = [first];
    let current = 0;

    function chosenType() {
        const picked = form.querySelector('input[name="pet_type"]:checked');
        return picked ? picked.value : 'any';
    }

    // Work out which bank questions belong to this quiz
    function buildSteps() {
        const types = QUIZ_TYPES[chosenType()] || QUIZ_TYPES.any;
        const relevant = bank.filter(q => types.includes(q.dataset.petType)).slice(0, QUIZ_MAX);
        bank.forEach(q => {
            const on = relevant.includes(q);
            q.querySelectorAll('input').forEach(i => { i.disabled = !on; });
        });
        steps = [first, ...relevant];
    }

    function render() {
        steps.forEach((s, i) => { s.hidden = i !== current; });
        bank.forEach(q => { if (!steps.includes(q)) q.hidden = true; });

        const total = steps.length;
        const pct = Math.round(((current + 1) / total) * 100);
        text.textContent = `Question ${current + 1} of ${total} (${pct}% complete)`;
        bar.innerHTML = steps.map((_, i) => `<div class="${i <= current ? 'completed' : ''}"></div>`).join('');

        const nextBtn = steps[current].querySelector('[data-next]');
        if (nextBtn) nextBtn.innerHTML = current === total - 1 ? 'Submit' : 'Next <b>→</b>';
        window.scrollTo(0, 0);
    }

    form.addEventListener('click', function (e) {
        if (e.target.closest('[data-back]')) {
            if (current > 0) { current--; render(); }
            return;
        }
        if (!e.target.closest('[data-next]')) return;

        const step = steps[current];
        if (!step.querySelector('input:checked')) {
            alert('Please select an answer before proceeding.');
            return;
        }
        if (current === 0) buildSteps();
        if (current < steps.length - 1) {
            current++;
            render();
        } else {
            e.target.closest('[data-next]').disabled = true;
            form.submit();
        }
    });

    buildSteps();
    render();
})();
