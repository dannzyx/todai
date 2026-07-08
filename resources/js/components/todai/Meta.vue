<script setup lang="ts">
/**
 * Machine metadata, always in the mono face: dates, counts, confidence,
 * meeting labels. Never used for human-authored prose.
 */
withDefaults(
    defineProps<{
        as?: string;
    }>(),
    { as: 'span' },
);
</script>

<template>
    <component
        :is="as"
        class="font-mono text-xs tracking-tight text-muted-foreground"
    >
        <slot />
    </component>
</template>
