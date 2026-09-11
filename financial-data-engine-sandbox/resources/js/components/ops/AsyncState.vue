<script setup lang="ts">
withDefaults(defineProps<{
    state: 'loading' | 'empty' | 'error' | 'disabled';
    title: string;
    message?: string;
}>(), { message: undefined });
</script>

<template>
    <div :role="state === 'error' ? 'alert' : 'status'" :aria-busy="state === 'loading' ? 'true' : undefined" class="rounded-xl border border-hissa-border bg-hissa-surface p-6 text-center">
        <div v-if="state === 'loading'" aria-hidden="true" class="mx-auto mb-3 h-3 w-32 animate-pulse rounded bg-hissa-subtle" />
        <p class="font-semibold text-hissa-primary">{{ title }}</p>
        <p v-if="message !== undefined" class="mt-1 text-sm leading-[21px] text-hissa-secondary">{{ message }}</p>
        <div v-if="$slots.action" class="mt-4"><slot name="action" /></div>
    </div>
</template>
