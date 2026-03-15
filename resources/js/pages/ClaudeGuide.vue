<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Claude Usage Guide', href: route('claude.guide') },
];

// ── Active tip tracker ────────────────────────────────────────────────────────
const activeTip = ref<number | null>(null);

const toggleTip = (id: number) => {
    activeTip.value = activeTip.value === id ? null : id;
};

// ── Tip data ──────────────────────────────────────────────────────────────────
const habits = [
    {
        id: 1,
        icon: 'edit',
        category: 'Edit',
        color: 'amber',
        title: 'Edit your prompt, don\'t send a follow-up',
        summary: 'Regenerate instead of stacking messages.',
        detail: 'When Claude misses the mark, click the edit icon on your original message, fix the prompt, and regenerate. The old exchange gets replaced instead of added to the conversation history. Over 10 rounds, this habit alone cuts token use by 80–90%.',
        rule: 'Fix the prompt. Don\'t stack the chat.',
    },
    {
        id: 2,
        icon: 'chat',
        category: 'Fresh Chat',
        color: 'coral',
        title: 'Start fresh every 15–20 messages',
        summary: 'Long chats re-read everything each turn.',
        detail: 'Claude re-reads the entire conversation history every single turn. Your 1st message costs ~200 tokens. By message 30, a simple question can cost 50,000+ tokens. Ask for a summary, copy it, open a new chat, paste it in.',
        rule: 'Long chats are expensive chats.',
    },
    {
        id: 3,
        icon: 'batch',
        category: 'Batching',
        color: 'teal',
        title: 'Combine multiple questions into one',
        summary: 'Three questions, one message, always.',
        detail: 'Instead of sending three separate messages, batch them: "Summarize this article, list the main points as bullets, then suggest a headline." One turn instead of three means one context load — and the answers are often better because Claude sees the full picture.',
        rule: 'Three questions. One message. Always.',
    },
    {
        id: 4,
        icon: 'folder',
        category: 'Projects',
        color: 'blue',
        title: 'Upload recurring files to Projects',
        summary: 'Upload once, stop paying every time.',
        detail: 'If you\'re uploading the same PDF, brief, or guide in multiple chats, Claude is re-counting those tokens every single time. Projects (in the sidebar) cache your files so they don\'t re-cost on each conversation. Huge saver for anyone who works with long documents regularly.',
        rule: 'Upload once. Stop paying every time.',
    },
    {
        id: 5,
        icon: 'memory',
        category: 'Memory',
        color: 'purple',
        title: 'Set up Memory & custom instructions',
        summary: 'Set it once. It runs forever.',
        detail: 'Every conversation you start without context burns 3–5 setup messages just re-explaining who you are and how you work. Go to Settings → Memory and User Preferences and store it once: your role, your tone, your preferences. Claude will carry it automatically into every chat.',
        rule: 'Set it once. It runs forever.',
    },
    {
        id: 6,
        icon: 'toggle',
        category: 'Toggle',
        color: 'gray',
        title: 'Turn off features you\'re not using',
        summary: 'Every tool adds tokens, even unused.',
        detail: 'Web search, Research mode, and connectors all add tokens to every response, even when you don\'t need them. When you\'re working with your own content or just writing, toggle off "Search and tools." Extended Thinking is the same: leave it off by default and switch it on only when your first attempt wasn\'t good enough.',
        rule: 'If you didn\'t turn it on, turn it off.',
    },
    {
        id: 7,
        icon: 'haiku',
        category: 'Right model',
        color: 'green',
        title: 'Use Haiku for simple tasks all day',
        summary: 'Haiku is free for most everyday tasks.',
        detail: 'Haiku handles grammar checks, quick answers, brainstorming, formatting, and translations at a fraction of the cost of Sonnet or Opus. Using the right model is the single highest-impact decision you can make. Haiku all day for simple work frees up 50–70% of your budget for the tasks that actually need the bigger models.',
        rule: '"Haiku for drafts. Sonnet for real work. Opus for the hard stuff."',
    },
    {
        id: 8,
        icon: 'clock',
        category: 'Spread',
        color: 'coral',
        title: 'Spread your work across the day',
        summary: 'Don\'t sprint. Pace yourself.',
        detail: 'Claude runs on a rolling 5-hour window that resets continuously. If you burn through your limit in one morning session, you\'re done until the window rolls over. Split your work into 2–3 sessions per day instead of one burst — and you can effectively get 150–200+ messages a day on a Pro plan instead of 45.',
        rule: 'Don\'t sprint. Pace yourself.',
    },
];

