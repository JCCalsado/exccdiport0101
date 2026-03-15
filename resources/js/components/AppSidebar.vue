<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Bell,
    CheckCircle2,
    CreditCard,
    GraduationCap,
    History,
    LayoutGrid,
    Receipt,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Safely resolve a Ziggy route name.
 * Returns the resolved URL string, or a fallback '#' when the route has not
 * yet been registered in the compiled Ziggy manifest (e.g. after a rename
 * before `php artisan ziggy:generate` has been re-run).
 *
 * This prevents the sidebar from hard-crashing for ALL roles when a single
 * role-gated route is missing from the manifest.
 */
const safeRoute = (name: string, params?: any): string => {
    try {
        return route(name, params);
    } catch {
        if (import.meta.env.DEV) {
            console.warn(`[AppSidebar] Ziggy route not found: "${name}". Run: php artisan ziggy:generate`);
        }
        return '#';
    }
};

// ---------------------------------------------------------------------------
// Auth / role
// ---------------------------------------------------------------------------

const page     = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');

// ---------------------------------------------------------------------------
// Nav items — ALL route() calls live inside the computed so they are resolved
// lazily after Ziggy is fully initialised, and wrapped via safeRoute so a
// missing route name never throws.
// ---------------------------------------------------------------------------

const mainNavItems = computed<NavItem[]>(() => {
    const role = userRole.value;

    const items: NavItem[] = [
        // ── Student ───────────────────────────────────────────────────────────
        {
            title: 'Student Dashboard',
            href:  safeRoute('student.dashboard'),
            icon:  GraduationCap,
            roles: ['student'],
        },
        {
            title: 'My Account',
            href:  safeRoute('student.account'),
            icon:  CreditCard,
            roles: ['student'],
        },
        {
            title: 'Transaction History',
            href:  safeRoute('transactions.index'),
            icon:  History,
            roles: ['student'],
        },

        // ── Admin ─────────────────────────────────────────────────────────────
        {
            title: 'Admin Dashboard',
            href:  safeRoute('admin.dashboard'),
            icon:  LayoutGrid,
            roles: ['admin'],
        },
        {
            title: 'Admin Users',
            href:  safeRoute('users.index'),
            icon:  Users,
            roles: ['admin'],
        },
        {
            title: 'Student Management',
            href:  safeRoute('students.index'),
            icon:  GraduationCap,
            roles: ['admin'],
        },
        {
            title: 'Archives',
            href:  safeRoute('students.archive'),
            icon:  GraduationCap,
            roles: ['admin'],
        },
        {
            title: 'Notifications',
            href:  '/admin/notifications',
            icon:  Bell,
            roles: ['admin'],
        },

        // ── Accounting ────────────────────────────────────────────────────────
        {
            title: 'Accounting Dashboard',
            href:  safeRoute('accounting.dashboard'),
            icon:  Banknote,
            roles: ['accounting'],
        },

        // ── Admin + Accounting ────────────────────────────────────────────────
        {
            title: 'Student Fee Management',
            href:  safeRoute('student-fees.index'),
            icon:  Receipt,
            roles: ['accounting', 'admin'],
        },
        {
            title: 'Payment Approvals',
            href:  safeRoute('approvals.index'),
            icon:  CheckCircle2,
            roles: ['accounting', 'admin'],
        },
    ];

    // Filter to only the items that belong to the current user's role.
    return items.filter((item) => {
        if (!item.roles) return true;
        return item.roles.includes(role);
    });
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>