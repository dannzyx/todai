<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Meta from '@/components/todai/Meta.vue';
import ProjectForm from '@/components/todai/ProjectForm.vue';
import { Button } from '@/components/ui/button';
import type { Project } from '@/types';

defineProps<{
    active: Project[];
    archived: Project[];
}>();

const showArchived = ref(false);

const archive = (project: Project) => {
    router.patch(
        ProjectController.archive(project).url,
        {},
        { preserveScroll: true },
    );
};

const unarchive = (project: Project) => {
    router.patch(
        ProjectController.unarchive(project).url,
        {},
        { preserveScroll: true },
    );
};

const taskCountLabel = (count: number | undefined): string => {
    const value = count ?? 0;

    return value === 1 ? '1 open taak' : `${value} open taken`;
};
</script>

<template>
    <Head title="Projecten" />

    <div class="space-y-10">
        <header class="space-y-1">
            <h1 class="font-display text-4xl font-semibold tracking-tight">
                Projecten
            </h1>
            <p class="text-sm text-muted-foreground">
                Bundel je taken in projecten. Gearchiveerde projecten verdwijnen
                uit de keuzelijsten, hun taken blijven bestaan.
            </p>
        </header>

        <section
            class="rounded-xl border border-border bg-card p-5 shadow-sm"
            aria-label="Nieuw project"
        >
            <h2 class="mb-4 text-sm font-semibold text-foreground">
                Nieuw project
            </h2>
            <ProjectForm />
        </section>

        <section aria-label="Actieve projecten" class="space-y-3">
            <div
                v-if="active.length === 0"
                class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                Nog geen projecten. Maak er hierboven één aan.
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="project in active"
                    :key="project.id"
                    class="group flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:border-solar/50"
                >
                    <span
                        class="h-3 w-3 shrink-0 rounded-full"
                        :style="{ backgroundColor: project.color ?? '#6B7280' }"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="ProjectController.show(project).url"
                            class="block truncate font-medium text-foreground hover:underline"
                        >
                            {{ project.name }}
                        </Link>
                        <Meta>{{
                            taskCountLabel(project.open_tasks_count)
                        }}</Meta>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                        @click="archive(project)"
                    >
                        <Archive class="mr-1.5 h-4 w-4" />
                        Archiveer
                    </Button>
                </li>
            </ul>
        </section>

        <section
            v-if="archived.length > 0"
            aria-label="Gearchiveerde projecten"
        >
            <button
                type="button"
                class="mb-3 text-sm font-medium text-muted-foreground hover:text-foreground"
                @click="showArchived = !showArchived"
            >
                {{ showArchived ? 'Verberg' : 'Toon' }} archief ({{
                    archived.length
                }})
            </button>

            <ul v-show="showArchived" class="space-y-2">
                <li
                    v-for="project in archived"
                    :key="project.id"
                    class="flex items-center gap-3 rounded-xl border border-border bg-muted/40 px-4 py-3"
                >
                    <span
                        class="h-3 w-3 shrink-0 rounded-full opacity-50"
                        :style="{ backgroundColor: project.color ?? '#6B7280' }"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 truncate text-muted-foreground">
                        {{ project.name }}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="unarchive(project)"
                    >
                        <ArchiveRestore class="mr-1.5 h-4 w-4" />
                        Herstel
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
