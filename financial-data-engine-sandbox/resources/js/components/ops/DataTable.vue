<script setup lang="ts">
withDefaults(defineProps<{ minWidthClass?: string; captionClass?: string }>(), {
    minWidthClass: 'min-w-[960px]',
    captionClass: 'sr-only',
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-separate border-spacing-0 text-left text-sm" :class="minWidthClass">
            <caption :class="captionClass"><slot name="caption" /></caption>
            <slot />
        </table>
    </div>
</template>