// ── Model comparison data ─────────────────────────────────────────────────────
const models = [
    {
        name: 'Haiku',
        cost: 'Very Low',
        costLevel: 1,
        color: 'green',
        tasks: ['Quick answers', 'Brainstorms', 'Formatting', 'Grammar'],
    },
    {
        name: 'Sonnet',
        cost: 'Medium',
        costLevel: 2,
        color: 'amber',
        tasks: ['Content writing', 'Analysis', 'Coding', 'Drafts'],
        recommended: true,
    },
    {
        name: 'Opus',
        cost: 'High',
        costLevel: 3,
        color: 'coral',
        tasks: ['Deep research', 'Hard logic', 'Long doc review'],
    },
];

// ── Icon paths ────────────────────────────────────────────────────────────────
const iconPaths: Record<string, string> = {
    edit:   'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
    chat:   'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
    batch:  'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    folder: 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z',
    memory: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z',
    toggle: 'M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3',
    haiku:  'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM8 12h8M12 8v8',
    clock:  'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 6v6l4 2',
};

const colorMap: Record<string, { bg: string; text: string; border: string; dot: string }> = {
    amber:  { bg: 'bg-amber-50 dark:bg-amber-950/30',  text: 'text-amber-700 dark:text-amber-300',  border: 'border-amber-200 dark:border-amber-800',  dot: 'bg-amber-400' },
    coral:  { bg: 'bg-orange-50 dark:bg-orange-950/30', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-200 dark:border-orange-800', dot: 'bg-orange-400' },
    teal:   { bg: 'bg-teal-50 dark:bg-teal-950/30',    text: 'text-teal-700 dark:text-teal-300',    border: 'border-teal-200 dark:border-teal-800',    dot: 'bg-teal-400' },
    blue:   { bg: 'bg-blue-50 dark:bg-blue-950/30',    text: 'text-blue-700 dark:text-blue-300',    border: 'border-blue-200 dark:border-blue-800',    dot: 'bg-blue-400' },
    purple: { bg: 'bg-violet-50 dark:bg-violet-950/30', text: 'text-violet-700 dark:text-violet-300', border: 'border-violet-200 dark:border-violet-800', dot: 'bg-violet-400' },
    gray:   { bg: 'bg-zinc-50 dark:bg-zinc-900/40',    text: 'text-zinc-600 dark:text-zinc-400',    border: 'border-zinc-200 dark:border-zinc-700',    dot: 'bg-zinc-400' },
    green:  { bg: 'bg-green-50 dark:bg-green-950/30',  text: 'text-green-700 dark:text-green-300',  border: 'border-green-200 dark:border-green-800',  dot: 'bg-green-400' },
};

const modelColorMap: Record<string, { badge: string; bar: string }> = {
    green:  { badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',  bar: 'bg-green-400' },
    amber:  { badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',  bar: 'bg-amber-400' },
    coral:  { badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300', bar: 'bg-orange-400' },
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Claude Usage Guide" />

        <div class="mx-auto max-w-4xl space-y-10 px-4 py-8">

            <!-- Header -->
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    Claude usage guide
                </h1>
                <p class="text-sm text-muted-foreground">
                    Claude counts tokens, not messages. Some conversations eat through your limit 10× faster than others.
                    These 8 habits keep you in control.
                </p>
            </div>

            <!-- Model comparison panel -->
            <div class="rounded-xl border bg-card p-6">
                <p class="mb-1 text-xs font-medium uppercase tracking-widest text-muted-foreground">
                    Highest-impact decision
                </p>
                <h2 class="mb-5 text-base font-medium text-foreground">Use the right model for the task</h2>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div
                        v-for="m in models"
                        :key="m.name"
                        class="relative rounded-lg border p-4 transition-all"
                        :class="[
                            m.recommended
                                ? 'border-2 border-blue-400 dark:border-blue-500 bg-blue-50/40 dark:bg-blue-950/20'
                                : 'border bg-background'
                        ]"
                    >
                        <!-- Recommended badge -->
                        <span
                            v-if="m.recommended"
                            class="absolute -top-2.5 left-1/2 -translate-x-1/2 rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/60 dark:text-blue-300"
                        >
                            Most used
                        </span>

                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-foreground">{{ m.name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="modelColorMap[m.color].badge"
                            >
                                {{ m.cost }}
                            </span>
                        </div>

                        <!-- Cost bar -->
                        <div class="mb-3 flex gap-1">
                            <span
                                v-for="i in 3"
                                :key="i"
                                class="h-1 flex-1 rounded-full"
                                :class="i <= m.costLevel ? modelColorMap[m.color].bar : 'bg-muted'"
                            />
                        </div>

                        <ul class="space-y-1">
                            <li
                                v-for="task in m.tasks"
                                :key="task"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span class="h-1 w-1 rounded-full bg-muted-foreground/40" />
                                {{ task }}
                            </li>
                        </ul>
                    </div>
                </div>

                <p class="mt-4 text-center text-xs italic text-muted-foreground">
                    "Haiku for drafts. Sonnet for real work. Opus for the hard stuff."
                </p>
            </div>

            <!-- Tips grid (8 expandable cards) -->
            <div>
                <h2 class="mb-4 text-base font-medium text-foreground">8 habits to stay in control</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="tip in habits"
                        :key="tip.id"
                        type="button"
                        class="group w-full rounded-xl border text-left transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :class="[
                            colorMap[tip.color].border,
                            activeTip === tip.id
                                ? colorMap[tip.color].bg
                                : 'bg-card hover:bg-muted/40'
                        ]"
                        @click="toggleTip(tip.id)"
                    >
                        <!-- Card header (always visible) -->
                        <div class="flex items-start gap-3 p-4">
                            <!-- Number badge -->
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                :class="[colorMap[tip.color].bg, colorMap[tip.color].text]"
                            >
                                {{ tip.id }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium uppercase tracking-wide" :class="colorMap[tip.color].text">
                                        {{ tip.category }}
                                    </span>
                                    <!-- Chevron -->
                                    <svg
                                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                                        :class="activeTip === tip.id ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    >
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </div>
                                <p class="mt-0.5 text-sm font-medium leading-snug text-foreground">
                                    {{ tip.title }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ tip.summary }}
                                </p>
                            </div>
                        </div>

                        <!-- Expanded detail -->
                        <div
                            v-if="activeTip === tip.id"
                            class="border-t px-4 pb-4 pt-3"
                            :class="colorMap[tip.color].border"
                        >
                            <p class="text-sm leading-relaxed text-muted-foreground">
                                {{ tip.detail }}
                            </p>
                            <p class="mt-3 text-xs font-medium" :class="colorMap[tip.color].text">
                                → {{ tip.rule }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Quick-reference summary -->
            <div class="rounded-xl border bg-muted/40 p-5">
                <h2 class="mb-3 text-sm font-medium text-foreground">Quick-reference checklist</h2>
                <div class="grid gap-x-8 gap-y-2 text-xs text-muted-foreground sm:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0" />
                        Quick tasks: Edit, don't add
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-orange-400 shrink-0" />
                        Deep sessions: Split chats
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-400 shrink-0" />
                        Recurring work: Use Projects
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-400 shrink-0" />
                        All tasks: Pick the right model
                    </div>
                </div>

                <p class="mt-4 border-t pt-4 text-center text-xs text-muted-foreground">
                    Claude counts tokens, not messages. Now you do too.
                </p>
            </div>

        </div>
    </AppLayout>
</template>