<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Meta from '@/components/todai/Meta.vue';
import TaskItem from '@/components/todai/TaskItem.vue';
import TaskList from '@/components/todai/TaskList.vue';
import { heroDate } from '@/lib/dates';
import type { Task } from '@/types';

const props = defineProps<{
    date: string;
    overdue: Task[];
    today: Task[];
    inbox: Task[];
}>();

const agenda = computed(() => [...props.overdue, ...props.today]);
const agendaEmpty = computed(() => agenda.value.length === 0);
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

        <section aria-label="Agenda">
            <div
                v-if="agendaEmpty"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                Nothing scheduled today.
            </div>
            <TaskList v-else :tasks="agenda" :show-project="true" />
        </section>

        <section aria-label="Inbox">
            <div class="mb-3 flex items-center justify-between">
                <Link
                    href="/inbox"
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:text-foreground"
                >
                    Inbox
                </Link>
                <Meta>{{ inbox.length }}</Meta>
            </div>

            <p
                v-if="inbox.length === 0"
                class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
            >
                Nothing in your inbox.
            </p>

            <ul v-else class="space-y-2">
                <li v-for="task in inbox" :key="task.id">
                    <TaskItem :task="task" :show-project="false" />
                </li>
            </ul>
        </section>
    </div>
</template>
