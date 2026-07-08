<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string; icon: unknown }[] = [
    { value: 'light', label: 'Licht', icon: Sun },
    { value: 'dark', label: 'Donker', icon: Moon },
    { value: 'system', label: 'Systeem', icon: Monitor },
];

const activeIcon = computed(() => {
    return (
        options.find((option) => option.value === appearance.value)?.icon ?? Sun
    );
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label="Weergave wisselen"
        >
            <component :is="activeIcon" class="h-4 w-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="cursor-pointer"
                @click="updateAppearance(option.value)"
            >
                <component :is="option.icon" class="mr-2 h-4 w-4" />
                {{ option.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
