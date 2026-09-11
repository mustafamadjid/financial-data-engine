<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

import OpsNavigation from '../components/ops/OpsNavigation.vue';

const props = withDefaults(defineProps<{
    title: string;
    description?: string;
    pageId: string;
    currentPath?: string;
}>(), {
    description: undefined,
    currentPath: undefined,
});

const isNavigationOpen = ref(false);
const menuButton = ref<HTMLButtonElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const overlayPanel = ref<HTMLElement | null>(null);
const resolvedPath = computed(() => props.currentPath ?? (typeof window === 'undefined' ? '' : window.location.pathname));

async function openNavigation(): Promise<void> {
    isNavigationOpen.value = true;
    await nextTick();
    closeButton.value?.focus();
}

async function closeNavigation(): Promise<void> {
    isNavigationOpen.value = false;
    await nextTick();
    menuButton.value?.focus();
}

function handleOverlayKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        void closeNavigation();
        return;
    }
    if (event.key !== 'Tab' || overlayPanel.value === null) return;
    const focusable = Array.from(overlayPanel.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'));
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
</script>

<template>
    <div class="min-h-screen bg-hissa-canvas font-sans text-hissa-primary">
        <a href="#ops-main-content" class="sr-only z-[70] rounded-lg bg-hissa-surface px-3 py-2 font-semibold text-hissa-action focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to content</a>
        <div class="mx-auto flex min-h-screen w-full max-w-[1408px]">
            <aside data-navigation="desktop" class="hidden shrink-0 border-r border-hissa-border bg-hissa-surface lg:flex lg:w-[72px] lg:flex-col xl:w-[232px]">
                <Link href="/ops/pipeline" class="flex h-16 items-center gap-3 border-b border-hissa-border px-5 text-hissa-primary outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-hissa-action">
                    <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-lg bg-hissa-action text-sm font-bold text-white">H</span>
                    <span class="text-sm font-semibold lg:max-xl:sr-only">HISSA Ops</span>
                </Link>
                <OpsNavigation :current-path="resolvedPath" responsive-collapse />
                <p class="mx-3 mb-4 mt-auto rounded-lg bg-hissa-subtle px-3 py-2 text-xs leading-[18px] text-hissa-secondary lg:max-xl:sr-only">Operational review workspace</p>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex min-h-16 items-center gap-3 border-b border-hissa-border bg-hissa-surface px-4 sm:px-6">
                    <button
                        ref="menuButton"
                        type="button"
                        aria-label="Open navigation"
                        class="grid size-10 shrink-0 place-items-center rounded-lg border border-hissa-border text-hissa-secondary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 lg:hidden"
                        @click="openNavigation"
                    >
                        <span aria-hidden="true" class="text-xl leading-none">Menu</span>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-2xl font-semibold leading-8">{{ title }}</h1>
                        <p v-if="description !== undefined" class="hidden truncate text-sm leading-[21px] text-hissa-secondary sm:block">{{ description }}</p>
                    </div>
                    <div v-if="$slots.search" class="hidden min-w-0 md:block"><slot name="search" /></div>
                    <div v-if="$slots.status" class="shrink-0"><slot name="status" /></div>
                </header>

                <main id="ops-main-content" :data-page="pageId" tabindex="-1" class="min-w-0 flex-1 p-4 outline-none sm:p-6">
                    <div class="space-y-6"><slot /></div>
                </main>
            </div>
        </div>

        <div
            v-if="isNavigationOpen"
            data-navigation-overlay
            role="dialog"
            aria-modal="true"
            aria-label="Ops navigation"
            class="fixed inset-0 z-50 bg-black/40 lg:hidden"
            @click.self="closeNavigation"
            @keydown="handleOverlayKeydown"
        >
            <aside ref="overlayPanel" class="flex h-full w-[min(88vw,288px)] flex-col bg-hissa-surface shadow-xl">
                <div class="flex h-16 items-center justify-between border-b border-hissa-border px-4">
                    <span class="font-semibold">HISSA Ops</span>
                    <button ref="closeButton" type="button" aria-label="Close navigation" class="grid size-10 place-items-center rounded-lg text-hissa-secondary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="closeNavigation">Close</button>
                </div>
                <OpsNavigation :current-path="resolvedPath" />
            </aside>
        </div>
    </div>
</template>
