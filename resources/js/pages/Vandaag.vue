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
    tomorrow: Task[];
}>();

const agenda = computed(() => [...props.overdue, ...props.today]);
const agendaEmpty = computed(() => agenda.value.length === 0);
const tomorrowEmpty = computed(() => props.tomorrow.length === 0);
</script>

<template>
    <Head title="" />

    <div class="space-y-10">
        <header>
            <p class="font-mono text-xs tracking-tight text-muted-foreground">
                This week
            </p>
            <h1
                class="mt-1 font-display text-4xl font-semibold tracking-tight capitalize sm:text-5xl"
            >
                {{ heroDate(date) }}
            </h1>
        </header>

        <section aria-label="Today" class="space-y-3">
            <h2 class="text-sm font-semibold text-foreground">Today</h2>
            <div
                v-if="agendaEmpty"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                Nothing scheduled today.
            </div>
            <TaskList v-else :tasks="agenda" :show-project="true" />
        </section>

        <section aria-label="Tomorrow" class="space-y-3">
            <h2 class="text-sm font-semibold text-foreground">Tomorrow</h2>
            <div
                v-if="tomorrowEmpty"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                Nothing scheduled tomorrow.
            </div>
            <TaskList v-else :tasks="tomorrow" :show-project="true" />
        </section>
    </div>
</template>
