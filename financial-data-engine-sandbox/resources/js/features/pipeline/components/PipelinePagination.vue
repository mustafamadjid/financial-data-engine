<script setup lang="ts">
const props = defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
}>();

const emit = defineEmits<{
    'change-page': [page: number];
}>();
</script>

<template>
    <nav v-if="lastPage > 1" aria-label="Pipeline pagination" class="flex items-center justify-between gap-4 border-t border-hissa-border px-4 py-3">
        <p class="text-sm text-hissa-secondary">{{ total }} filings · Page {{ currentPage }} of {{ lastPage }}</p>
        <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-hissa-border px-3 py-1.5 text-sm font-semibold text-hissa-primary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action disabled:cursor-not-allowed disabled:text-hissa-secondary" :disabled="currentPage <= 1" @click="emit('change-page', currentPage - 1)">Previous</button>
            <button type="button" class="rounded-lg border border-hissa-border px-3 py-1.5 text-sm font-semibold text-hissa-primary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action disabled:cursor-not-allowed disabled:text-hissa-secondary" :disabled="currentPage >= lastPage" @click="emit('change-page', currentPage + 1)">Next</button>
        </div>
    </nav>
</template>
