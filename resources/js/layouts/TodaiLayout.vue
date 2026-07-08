<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import AppRail from '@/components/todai/AppRail.vue';
import CommandPalette from '@/components/todai/CommandPalette.vue';
import ComposeBar from '@/components/todai/ComposeBar.vue';
import TaskModal from '@/components/todai/TaskModal.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

/**
 * The Todai shell: a slim top rail over a single calm working column, with the
 * persistent compose bar pinned to the bottom and a Cmd/Ctrl+K command palette.
 * No fixed left sidebar.
 *
 * `breadcrumbs` is accepted (some starter-kit pages still pass it as a layout
 * prop) but the top rail replaces breadcrumb navigation, so it is ignored.
 */
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const paletteOpen = ref(false);

const openCommand = (): void => {
    paletteOpen.value = true;
};

const onKeydown = (event: KeyboardEvent): void => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        paletteOpen.value = !paletteOpen.value;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <AppRail @open-command="openCommand" />

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6">
            <slot />
        </main>

        <ComposeBar />

        <CommandPalette v-model:open="paletteOpen" />

        <TaskModal />

        <Toaster />
    </div>
</template>
