<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import TaskList from '@/components/todai/TaskList.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Task } from '@/types';

type Filters = {
    search: string;
    project: string;
    status: string;
};

const props = defineProps<{
    tasks: Task[];
    filters: Filters;
}>();

const page = usePage();
const projects = computed(() => page.props.activeProjects ?? []);

const search = ref(props.filters.search);
const project = ref(props.filters.project);
const status = ref(props.filters.status);

// Push the current filters into the query string and reload just the task list.
// preserveState keeps the local filter refs (and focus) intact between reloads.
const reload = (): void => {
    router.get(
        TaskController.index().url,
        {
            search: search.value,
            project: project.value,
            status: status.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tasks', 'filters'],
        },
    );
};

watchDebounced(search, reload, { debounce: 300 });
watch([project, status], reload);

const hasFilters = computed(
    () =>
        search.value.trim() !== '' ||
        project.value !== 'all' ||
        status.value !== 'open',
);

const clearFilters = (): void => {
    search.value = '';
    project.value = 'all';
    status.value = 'open';
};
</script>

<template>
    <Head title="All tasks" />

    <div class="space-y-8">
        <header>
            <p class="font-mono text-xs tracking-tight text-muted-foreground">
                Tasks
            </p>
            <h1
                class="mt-1 font-display text-4xl font-semibold tracking-tight sm:text-5xl"
            >
                All tasks
            </h1>
        </header>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Input
                v-model="search"
                type="search"
                placeholder="Search tasks..."
                aria-label="Search tasks"
                class="sm:flex-1"
            />

            <div class="flex gap-2">
                <Select v-model="project">
                    <SelectTrigger
                        class="w-full sm:w-44"
                        aria-label="Filter by project"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All projects</SelectItem>
                        <SelectItem value="inbox">Inbox (no project)</SelectItem>
                        <SelectItem
                            v-for="option in projects"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="status">
                    <SelectTrigger
                        class="w-full sm:w-32"
                        aria-label="Filter by status"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="open">Open</SelectItem>
                        <SelectItem value="all">All</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div
            v-if="tasks.length === 0"
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            <p>No tasks match these filters.</p>
            <button
                v-if="hasFilters"
                type="button"
                class="mt-2 text-sm font-medium text-solar-strong hover:underline"
                @click="clearFilters"
            >
                Clear filters
            </button>
        </div>

        <TaskList v-else :tasks="tasks" />
    </div>
</template>
