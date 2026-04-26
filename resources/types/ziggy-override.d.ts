// Override Ziggy types to accept arrays & nested objects in query params
import 'ziggy-js';

declare module 'ziggy-js' {
    export type QueryOptions = Record<string, string | number | boolean | string[] | null | undefined | Record<string, string | number | boolean>>;
}
