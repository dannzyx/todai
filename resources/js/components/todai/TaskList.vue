<script setup lang="ts">
import { computed, ref } from 'vue';
import TaskItem from '@/components/todai/TaskItem.vue';
import { bucketFor } from '@/lib/dates';
import type { DateBucket } from '@/lib/dates';
import type { Task } from '@/types';

const props = withDefaults(
    defineProps<{
        tasks: Task[];
        showProject?: boolean;
    }>(),
    { showProject: true },
);

const showCompleted = ref(false);

const GROUP_ORDER: { key: DateBucket; label: string }[] = [
    { key: 'overdue', label: 'Overdue' },
    { key: 'today', label: 'Today' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'none', label: 'No date' },
];

const open = computed(() => props.tasks.filter((task) => !task.completed_at));
const completed = computed(() =>
    props.tasks.filter((task) => task.completed_at),
);

const groups = computed(() =>
    GROUP_ORDER.map((group) => ({
        ...group,
        tasks: open.value.filter((task) => bucketFor(task) === group.key),
    })).filter((group) => group.tasks.length > 0),
);

// Single colour class per group so no static/dynamic Tailwind conflict occurs.
const headerColor = (key: DateBucket): string => {
    if (key === 'overdue') {
        return 'text-destructive';
    }

    if (key === 'today') {
        return 'text-solar-strong';
    }

    return 'text-muted-foreground';
};
</script>

<template>
    <div class="space-y-8">
        <section v-for="group in groups" :key="group.key">
            <h2
                class="mb-3 text-xs font-semibold tracking-wide uppercase"
                :class="headerColor(group.key)"
            >
                {{ group.label }}
            </h2>
            <ul class="space-y-2">
                <li v-for="task in group.tasks" :key="task.id">
                    <TaskItem :task="task" :show-project="showProject" />
                </li>
            </ul>
        </section>

        <section v-if="completed.length > 0">
            <button
                type="button"
                class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:text-foreground"
                @click="showCompleted = !showCompleted"
            >
                {{ showCompleted ? 'Hide' : 'Show' }} completed ({{
                    completed.length
                }})
            </button>
            <ul v-show="showCompleted" class="space-y-2">
                <li v-for="task in completed" :key="task.id">
                    <TaskItem :task="task" :show-project="showProject" />
                </li>
            </ul>
        </section>
    </div>
</template>
