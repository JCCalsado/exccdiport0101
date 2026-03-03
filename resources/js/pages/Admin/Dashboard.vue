<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Users, FileText, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  stats?: {
    totalAdmins: number
    activeAdmins: number
    inactiveAdmins: number
    pendingApprovals: number
    totalUsers: number
    totalStudents: number
    recentNotifications: Array<{
      id: number
      title: string
      targetRole: string
      startDate: string
      endDate: string
      createdAt: string
    }>
    systemHealth: {
      status: string
    }
  }
}

const props = withDefaults(defineProps<Props>(), {
  stats: () => ({
    totalAdmins: 0,
    activeAdmins: 0,
    inactiveAdmins: 0,
    pendingApprovals: 0,
    totalUsers: 0,
    totalStudents: 0,
    recentNotifications: [],
    systemHealth: {
      status: 'operational',
    },
  }),
})

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Dashboard', href: route('admin.dashboard') },
]

const adminStats = computed(() => [
  {
    title: 'Total Admins',
    value: props.stats?.totalAdmins || 0,
    description: `${props.stats?.activeAdmins || 0} active`,
    icon: Users,
    color: 'blue',
  },
  {
    title: 'Total Users',
    value: props.stats?.totalUsers || 0,
    description: `${props.stats?.totalStudents || 0} students`,
    icon: Users,
    color: 'purple',
  },
  {
    title: 'Pending Approvals',
    value: props.stats?.pendingApprovals || 0,
    description: 'Awaiting action',
    icon: AlertCircle,
    color: 'orange',
  },
  {
    title: 'System Status',
    value: 'Operational',
    description: 'All systems healthy',
    icon: CheckCircle2,
    color: 'green',
  },
])

const getColorClass = (color: string) => {
  const colors: Record<string, string> = {
    blue: 'text-blue-500',
    purple: 'text-purple-500',
    orange: 'text-orange-500',
    green: 'text-green-500',
  }
  return colors[color] || 'text-gray-500'
}
</script>

<template>
  <Head title="Admin Dashboard" />

  <AppLayout>
    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Welcome Header -->
      <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
        <p class="text-gray-600">Welcome to your administration center</p>
      </div>

      <!-- Quick Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div v-for="(stat, index) in adminStats" :key="index">
          <Card>
            <CardHeader class="pb-3">
              <CardTitle class="text-sm font-medium text-gray-700">{{ stat.title }}</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="flex items-center justify-between">
                <div class="text-3xl font-bold text-gray-900">{{ stat.value }}</div>
                <component :is="stat.icon" :class="['w-8 h-8', getColorClass(stat.color)]" />
              </div>
              <p class="text-xs text-gray-500 mt-2">{{ stat.description }}</p>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Quick Actions -->
        <Card class="lg:col-span-1">
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>Common administrative tasks</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <Link :href="route('users.create')" as="button" class="w-full">
              <Button variant="outline" class="w-full justify-start">
                <Users class="w-4 h-4 mr-2" />
                Add Admin User
              </Button>
            </Link>
            <Link :href="route('notifications.index')" as="button" class="w-full">
              <Button variant="outline" class="w-full justify-start">
                <FileText class="w-4 h-4 mr-2" />
                Manage Notifications
              </Button>
            </Link>
            <Link :href="route('users.index')" as="button" class="w-full">
              <Button variant="outline" class="w-full justify-start">
                <Users class="w-4 h-4 mr-2" />
                View All Admins
              </Button>
            </Link>
            <Link :href="route('student-fees.index')" as="button" class="w-full">
              <Button variant="outline" class="w-full justify-start">
                <Users class="w-4 h-4 mr-2" />
                View Students
              </Button>
            </Link>
            <!-- Fee Management link removed (Fee Management disabled) -->
          </CardContent>
        </Card>

        <!-- System Status & Admin Information -->
        <div class="lg:col-span-2 space-y-6">
          <!-- System Status -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <CheckCircle2 class="w-5 h-5 text-green-500" />
                System Status
              </CardTitle>
              <CardDescription>Real-time system health</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="border rounded-lg p-4 bg-green-50 border-green-200">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="font-semibold text-green-900">All Systems Operational</h4>
                    <p class="text-sm text-green-700 mt-1">All services are running normally</p>
                  </div>
                  <CheckCircle2 class="w-8 h-8 text-green-500" />
                </div>
              </div>

              <div class="grid grid-cols-3 gap-2">
                <div class="p-3 bg-gray-50 rounded border border-gray-200">
                  <p class="text-xs text-gray-600 font-medium">Database</p>
                  <p class="text-sm font-semibold text-green-600 mt-1">✓ Online</p>
                </div>
                <div class="p-3 bg-gray-50 rounded border border-gray-200">
                  <p class="text-xs text-gray-600 font-medium">API</p>
                  <p class="text-sm font-semibold text-green-600 mt-1">✓ Online</p>
                </div>
                <div class="p-3 bg-gray-50 rounded border border-gray-200">
                  <p class="text-xs text-gray-600 font-medium">Auth</p>
                  <p class="text-sm font-semibold text-green-600 mt-1">✓ Online</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Admin Roles Distribution -->
          <Card>
            <CardHeader>
              <CardTitle>Admin Roles</CardTitle>
              <CardDescription>Current admin distribution</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="space-y-2">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-700">Active Admins</span>
                  <span class="font-semibold text-gray-900">{{ props.stats?.activeAdmins || 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-green-500 h-2 rounded-full" :style="{ width: props.stats?.activeAdmins ? (props.stats.activeAdmins / Math.max(props.stats.totalAdmins, 1)) * 100 + '%' : '0%' }"></div>
                </div>
              </div>

              <div class="space-y-2">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-700">Inactive Admins</span>
                  <span class="font-semibold text-gray-900">{{ props.stats?.inactiveAdmins || 0 }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-red-500 h-2 rounded-full" :style="{ width: props.stats?.inactiveAdmins ? (props.stats.inactiveAdmins / Math.max(props.stats.totalAdmins, 1)) * 100 + '%' : '0%' }"></div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Recent Notifications -->
      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle>Recent Notifications</CardTitle>
            <CardDescription>Latest notifications sent to users</CardDescription>
          </div>
          <Link :href="route('notifications.index')">
            <Button variant="outline" size="sm">View All</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <div v-if="!props.stats?.recentNotifications?.length" class="text-center py-8">
            <FileText class="w-12 h-12 mx-auto mb-4 text-gray-300" />
            <p class="text-gray-500">No notifications yet</p>
            <p class="text-sm text-gray-400 mt-1">Create one to get started</p>
          </div>
          <div v-else class="space-y-3">
            <div v-for="notification in props.stats?.recentNotifications?.slice(0, 5)" :key="notification.id" class="border rounded-lg p-4 hover:bg-gray-50 transition">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-900">{{ notification.title }}</h4>
                  <p class="text-sm text-gray-600 mt-1">To: <span class="capitalize font-medium">{{ notification.targetRole }}</span></p>
                </div>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">{{ new Date(notification.createdAt).toLocaleDateString() }}</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>