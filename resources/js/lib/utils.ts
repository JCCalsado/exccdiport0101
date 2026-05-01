import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { InertiaLinkProps } from '@inertiajs/vue3';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * Checks if the given nav item URL matches the current page URL.
 *
 * Uses prefix matching so that child routes (e.g. /student-fees/123)
 * correctly highlight their parent nav item (/student-fees).
 *
 * Exact match is used for root-level routes like /dashboard to avoid
 * false positives (e.g. /dashboard should not highlight / ).
 */
export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
): boolean {
    const href = toUrl(urlToCheck) ?? '';

    // Strip origin so full URLs like https://example.com/student-fees
    // become just /student-fees (same format as Inertia's page.url)
    let hrefPath: string;
    try {
        hrefPath = new URL(href).pathname;
    } catch {
        hrefPath = href;
    }

    // Strip query strings and trailing slashes
    hrefPath = hrefPath.split('?')[0].replace(/\/$/, '');
    const currentPath = currentUrl.split('?')[0].replace(/\/$/, '');

    // Exact match always wins
    if (hrefPath === currentPath) {
        return true;
    }

    // Prefix match: /student-fees is active when on /student-fees/123/edit
    // But don't match bare "/" against everything
    if (hrefPath.length > 1 && currentPath.startsWith(hrefPath + '/')) {
        return true;
    }

    return false;
}

