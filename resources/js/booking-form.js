import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

document.addEventListener('livewire:initialized', () => {
    initBookingPicker();
});

// Re-init after Livewire navigates or re-renders the step.
document.addEventListener('livewire:navigated', () => {
    initBookingPicker();
});

function initBookingPicker() {
    const el = document.getElementById('date-range-picker');
    if (!el || el._flatpickr) return;

    const availabilityUrl = el.dataset.availabilityUrl;

    fetch(availabilityUrl)
        .then((res) => res.json())
        .then(({ unavailable, blocked }) => {
            const disableRanges = [
                ...unavailable.map((b) => ({ from: b.start_date, to: b.end_date })),
                ...blocked.map((b) => ({ from: b.start_date, to: b.end_date })),
            ];

            flatpickr(el, {
                mode: 'range',
                minDate: 'today',
                dateFormat: 'Y-m-d',
                disable: disableRanges,
                onChange(selectedDates) {
                    if (selectedDates.length === 2) {
                        const fmt = (d) => d.toISOString().slice(0, 10);
                        Livewire.dispatch('dates-selected', {
                            start: fmt(selectedDates[0]),
                            end: fmt(selectedDates[1]),
                        });
                    }
                },
            });
        });
}
