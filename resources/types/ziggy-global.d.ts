// types/ziggy-global.d.ts
import { route as routeFn } from 'ziggy-js';

declare global {
    // Makes `route("login")` available in templates without TS errors
    const route: typeof routeFn;
}

// Also allow `route()` inside Vue templates if TS doesn't catch it yet:
declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof routeFn;
    }
}

export {};
