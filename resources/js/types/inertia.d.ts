import '@inertiajs/core';
import { StudentUser } from './user';

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: StudentUser;
        };
    }
}
