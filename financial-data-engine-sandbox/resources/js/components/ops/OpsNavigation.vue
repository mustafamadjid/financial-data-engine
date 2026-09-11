<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface NavigationItem {
    id: string;
    label: string;
    shortLabel: string;
    href: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<{
    currentPath: string;
    responsiveCollapse?: boolean;
}>(), {
    responsiveCollapse: false,
});

const navigationItems: readonly NavigationItem[] = [
    { id: 'pipeline', label: 'Filings & Pipeline', shortLabel: 'FP', href: '/ops/pipeline' },
    { id: 'financial-review', label: 'Financial Review', shortLabel: 'FR', href: '/ops/financial-review' },
    { id: 'concept-mapping', label: 'Concept Mapping', shortLabel: 'CM', href: '/ops/concept-mapping' },
    { id: 'data-quality', label: 'Data Quality', shortLabel: 'DQ', href: '/ops/data-quality' },
    { id: 'debt-review', label: 'Debt Review', shortLabel: 'DR', href: '/ops/debt-review', disabled: true },
];

const items = computed(() => navigationItems.map((item) => ({
    ...item,
    active: props.currentPath === item.href || props.currentPath.startsWith(`${item.href}/`),
})));
</script>

<template>
    <nav aria-label="Ops workspaces" class="flex min-h-0 flex-1 flex-col">
        <ul class="space-y-1 px-3 py-4">
            <li v-for="item in items" :key="item.id">
                <Link
                    v-if="!item.disabled"
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2"
                    :class="item.active ? 'bg-hissa-secondary-soft text-hissa-action' : 'text-hissa-secondary hover:bg-hissa-subtle hover:text-hissa-primary'"
                >
                    <span aria-hidden="true" class="grid size-7 shrink-0 place-items-center rounded-md border border-current/20 text-[10px] font-bold">{{ item.shortLabel }}</span>
                    <span :class="responsiveCollapse ? 'lg:max-xl:sr-only' : undefined">{{ item.label }}</span>
                </Link>
                <span
                    v-else
                    aria-disabled="true"
                    class="flex min-h-10 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-hissa-secondary/60"
                    :title="`${item.label} is not available yet.`"
                >
                    <span aria-hidden="true" class="grid size-7 shrink-0 place-items-center rounded-md border border-current/20 text-[10px] font-bold">{{ item.shortLabel }}</span>
                    <span :class="responsiveCollapse ? 'lg:max-xl:sr-only' : undefined">{{ item.label }}</span>
                    <span class="ml-auto rounded-full border border-hissa-border px-2 py-0.5 text-[10px] uppercase tracking-wide" :class="responsiveCollapse ? 'lg:max-xl:sr-only' : undefined">Later</span>
                </span>
            </li>
        </ul>
    </nav>
</template>
