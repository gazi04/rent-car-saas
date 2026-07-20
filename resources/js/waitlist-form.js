import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

document.addEventListener('livewire:initialized', initWaitlistPickers);
document.addEventListener('livewire:navigated', initWaitlistPickers);

function fmt(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function initWaitlistPickers() {
    const startEl = document.getElementById('waitlist-start-picker');
    const endEl = document.getElementById('waitlist-end-picker');
    if (!startEl || !endEl || startEl._flatpickr) return;

    const endPicker = flatpickr(endEl, {
        dateFormat: 'd/m/Y',
        minDate: 'today',
        onChange(selectedDates) {
            if (selectedDates[0]) {
                Livewire.dispatch('waitlist-end-selected', { date: fmt(selectedDates[0]) });
            }
        },
    });

    flatpickr(startEl, {
        dateFormat: 'd/m/Y',
        minDate: 'today',
        onChange(selectedDates) {
            if (selectedDates[0]) {
                Livewire.dispatch('waitlist-start-selected', { date: fmt(selectedDates[0]) });
                endPicker.set('minDate', selectedDates[0]);
            }
        },
    });
}
