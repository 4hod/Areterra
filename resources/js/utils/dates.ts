function pad(value: number) {
    return String(value).padStart(2, '0');
}

/** A date suitable for an HTML date input, using the device's local timezone. */
export function localDateInputValue(date = new Date()) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** A date and time suitable for an HTML datetime-local input. */
export function localDateTimeInputValue(date = new Date()) {
    return `${localDateInputValue(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
