import type { Task } from '@/types';

const MONTHS_SHORT = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const MONTHS_LONG = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const WEEKDAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

/** Parse an ISO date (or datetime) into a local Date at midnight. */
const parseDate = (iso: string): Date => {
    const datePart = iso.slice(0, 10);
    const [year, month, day] = datePart.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const startOfToday = (): Date => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

/** Whole-day difference between an ISO date and today (negative = past). */
const dayDelta = (iso: string): number => {
    const ms = parseDate(iso).getTime() - startOfToday().getTime();

    return Math.round(ms / 86_400_000);
};

export type DateBucket = 'overdue' | 'today' | 'upcoming' | 'none';

export const bucketFor = (task: Task): DateBucket => {
    if (!task.due_date) {
        return 'none';
    }

    const delta = dayDelta(task.due_date);

    if (delta < 0) {
        return 'overdue';
    }

    if (delta === 0) {
        return 'today';
    }

    return 'upcoming';
};

/** Short mono-friendly date, e.g. "8 Jul". */
export const shortDate = (iso: string): string => {
    const date = parseDate(iso);

    return `${date.getDate()} ${MONTHS_SHORT[date.getMonth()]}`;
};

/** Human due label: "today", "tomorrow", "yesterday", weekday, or date. */
export const dueLabel = (iso: string): string => {
    const delta = dayDelta(iso);

    if (delta === 0) {
        return 'today';
    }

    if (delta === 1) {
        return 'tomorrow';
    }

    if (delta === -1) {
        return 'yesterday';
    }

    if (delta < -1) {
        return `${Math.abs(delta)} days overdue`;
    }

    if (delta > 1 && delta < 7) {
        return WEEKDAYS[parseDate(iso).getDay()];
    }

    return shortDate(iso);
};

/** Full display date for the Vandaag hero, e.g. "Wednesday 8 July". */
export const heroDate = (iso: string): string => {
    const date = parseDate(iso);

    return `${WEEKDAYS[date.getDay()]} ${date.getDate()} ${MONTHS_LONG[date.getMonth()]}`;
};

export const todayIso = (): string => {
    const date = startOfToday();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
};
