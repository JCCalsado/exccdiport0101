<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Banknote, Bell, CheckCircle2, CreditCard, GraduationCap, History, LayoutGrid, Receipt, User, Users } from 'lucide-vue-next';
// REMOVED: BookOpen — was used for Subject Management (now disabled)
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || 'student');

const allNavItems: NavItem[] = [
    {
        title: 'Student Dashboard',
        href: route('student.dashboard'),
        icon: GraduationCap,
        roles: ['student'],
    },
    {
        title: 'My Account',
        href: route('student.account'),
        icon: CreditCard,
        roles: ['student'],
    },
    {
        title: 'Transaction History',
        href: route('transactions.index'),
        icon: History,
        roles: ['student'],
    },
    {
        title: 'Admin Dashboard',
        href: route('admin.dashboard'),
        icon: LayoutGrid,
        roles: ['admin'],
    },
    {
        title: 'Accounting Dashboard',
        href: route('accounting.dashboard'),
        icon: Banknote,
        roles: ['accounting'],
    },
    // REMOVED: Fee Management (route disabled)
    // REMOVED: Subject Management (route disabled)
    {
        title: 'Admin Users',
        href: '/admin/users',
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'Notifications',
        href: '/admin/notifications',
        icon: Bell,
        roles: ['admin'],
    },
    {
        title: 'User Management',
        href: route('users.index'),
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'Archives',
        href: route('students.archive'),
        icon: GraduationCap,
        roles: ['admin'],
    },
    {
        title: 'My Profile',
        href: route('my-profile'),
        icon: User,
        roles: ['student'],
    },
    {
        title: 'Student Fee Management',
        href: route('student-fees.index'),
        icon: Receipt,
        roles: ['accounting', 'admin'],
    },
    {
        title: 'Payment Approvals',
        href: route('approvals.index'),
        icon: CheckCircle2,
        roles: ['accounting', 'admin'],
    },
];

const mainNavItems = computed(() => {
    return allNavItems.filter((item) => {
        if (!item.roles) return true;
        return item.roles.includes(userRole.value);
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