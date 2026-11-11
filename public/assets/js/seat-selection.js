(() => {
    const root = document.getElementById('seat-selector');
    if (!root) return;

    const showId = root.dataset.showId;
    const csrf = root.dataset.csrf;
    const selected = new Map();
    const countNode = document.getElementById('selected-seat-count');
    const labelsNode = document.getElementById('selected-seat-labels');
    const totalNode = document.getElementById('selected-seat-total');
    const continueLink = document.getElementById('continue-to-checkout');
    const messageNode = document.getElementById('seat-message');
    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

    function refreshSummary() {
        const seats = [...selected.values()];
        const total = seats.reduce((sum, seat) => sum + Number(seat.price), 0);
        countNode.textContent = String(seats.length);
        labelsNode.textContent = seats.length ? seats.map((seat) => seat.label).join(', ') : 'Choose available seats above.';
        totalNode.textContent = money.format(total);
        continueLink.setAttribute('aria-disabled', seats.length ? 'false' : 'true');
        continueLink.classList.toggle('seat-continue--disabled', seats.length === 0);
    }

    function showMessage(message, isError = false) {
        messageNode.textContent = message;
        messageNode.classList.toggle('form-message--error', isError);
    }

    async function post(url, seatId) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ show_id: Number(showId), seat_id: Number(seatId) }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Seat request failed.');
        return payload;
    }

    document.querySelectorAll('.seat[data-seat-id]').forEach((button) => {
        const seat = { id: button.dataset.seatId, label: button.dataset.seatLabel, price: button.dataset.price };
        if (button.dataset.held === '1') selected.set(seat.id, seat);

        button.addEventListener('click', async () => {
            if (button.disabled) return;
            button.disabled = true;
            showMessage('Updating your seat selection…');
            try {
                if (selected.has(seat.id)) {
                    await post('/api/release-seat.php', seat.id);
                    selected.delete(seat.id);
                    button.dataset.held = '0';
                    button.classList.remove('seat--selected');
                    button.classList.add('seat--available');
                    button.setAttribute('aria-pressed', 'false');
                    showMessage('Seat released.');
                } else {
                    const hold = await post('/api/hold-seat.php', seat.id);
                    seat.price = hold.price;
                    selected.set(seat.id, seat);
                    button.dataset.held = '1';
                    button.dataset.price = hold.price;
                    button.classList.remove('seat--available');
                    button.classList.add('seat--selected');
                    button.setAttribute('aria-pressed', 'true');
                    showMessage('Seat held for five minutes while you continue.');
                }
                refreshSummary();
            } catch (error) {
                showMessage(error.message || 'Seat request failed. Refresh and try again.', true);
            } finally {
                button.disabled = false;
            }
        });
    });

    continueLink.addEventListener('click', (event) => {
        if (selected.size === 0) {
            event.preventDefault();
            showMessage('Select at least one seat first.', true);
        }
    });

    refreshSummary();
})();
