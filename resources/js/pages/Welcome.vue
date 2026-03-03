<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
// import { route } from 'ziggy-js';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

const backgrounds = [
    '/images/bg1.jpg',
    '/images/bg2.jpg',
    '/images/bg3.jpg',
    '/images/bg4.jpg',
    '/images/bg5.jpg',
    '/images/bg6.jpg',
    '/images/bg7.jpg',
    '/images/bg11.jpg',
    '/images/bg12.jpg',
    '/images/bg13.jpg',
    '/images/bg14.jpg',
    '/images/bg15.jpg',
    '/images/bg16.jpg',
];

const currentIndex = ref(0);
let interval: number;

onMounted(() => {
    interval = window.setInterval(() => {
        currentIndex.value = (currentIndex.value + 1) % backgrounds.length;
    }, 5000);
});

onUnmounted(() => {
    clearInterval(interval);
});

const translateX = computed(() => `-${currentIndex.value * 100}%`);
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="relative min-h-screen overflow-hidden text-white">
        <!-- Slideshow container -->
        <div class="absolute inset-0 flex transition-transform duration-1000 ease-in-out" :style="{ transform: `translateX(${translateX})` }">
            <div
                v-for="(bg, index) in backgrounds"
                :key="index"
                class="min-h-screen min-w-full bg-cover bg-center bg-no-repeat"
                :style="{ backgroundImage: `url(${bg})` }"
            />
        </div>

        <!-- Overlay for content -->
        <div class="relative z-10 flex min-h-screen flex-col bg-black/60 p-6 lg:p-8">
            <!-- Top-right nav -->
            <header class="mb-6 flex w-full justify-end">
                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('dashboard')"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 leading-normal text-[#eaeaea] hover:border-white/60 dark:border-[#ddddcb] dark:hover:border-[#ffffff]"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-block rounded-sm border border-transparent px-5 py-1.5 leading-normal text-white hover:border-white/50"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-block rounded-sm border border-white/40 px-5 py-1.5 leading-normal text-white hover:border-white"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <!-- Center content -->
            <main class="flex w-full flex-1 items-start justify-center">
                <div class="mx-auto max-w-screen-xl px-4 py-24 text-center lg:py-56">
                    <h1 class="mb-4 text-4xl leading-none font-extrabold tracking-tight text-white drop-shadow-lg md:text-5xl lg:text-6xl">
                        Computer Communication Development Institute
                    </h1>
                    <p class="mb-8 text-lg font-normal text-gray-100 drop-shadow-md sm:px-16 lg:px-48 lg:text-xl">
                        CCDI envisions of providing a service of leadership through excellent instructions that will produce empowered and world-class
                        I.T. graduates.
                    </p>
                    <div class="flex flex-col space-y-4 sm:flex-row sm:justify-center sm:space-y-0">
                        <!-- Get Started: dynamic route -->
                        <Link
                            :href="page.props.auth.user ? route('dashboard') : route('login')"
                            class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-center text-base font-medium text-white shadow hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-900"
                        >
                            Get Started
                            <svg
                                class="ms-2 h-3.5 w-3.5 drop-shadow rtl:rotate-180"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 14 10"
                            >
                                <path
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M1 5h12m0 0L9 1m4 4L9 9"
                                />
                            </svg>
                        </Link>
                        <!-- Learn more: external link -->
                        <a
                            href="https://www.ccdisorsogon.edu.ph/"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-lg border border-white px-5 py-3 text-center text-base font-medium text-white shadow hover:bg-white hover:text-black focus:ring-4 focus:ring-white/40 sm:ms-4"
                        >
                            Learn more
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
