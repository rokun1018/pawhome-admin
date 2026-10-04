// ============================================
// BOARDING: show "another pet" fields and the price estimate.
// The nightly price comes from the admin panel (Settings > Organisation).
// ============================================
(function () {
    const form = document.getElementById('boardingForm');
    if (!form) return;
    const rate = parseFloat(form.dataset.rate) || 0;
    const currency = form.dataset.currency || '';
    const petSelect = document.getElementById('petSelect');
    const otherPet = document.getElementById('otherPet');
    const start = document.getElementById('startDate');
    const end = document.getElementById('endDate');

    function money(n) {
        return currency + n.toLocaleString(undefined, { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    }

    function toggleOtherPet() {
        const isOther = petSelect.value === 'other';
        otherPet.hidden = !isOther;
        otherPet.querySelector('#petName').required = isOther;
        otherPet.querySelector('select').required = isOther;
    }

    function calculatePrice() {
        if (start.value) end.min = new Date(new Date(start.value).getTime() + 86400000).toISOString().slice(0, 10);
        if (!start.value || !end.value) return;
        const nights = Math.round((new Date(end.value) - new Date(start.value)) / 86400000);
        if (nights < 1) {
            document.getElementById('duration').textContent = 'End must be after start';
            document.getElementById('totalPrice').textContent = '–';
            return;
        }
        document.getElementById('duration').textContent = `${nights} Night${nights !== 1 ? 's' : ''}`;
        document.getElementById('totalPrice').textContent = rate > 0 ? money(rate * nights) : 'Confirmed by our team';
    }

    petSelect.addEventListener('change', toggleOtherPet);
    start.addEventListener('change', calculatePrice);
    end.addEventListener('change', calculatePrice);
    form.addEventListener('submit', () => { form.querySelector('.btn-submit').disabled = true; });
    toggleOtherPet();
    calculatePrice();
})();
