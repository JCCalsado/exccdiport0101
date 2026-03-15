/**
 * Generate initials from a user's display name.
 *
 * The system formats names as "LAST, First MI." (e.g. "DELA CRUZ, Juan P.").
 * We parse this format to extract meaningful initials (First + Last).
 *
 * Falls back to splitting on spaces for non-comma formats.
 */
export function getInitials(fullName?: string): string {
    if (!fullName) return '?';

    const trimmed = fullName.trim();

    // Handle "LAST, First MI." format produced by User::getNameAttribute()
    if (trimmed.includes(',')) {
        const [lastPart, firstPart] = trimmed.split(',').map((s) => s.trim());
        const firstInitial = firstPart?.charAt(0)?.toUpperCase() ?? '';
        const lastInitial = lastPart?.charAt(0)?.toUpperCase() ?? '';
        return `${firstInitial}${lastInitial}`;
    }

    // Fallback: standard "First Last" format
    const names = trimmed.split(/\s+/);
    if (names.length === 1) return names[0].charAt(0).toUpperCase();
    return `${names[0].charAt(0)}${names[names.length - 1].charAt(0)}`.toUpperCase();
}

export function useInitials() {
    return { getInitials };
}