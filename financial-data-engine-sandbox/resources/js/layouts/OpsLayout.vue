<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed, nextTick, ref } from "vue";

import OpsNavigation from "../components/ops/OpsNavigation.vue";

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        pageId: string;
        currentPath?: string;
    }>(),
    {
        description: undefined,
        currentPath: undefined,
    },
);

const isNavigationOpen = ref(false);
const menuButton = ref<HTMLButtonElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const overlayPanel = ref<HTMLElement | null>(null);
const resolvedPath = computed(
    () =>
        props.currentPath ??
        (typeof window === "undefined" ? "" : window.location.pathname),
);

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
    if (event.key === "Escape") {
        event.preventDefault();
        void closeNavigation();
        return;
    }
    if (event.key !== "Tab" || overlayPanel.value === null) return;
    const focusable = Array.from(
        overlayPanel.value.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    );
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
    <div
        data-ops-shell
        class="min-h-screen bg-hissa-canvas font-sans text-hissa-primary"
    >
        <a
            href="#ops-main-content"
            class="sr-only z-[70] rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 font-semibold text-hissa-action shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
            >Skip to content</a
        >
        <div
            class="flex min-h-screen w-full"
            :aria-hidden="isNavigationOpen ? 'true' : undefined"
            :inert="isNavigationOpen ? true : undefined"
        >
            <aside
                data-navigation="desktop"
                class="sticky top-0 hidden h-dvh shrink-0 border-r border-hissa-border bg-hissa-surface lg:flex lg:w-16 lg:flex-col xl:w-56 2xl:w-60"
            >
                <Link
                    href="/ops/pipeline"
                    class="flex h-[68px] items-center gap-3 border-b border-hissa-border px-4 text-hissa-primary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-hissa-action xl:px-5"
                >
                    <span
                        aria-hidden="true"
                        class="grid size-8 shrink-0 place-items-center rounded-md bg-hissa-action text-sm font-bold text-white"
                        >H</span
                    >
                    <span
                        class="truncate text-sm font-semibold lg:max-xl:sr-only"
                        >HISSA Ops</span
                    >
                </Link>
                <OpsNavigation
                    :current-path="resolvedPath"
                    responsive-collapse
                />
                <div
                    class="mt-auto border-t border-hissa-border px-4 py-4 xl:px-5 lg:max-xl:hidden"
                >
                    <p class="text-xs leading-[18px] text-hissa-secondary">
                        Operational review workspace
                    </p>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    data-ops-header
                    aria-label="Ops workspace header"
                    class="sticky top-0 z-30 border-b border-hissa-border bg-hissa-surface/95 backdrop-blur-sm"
                >
                    <div
                        class="mx-auto flex min-h-[76px] w-full max-w-[1680px] items-center gap-3 px-4 sm:gap-4 sm:px-6 xl:px-8"
                    >
                        <button
                            ref="menuButton"
                            type="button"
                            aria-label="Open navigation"
                            aria-controls="ops-navigation-drawer"
                            :aria-expanded="isNavigationOpen ? 'true' : 'false'"
                            class="ops-touch-target grid size-10 shrink-0 place-items-center rounded-md border outline-none transition-colors focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 lg:hidden"
                            :class="
                                isNavigationOpen
                                    ? 'border-hissa-action bg-hissa-secondary-soft text-hissa-action'
                                    : 'border-hissa-border text-hissa-secondary hover:bg-hissa-surface-subtle hover:text-hissa-primary'
                            "
                            @click="openNavigation"
                        >
                            <svg
                                aria-hidden="true"
                                class="size-5"
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-width="1.75"
                            >
                                <path d="M3 5.25h14M3 10h14M3 14.75h14" />
                            </svg>
                        </button>

                        <div class="min-w-0 flex-1 py-3">
                            <nav
                                aria-label="Breadcrumb"
                                class="mb-1 hidden items-center gap-2 text-xs leading-4 text-hissa-secondary md:flex"
                            >
                                <span class="font-medium">Ops</span>
                                <span aria-hidden="true" class="text-hissa-muted"
                                    >/</span
                                >
                                <span
                                    aria-current="page"
                                    class="truncate font-medium text-hissa-primary"
                                    >{{ title }}</span
                                >
                            </nav>
                            <div class="flex min-w-0 items-center gap-3">
                                <h1 class="ops-page-title truncate">{{ title }}</h1>
                                <span
                                    v-if="description !== undefined"
                                    aria-hidden="true"
                                    class="hidden h-5 w-px shrink-0 bg-hissa-border-strong xl:block"
                                />
                                <p
                                    v-if="description !== undefined"
                                    class="hidden min-w-0 truncate text-sm leading-5 text-hissa-secondary xl:block"
                                >
                                    {{ description }}
                                </p>
                            </div>
                        </div>

                        <div
                            data-ops-header-utilities
                            role="group"
                            aria-label="Header utilities"
                            class="flex min-w-0 shrink-0 items-center gap-2 sm:gap-3"
                        >
                            <div
                                v-if="$slots.search"
                                class="hidden min-w-0 max-w-[min(34vw,28rem)] border-l border-hissa-border pl-3 md:block xl:pl-4"
                            >
                                <slot name="search" />
                            </div>
                            <div
                                v-if="$slots.status"
                                class="min-w-0 max-w-[min(42vw,18rem)] border-l border-hissa-border pl-2 sm:pl-3"
                            >
                                <slot name="status" />
                            </div>
                        </div>
                    </div>
                </header>

                <main
                    id="ops-main-content"
                    :data-page="pageId"
                    tabindex="-1"
                    class="min-w-0 flex-1 px-4 py-5 outline-none sm:px-6 sm:py-6 xl:px-8 xl:py-7"
                >
                    <div class="mx-auto w-full max-w-[1680px] space-y-6">
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <div
            v-if="isNavigationOpen"
            data-navigation-overlay
            role="dialog"
            aria-modal="true"
            aria-label="Ops navigation"
            class="fixed inset-0 z-50 bg-black/30 lg:hidden"
            @click.self="closeNavigation"
            @keydown="handleOverlayKeydown"
        >
            <aside
                id="ops-navigation-drawer"
                ref="overlayPanel"
                class="flex h-full w-[min(88vw,320px)] flex-col border-r border-hissa-border bg-hissa-surface pb-[env(safe-area-inset-bottom)] shadow-lg"
            >
                <div
                    class="flex h-[68px] items-center justify-between border-b border-hissa-border px-4"
                >
                    <span class="font-semibold">HISSA Ops</span>
                    <button
                        ref="closeButton"
                        type="button"
                        aria-label="Close navigation"
                        class="ops-touch-target grid min-h-10 place-items-center rounded-md border border-hissa-border px-3 text-xs font-semibold uppercase tracking-wide text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2"
                        @click="closeNavigation"
                    >
                        Close
                    </button>
                </div>
                <OpsNavigation :current-path="resolvedPath" />
            </aside>
        </div>
    </div>
</template>
