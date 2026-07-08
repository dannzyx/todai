<script setup lang="ts">
import AppRail from '@/components/todai/AppRail.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

/**
 * The Todai shell: a slim top rail over a single calm working column.
 * No fixed left sidebar. The command palette and persistent compose bar
 * are layered in on top of this shell in later phases.
 *
 * `breadcrumbs` is accepted (some starter-kit pages still pass it as a layout
 * prop) but the top rail replaces breadcrumb navigation, so it is ignored.
 */
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const handleOpenCommand = (): void => {
    // Wired to the command palette in Phase 2.
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <AppRail @open-command="handleOpenCommand" />

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
