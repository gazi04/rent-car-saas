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
    const { defaultStart, defaultEnd } = el.dataset;
    const maxRentalDays = Number(el.dataset.maxRentalDays) || 0;

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
                defaultDate: defaultStart && defaultEnd ? [defaultStart, defaultEnd] : undefined,
                onChange(selectedDates, _dateStr, instance) {
                    // flatpickr 4 has no max-range option, so the duration cap is
                    // expressed by moving maxDate once the first date is picked.
                    // A static maxDate would instead forbid a 3-day rental six
                    // months out, which is a different (and wrong) rule.
                    if (maxRentalDays > 0) {
                        if (selectedDates.length === 1) {
                            const latest = new Date(selectedDates[0]);
                            latest.setDate(latest.getDate() + maxRentalDays);
                            instance.set('maxDate', latest);
                        } else if (selectedDates.length === 0) {
                            instance.set('maxDate', null);
                        }
                    }

                    if (selectedDates.length === 2) {
                        const fmt = (d) => {
                            const year = d.getFullYear();
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const day = String(d.getDate()).padStart(2, '0');
                            return `${year}-${month}-${day}`;
                        };
                        Livewire.dispatch('dates-selected', {
                            start: fmt(selectedDates[0]),
                            end: fmt(selectedDates[1]),
                        });

                        // Range complete — lift the bound so the next selection
                        // starts from a clean calendar.
                        if (maxRentalDays > 0) {
                            instance.set('maxDate', null);
                        }
                    }
                },
            });
        });
}
