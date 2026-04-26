import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

// ✅ Re-export everything from user.d.ts
export type { StudentUser, User } from './user';

export interface Auth {
    user: User | StudentUser;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    roles?: string[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export type BreadcrumbItemType = BreadcrumbItem;
