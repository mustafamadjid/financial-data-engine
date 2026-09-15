<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface NavigationItem {
    id: string;
    label: string;
    shortLabel: string;
    href: string;
}

interface NavigationGroup {
    label: string;
    items: readonly NavigationItem[];
}

const props = withDefaults(defineProps<{
    currentPath: string;
    responsiveCollapse?: boolean;
}>(), {
    responsiveCollapse: false,
});

const navigationGroups: readonly NavigationGroup[] = [
    {
        label: 'Operations',
        items: [
            { id: 'pipeline', label: 'Filings & Pipeline', shortLabel: 'FP', href: '/ops/pipeline' },
        ],
    },
    {
        label: 'Review',
        items: [
            { id: 'financial-review', label: 'Financial Review', shortLabel: 'FR', href: '/ops/financial-review' },
            { id: 'concept-mapping', label: 'Concept Mapping', shortLabel: 'CM', href: '/ops/concept-mappings' },
        ],
    },
    {
        label: 'Quality',
        items: [
            { id: 'data-quality', label: 'Data Quality', shortLabel: 'DQ', href: '/ops/data-quality' },
        ],
    },
];

const groups = computed(() => navigationGroups.map((group) => ({
    ...group,
    items: group.items.map((item) => ({
        ...item,
        active: props.currentPath === item.href || props.currentPath.startsWith(`${item.href}/`),
    })),
})));
</script>

<template>
    <nav data-ops-navigation aria-label="Ops workspaces" class="flex min-h-0 flex-1 flex-col overflow-y-auto">
        <ul class="space-y-6 px-2 py-5">
            <li v-for="group in groups" :key="group.label">
                <p class="ops-meta mb-2 px-3 font-semibold uppercase tracking-[0.08em] text-hissa-muted" :class="responsiveCollapse ? 'lg:max-xl:sr-only' : undefined">{{ group.label }}</p>
                <ul class="space-y-1">
                    <li v-for="item in group.items" :key="item.id">
                        <Link
                            :href="item.href"
                            :aria-current="item.active ? 'page' : undefined"
                            :title="responsiveCollapse ? item.label : undefined"
                            class="ops-touch-target group flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-sm font-medium outline-none transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 focus-visible:ring-offset-hissa-surface lg:max-xl:justify-center lg:max-xl:px-2"
                            :class="item.active ? 'bg-hissa-secondary-soft font-semibold text-hissa-primary' : 'text-hissa-secondary hover:bg-hissa-subtle hover:text-hissa-primary'"
                        >
                            <span aria-hidden="true" class="grid size-7 shrink-0 place-items-center rounded-md border text-[10px] font-bold transition-colors" :class="item.active ? 'border-hissa-action bg-hissa-action text-white' : 'border-hissa-border bg-hissa-surface-muted text-hissa-secondary group-hover:border-hissa-border-strong group-hover:text-hissa-primary'">{{ item.shortLabel }}</span>
                            <span :class="responsiveCollapse ? 'lg:max-xl:sr-only' : undefined">{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</template>
