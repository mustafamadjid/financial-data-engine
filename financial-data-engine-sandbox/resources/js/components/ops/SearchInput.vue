<script setup lang="ts">
withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue?: string;
    placeholder?: string;
    type?: 'search' | 'text';
    inputClass?: string;
}>(), {
    modelValue: '',
    placeholder: undefined,
    type: 'search',
    inputClass: 'w-full',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    change: [event: Event];
}>();
</script>

<template>
    <label :for="id" class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
        <span>{{ label }}</span>
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :class="['min-h-10 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20 disabled:cursor-not-allowed disabled:bg-hissa-surface-muted disabled:text-hissa-muted', inputClass]"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            @change="emit('change', $event)"
        />
    </label>
</template>
