<script setup lang="ts">
// ─── EnrolledSubjectsSkeleton ─────────────────────────────────────────────────
// Reusable skeleton placeholder for the Enrolled Subjects panels.
//
// variant="compact"  → 4-column layout  (AccountOverview: Code | Subject | Units | Status)
// variant="detailed" → 7-column layout  (Transactions:    Status | Code | Subject | Units | Unit Cost | Lab Fee | Total)
// rows               → number of shimmer rows to render (default: 5)
// ─────────────────────────────────────────────────────────────────────────────

withDefaults(
    defineProps<{
        variant?: 'compact' | 'detailed';
        rows?: number;
    }>(),
    {
        variant: 'compact',
        rows: 5,
    },
);
</script>

<template>
    <!-- Outer card — mirrors the accordion panel wrapper in both pages -->
    <div class="mb-3 animate-pulse overflow-hidden rounded-lg border border-gray-200 bg-white">

        <!-- ── Accordion Header Skeleton ── -->
        <div class="flex items-center justify-between px-4 py-3">
            <!-- Left: label + school-year chip + course pill -->
            <div class="flex items-center gap-2">
                <div class="h-4 w-36 rounded bg-gray-200" />
                <div class="h-3.5 w-20 rounded bg-gray-200" />
                <div class="h-5 w-16 rounded-full bg-gray-200" />
            </div>
            <!-- Right: count text + chevron icon -->
            <div class="flex items-center gap-3">
                <div class="h-3 w-28 rounded bg-gray-200" />
                <div class="h-4 w-4 rounded bg-gray-200" />
            </div>
        </div>

        <!-- ── Legend bar — detailed variant only ── -->
        <div
            v-if="variant === 'detailed'"
            class="flex flex-wrap items-center gap-4 border-t border-gray-100 bg-white px-6 py-2.5"
        >
            <div class="h-3 w-32 rounded bg-gray-200" />
            <div class="h-3 w-28 rounded bg-gray-200" />
            <div class="h-3 w-36 rounded bg-gray-200" />
        </div>

        <!-- ── Table ── -->
        <div class="overflow-x-auto border-t border-gray-100">
            <table class="min-w-full text-sm">

                <!-- thead -->
                <thead
                    :class="
                        variant === 'detailed'
                            ? 'border-b border-gray-200 bg-gray-100'
                            : 'border-t border-gray-200 bg-gray-50'
                    "
                >
                    <!-- compact: Code | Subject | Units | Status -->
                    <tr v-if="variant === 'compact'">
                        <th class="px-4 py-2.5 text-left">
                            <div class="h-3 w-10 rounded bg-gray-300" />
                        </th>
                        <th class="px-4 py-2.5 text-left">
                            <div class="h-3 w-20 rounded bg-gray-300" />
                        </th>
                        <th class="px-4 py-2.5 text-center">
                            <div class="mx-auto h-3 w-10 rounded bg-gray-300" />
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <div class="ml-auto h-3 w-12 rounded bg-gray-300" />
                        </th>
                    </tr>

                    <!-- detailed: Status | Code | Subject Name | Units | Unit Cost | Lab Fee | Total -->
                    <tr v-else>
                        <th class="px-5 py-2.5 text-left">
                            <div class="h-3 w-12 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-left">
                            <div class="h-3 w-10 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-left">
                            <div class="h-3 w-28 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-center">
                            <div class="mx-auto h-3 w-10 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-right">
                            <div class="ml-auto h-3 w-16 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-right">
                            <div class="ml-auto h-3 w-14 rounded bg-gray-300" />
                        </th>
                        <th class="px-5 py-2.5 text-right">
                            <div class="ml-auto h-3 w-12 rounded bg-gray-300" />
                        </th>
                    </tr>
                </thead>

                <!-- tbody — N shimmer rows -->
                <tbody class="divide-y divide-gray-100">

                    <!-- compact rows -->
                    <template v-if="variant === 'compact'">
                        <tr v-for="i in rows" :key="i">
                            <td class="px-4 py-2.5">
                                <div class="h-3 w-16 rounded bg-gray-200" />
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="h-3 rounded bg-gray-200" :style="`width: ${55 + (i % 3) * 15}%`" />
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="mx-auto h-3 w-6 rounded bg-gray-200" />
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="ml-auto h-5 w-20 rounded-full bg-gray-200" />
                            </td>
                        </tr>
                    </template>

                    <!-- detailed rows -->
                    <template v-else>
                        <tr v-for="i in rows" :key="i">
                            <td class="px-5 py-3 text-center">
                                <div class="mx-auto h-6 w-6 rounded-full bg-gray-200" />
                            </td>
                            <td class="px-5 py-3">
                                <div class="h-5 w-16 rounded bg-gray-200" />
                            </td>
                            <td class="px-5 py-3">
                                <div class="h-3 rounded bg-gray-200" :style="`width: ${50 + (i % 4) * 12}%`" />
                            </td>
                            <td class="px-5 py-3 text-center">
                                <div class="mx-auto h-5 w-14 rounded-full bg-gray-200" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="ml-auto mb-1 h-2.5 w-24 rounded bg-gray-200" />
                                <div class="ml-auto h-3 w-16 rounded bg-gray-200" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="ml-auto h-3 w-14 rounded bg-gray-200" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="ml-auto h-3 w-16 rounded bg-gray-200" />
                            </td>
                        </tr>
                    </template>

                </tbody>

                <!-- tfoot -->
                <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                    <!-- compact footer -->
                    <tr v-if="variant === 'compact'">
                        <td colspan="2" class="px-4 py-2">
                            <div class="h-3 w-10 rounded bg-gray-300" />
                        </td>
                        <td class="px-4 py-2 text-center">
                            <div class="mx-auto h-3 w-6 rounded bg-gray-300" />
                        </td>
                        <td />
                    </tr>

                    <!-- detailed footer -->
                    <tr v-else>
                        <td colspan="3" class="px-5 py-3">
                            <div class="h-3 w-52 rounded bg-gray-300" />
                        </td>
                        <td class="px-5 py-3 text-center">
                            <div class="mx-auto h-3 w-4 rounded bg-gray-300" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="ml-auto h-3 w-16 rounded bg-gray-300" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="ml-auto h-3 w-14 rounded bg-gray-300" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="ml-auto h-3 w-16 rounded bg-gray-300" />
                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>

        <!-- Footnote bar — detailed variant only -->
        <div
            v-if="variant === 'detailed'"
            class="border-t border-gray-100 bg-white px-5 py-2.5"
        >
            <div class="h-3 w-96 max-w-full rounded bg-gray-200" />
        </div>

    </div>
</template>