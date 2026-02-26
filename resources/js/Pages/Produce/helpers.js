/**
 * Pure utility functions shared across Produce page modules.
 */

/** Canonical timeline date for a batch line (journal_date → batch created_at → line created_at) */
export const batchDate = (line) =>
    line.batch?.journal?.journal_date ?? line.batch?.created_at ?? line.created_at ?? '1970-01-01';

/** Sort comparator: batch lines by timeline date, oldest first */
export const sortByTimeline = (a, b) =>
    new Date(batchDate(a)).getTime() - new Date(batchDate(b)).getTime();

/** Format a date string for display */
export const fmtDate = (d) =>
    new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

/** Format a number as currency (2 decimal places) */
export const currency = (n) => parseFloat(n ?? 0).toFixed(2);
