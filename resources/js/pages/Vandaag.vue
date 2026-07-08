<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TaskList from '@/components/todai/TaskList.vue';
import { heroDate } from '@/lib/dates';
import type { Task } from '@/types';

const props = defineProps<{
    date: string;
    overdue: Task[];
    today: Task[];
}>();

const tasks = computed(() => [...props.overdue, ...props.today]);
const isEmpty = computed(() => tasks.value.length === 0);
</script>

<template>
    <Head title="Today" />

    <div class="space-y-10">
        <header>
            <p class="font-mono text-xs tracking-tight text-muted-foreground">
                Today
            </p>
            <h1
                class="mt-1 font-display text-4xl font-semibold tracking-tight capitalize sm:text-5xl"
            >
                {{ heroDate(date) }}
            </h1>
        </header>

        <div
            v-if="isEmpty"
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            Nothing scheduled today.
        </div>

        <TaskList v-else :tasks="tasks" :show-project="true" />
    </div>
</template>
