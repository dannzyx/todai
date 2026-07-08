<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, ArrowLeft } from '@lucide/vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Meta from '@/components/todai/Meta.vue';
import ProjectForm from '@/components/todai/ProjectForm.vue';
import TaskForm from '@/components/todai/TaskForm.vue';
import TaskList from '@/components/todai/TaskList.vue';
import { Button } from '@/components/ui/button';
import type { Project, Task } from '@/types';

const props = defineProps<{
    project: Project;
    tasks: Task[];
}>();

const isArchived = () => props.project.archived_at !== null;

const toggleArchive = () => {
    const action = isArchived()
        ? ProjectController.unarchive(props.project)
        : ProjectController.archive(props.project);

    router.patch(action.url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="project.name" />

    <div class="space-y-8">
        <Link
            :href="ProjectController.index().url"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="h-4 w-4" />
            All projects
        </Link>

        <header class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="h-4 w-4 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color ?? '#6B7280' }"
                    aria-hidden="true"
                />
                <div>
                    <h1
                        class="font-display text-4xl font-semibold tracking-tight"
                    >
                        {{ project.name }}
                    </h1>
                    <Meta>{{ project.open_tasks_count ?? 0 }} open tasks</Meta>
                </div>
            </div>

            <Button variant="outline" size="sm" @click="toggleArchive">
                <component
                    :is="isArchived() ? ArchiveRestore : Archive"
                    class="mr-1.5 h-4 w-4"
                />
                {{ isArchived() ? 'Restore' : 'Archive' }}
            </Button>
        </header>

        <section aria-label="Tasks" class="space-y-4">
            <h2 class="text-sm font-semibold text-foreground">Tasks</h2>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <TaskForm :default-project-id="project.id" />
            </div>

            <div
                v-if="tasks.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                No tasks in this project yet.
            </div>
            <TaskList v-else :tasks="tasks" :show-project="false" />
        </section>

        <section
            class="rounded-xl border border-border bg-card p-5 shadow-sm"
            aria-label="Edit project"
        >
            <h2 class="mb-4 text-sm font-semibold text-foreground">
                Edit project
            </h2>
            <ProjectForm :project="project" submit-label="Save" />
        </section>
    </div>
</template>
