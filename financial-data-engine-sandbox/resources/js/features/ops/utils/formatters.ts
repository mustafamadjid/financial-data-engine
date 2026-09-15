export function humanize(value: unknown, fallback = 'Unknown'): string {
    if (typeof value !== 'string' || value.trim() === '') return fallback;

    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .toLowerCase()
        .replace(/(^|\s)\S/g, (character) => character.toUpperCase());
}

export function formatDateTime(value: unknown, fallback = 'Not recorded'): string {
    if (typeof value !== 'string' || value.trim() === '') return fallback;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export function displayValue(value: unknown, fallback = 'Not recorded'): string {
    if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) return fallback;

    return String(value);
}
