<script setup lang="ts">
import { computed } from 'vue';

/**
 * A card in one of the two voices.
 *  - "ai"    (aqua): everything Todai originates — suggestions, chat, imports.
 *  - "human" (solar): things the person originates and Todai highlights.
 *
 * The two accents only ever coexist through this structural split; the voice
 * is meaning, never decoration.
 */
const props = withDefaults(
    defineProps<{
        voice?: 'ai' | 'human';
        reveal?: boolean;
    }>(),
    { voice: 'ai', reveal: false },
);

const voiceClasses = computed(() =>
    props.voice === 'ai'
        ? 'border-aqua/40 bg-aqua-surface'
        : 'border-solar/50 bg-solar-surface',
);
</script>

<template>
    <div
        :class="[
            'rounded-xl border border-l-2 p-4 shadow-sm',
            voiceClasses,
            reveal ? 'todai-reveal' : '',
        ]"
        :style="
            voice === 'ai'
                ? 'border-left-color: var(--aqua)'
                : 'border-left-color: var(--solar)'
        "
    >
        <slot />
    </div>
</template>

<style scoped>
/* The single place the "AI thinking" reveal lives — one restrained settle. */
.todai-reveal {
    animation: todai-settle 0.32s ease-out both;
}

@keyframes todai-settle {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .todai-reveal {
        animation: none;
    }
}
</style>
