<template>
    <div data-ops-toolbar class="flex flex-wrap items-center justify-between gap-3 border-b border-hissa-border px-4 py-3 sm:px-5">
        <div class="min-w-0">
            <slot name="title" />
            <slot name="description" />
        </div>
        <div v-if="$slots.actions" class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
            <slot name="actions" />
        </div>
    </div>
</template>
