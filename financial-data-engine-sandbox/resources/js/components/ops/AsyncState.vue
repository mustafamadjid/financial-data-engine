<script setup lang="ts">
import { computed } from 'vue';

withDefaults(defineProps<{
    state: 'loading' | 'empty' | 'error' | 'disabled';
    title: string;
    message?: string;
    embedded?: boolean;
}>(), { message: undefined, embedded: false });

const stateClasses = computed(() => ({
    loading: 'border-hissa-border bg-hissa-surface',
    empty: 'border-hissa-border bg-hissa-surface',
    error: 'border-hissa-danger/30 bg-hissa-danger-soft',
    disabled: 'border-hissa-border bg-hissa-surface-muted',
}));
</script>

<template>
    <div
        :role="state === 'error' ? 'alert' : 'status'"
        :aria-live="state === 'error' ? 'assertive' : 'polite'"
        aria-atomic="true"
        :aria-busy="state === 'loading' ? 'true' : undefined"
        :data-state="state"
        class="w-full text-left"
        :class="embedded ? ['p-4 sm:p-5', state === 'error' ? 'bg-hissa-danger-soft/40' : undefined] : ['rounded-lg border p-5', stateClasses[state]]"
    >
        <div class="flex items-start gap-3">
            <span aria-hidden="true" class="mt-1.5 size-2 shrink-0 rounded-full bg-hissa-secondary" :class="{
                'animate-pulse bg-hissa-info': state === 'loading',
                'bg-hissa-danger': state === 'error',
                'bg-hissa-muted-state': state === 'disabled',
            }" />
            <div class="min-w-0">
                <p class="font-semibold leading-5 text-hissa-primary">{{ title }}</p>
                <p v-if="message !== undefined" class="ops-wrap mt-1 max-w-3xl text-sm leading-5 text-hissa-secondary">{{ message }}</p>
                <div v-if="$slots.action" class="mt-4 flex flex-wrap gap-2"><slot name="action" /></div>
            </div>
        </div>
    </div>
</template>
