<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, ArrowLeft } from '@lucide/vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Meta from '@/components/todai/Meta.vue';
import ProjectForm from '@/components/todai/ProjectForm.vue';
import { Button } from '@/components/ui/button';
import type { Project } from '@/types';

const props = defineProps<{
    project: Project;
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
            Alle projecten
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
                    <Meta>{{ project.open_tasks_count ?? 0 }} open taken</Meta>
                </div>
            </div>

            <Button variant="outline" size="sm" @click="toggleArchive">
                <component
                    :is="isArchived() ? ArchiveRestore : Archive"
                    class="mr-1.5 h-4 w-4"
                />
                {{ isArchived() ? 'Herstel' : 'Archiveer' }}
            </Button>
        </header>

        <section
            class="rounded-xl border border-border bg-card p-5 shadow-sm"
            aria-label="Project bewerken"
        >
            <h2 class="mb-4 text-sm font-semibold text-foreground">Bewerken</h2>
            <ProjectForm :project="project" submit-label="Opslaan" />
        </section>

        <!-- Taken binnen dit project verschijnen hier vanaf fase 2. -->
    </div>
</template>
