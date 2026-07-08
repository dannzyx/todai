<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Meta from '@/components/todai/Meta.vue';
import TaskItem from '@/components/todai/TaskItem.vue';
import TaskList from '@/components/todai/TaskList.vue';
import { heroDate } from '@/lib/dates';
import type { Project, Task } from '@/types';

const props = defineProps<{
    date: string;
    overdue: Task[];
    today: Task[];
    inbox: Task[];
    projects: Project[];
}>();

defineOptions({
    layout: { wide: true },
});

const agenda = computed(() => [...props.overdue, ...props.today]);
const agendaEmpty = computed(() => agenda.value.length === 0);

// Keep the dashboard compact; the full list lives on the Inbox page.
const INBOX_PREVIEW = 6;
const inboxPreview = computed(() => props.inbox.slice(0, INBOX_PREVIEW));
const inboxOverflow = computed(() => props.inbox.length - inboxPreview.value.length);
</script>

<template>
    <Head title="Today" />

    <div class="space-y-8">
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

        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Agenda: overdue + due today -->
            <section class="lg:col-span-2" aria-label="Agenda">
                <div
                    v-if="agendaEmpty"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    Nothing scheduled today.
                </div>
                <TaskList v-else :tasks="agenda" :show-project="true" />
            </section>

            <!-- Side rail: inbox + projects -->
            <aside class="space-y-8">
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
                        class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
                    >
                        Nothing in your inbox.
                    </p>

                    <ul v-else class="space-y-2">
                        <li v-for="task in inboxPreview" :key="task.id">
                            <TaskItem :task="task" :show-project="false" />
                        </li>
                    </ul>

                    <Link
                        v-if="inboxOverflow > 0"
                        href="/inbox"
                        class="mt-2 inline-block text-xs text-muted-foreground hover:text-foreground"
                    >
                        +{{ inboxOverflow }} more →
                    </Link>
                </section>

                <section aria-label="Projects">
                    <div class="mb-3 flex items-center justify-between">
                        <Link
                            :href="ProjectController.index().url"
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:text-foreground"
                        >
                            Projects
                        </Link>
                        <Meta>{{ projects.length }}</Meta>
                    </div>

                    <p
                        v-if="projects.length === 0"
                        class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
                    >
                        No projects yet.
                    </p>

                    <ul v-else class="space-y-1">
                        <li v-for="project in projects" :key="project.id">
                            <Link
                                :href="ProjectController.show(project).url"
                                class="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-accent"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor: project.color ?? '#6B7280',
                                    }"
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm text-foreground"
                                >
                                    {{ project.name }}
                                </span>
                                <Meta>{{ project.open_tasks_count ?? 0 }}</Meta>
                            </Link>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
