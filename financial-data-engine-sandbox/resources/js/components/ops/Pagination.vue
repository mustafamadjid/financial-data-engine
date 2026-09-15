<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
    perPage?: number;
    totalLabel?: string;
    ariaLabel?: string;
}>(), {
    perPage: 25,
    totalLabel: 'items',
    ariaLabel: 'Pagination',
});

const rangeStart = computed(() => props.total === 0 ? 0 : ((props.currentPage - 1) * props.perPage) + 1);
const rangeEnd = computed(() => Math.min(props.total, props.currentPage * props.perPage));

const emit = defineEmits<{
    'change-page': [page: number];
}>();
</script>

<template>
    <nav v-if="lastPage > 1" data-ops-pagination :aria-label="ariaLabel" class="flex flex-wrap items-start justify-between gap-2 border-t border-hissa-border px-4 py-3 text-sm sm:items-center sm:px-5">
        <p class="ops-numeric max-w-full text-hissa-secondary">{{ rangeStart }}–{{ rangeEnd }} of {{ total }} {{ totalLabel }} <span aria-hidden="true">·</span> Page {{ currentPage }} of {{ lastPage }}</p>
        <div class="flex shrink-0 gap-2">
            <button
                type="button"
                aria-label="Previous page"
                class="ops-touch-target min-h-10 whitespace-nowrap rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 font-semibold text-hissa-primary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-hissa-surface-muted disabled:text-hissa-muted"
                :disabled="currentPage <= 1"
                @click="emit('change-page', currentPage - 1)"
            >
                Previous
            </button>
            <button
                type="button"
                aria-label="Next page"
                class="ops-touch-target min-h-10 whitespace-nowrap rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 font-semibold text-hissa-primary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-hissa-surface-muted disabled:text-hissa-muted"
                :disabled="currentPage >= lastPage"
                @click="emit('change-page', currentPage + 1)"
            >
                Next
            </button>
        </div>
    </nav>
</template>
