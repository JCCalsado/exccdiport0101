<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const backgroundImage = '/images/BackgroundImage.jpg';
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="relative min-h-screen overflow-hidden text-white" style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background-image: url('/images/BackgroundImage.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">

        <!-- Overlay -->
        <div class="relative z-10 flex min-h-screen flex-col" style="background: linear-gradient(135deg, rgba(10,30,70,0.88) 0%, rgba(15,50,110,0.80) 50%, rgba(5,20,55,0.85) 100%);">

            <!-- Header -->
            <header class="flex w-full items-center justify-between px-8 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold text-white" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);">CC</div>
                    <span class="hidden text-sm font-semibold text-white/90 sm:block">CCDI Account Portal</span>
                </div>
                <nav class="flex items-center gap-3">
                    <Link v-if="page.props.auth.user" :href="route('dashboard')" class="rounded-lg px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white/10" style="border: 1px solid rgba(255,255,255,0.25);">Dashboard</Link>
                    <template v-else>
                        <Link :href="route('login')" class="rounded-lg px-4 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white">Log in</Link>
                        <Link :href="route('register')" class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition-all hover:opacity-90" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);">Register</Link>
                    </template>
                </nav>
            </header>

            <!-- Hero -->
            <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-medium text-white/80" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-400"></span>
                    AY 2026-2027 · CCDI Account Portal
                </div>

                <h1 class="mb-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight drop-shadow-lg md:text-5xl lg:text-6xl">
                    Computer Communication<br />Development Institute
                </h1>

                <p class="mb-10 max-w-xl text-base leading-relaxed text-white/70 md:text-lg">
                    CCDI envisions providing a service of leadership through excellent instructions that will produce empowered and world-class I.T. graduates.
                </p>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Link
                        :href="page.props.auth.user ? route('dashboard') : route('login')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-7 py-3.5 text-sm font-semibold text-white shadow-lg transition-all hover:scale-105 hover:opacity-90"
                        style="background: linear-gradient(135deg, #1d6fe6 0%, #1a56d4 100%);"
                    >
                        Get Started
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m0 0l-7-7m7 7l-7 7" /></svg>
                    </Link>
                    <a href="https://www.ccdisorsogon.edu.ph/" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl px-7 py-3.5 text-sm font-medium text-white transition-all hover:bg-white/10" style="border: 1px solid rgba(255,255,255,0.3);">
                        Learn more
                    </a>
                </div>

                <!-- Feature highlights -->
                <div class="mt-16 grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl p-5 text-left" style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);">
                        <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: rgba(29,111,230,0.25);">💳</div>
                        <p class="mb-1 text-sm font-semibold">Online Payments</p>
                        <p class="text-xs leading-relaxed text-white/60">Pay via GCash, Maya, or debit/credit card anytime, anywhere</p>
                    </div>
                    <div class="rounded-2xl p-5 text-left" style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);">
                        <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: rgba(22,163,74,0.25);">📊</div>
                        <p class="mb-1 text-sm font-semibold">Real-time Tracking</p>
                        <p class="text-xs leading-relaxed text-white/60">View balances, payment history, and fee breakdowns instantly</p>
                    </div>
                    <div class="rounded-2xl p-5 text-left" style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);">
                        <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl text-lg" style="background: rgba(234,179,8,0.25);">🔔</div>
                        <p class="mb-1 text-sm font-semibold">Smart Notifications</p>
                        <p class="text-xs leading-relaxed text-white/60">Email and SMS alerts for payments and due date reminders</p>
                    </div>
                </div>
            </main>


        </div>
    </div>
</template>