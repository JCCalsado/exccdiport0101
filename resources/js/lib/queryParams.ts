export type QueryOptions = Record<string, string | number | boolean | null | undefined>;

export function queryParams(options?: { query?: QueryOptions; mergeQuery?: QueryOptions }): string {
    if (!options) return '';
    const params = new URLSearchParams();

    // prefer mergeQuery if set
    const q = options.mergeQuery ?? options.query;

    if (q) {
        Object.entries(q).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                params.set(key, String(value));
            }
        });
    }

    const queryString = params.toString();
    return queryString ? `?${queryString}` : '';
}
