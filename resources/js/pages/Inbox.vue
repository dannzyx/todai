<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TaskList from '@/components/todai/TaskList.vue';
import type { Task } from '@/types';

defineProps<{
    tasks: Task[];
}>();
</script>

<template>
    <Head title="Inbox" />

    <div class="space-y-10">
        <header>
            <p class="font-mono text-xs tracking-tight text-muted-foreground">
                Inbox
            </p>
            <h1
                class="mt-1 font-display text-4xl font-semibold tracking-tight sm:text-5xl"
            >
                Inbox
            </h1>
        </header>

        <div
            v-if="tasks.length === 0"
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            Niks in je inbox. Typ hieronder om iets toe te voegen.
        </div>

        <TaskList v-else :tasks="tasks" :show-project="false" />
    </div>
</template>
