<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    FolderInput,
    Lightbulb,
    MoreHorizontal,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import Meta from '@/components/todai/Meta.vue';
import SuggestionBanner from '@/components/todai/SuggestionBanner.vue';
import TaskForm from '@/components/todai/TaskForm.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { bucketFor, dueLabel, todayIso } from '@/lib/dates';
import type { Task } from '@/types';

const props = withDefaults(
    defineProps<{ task: Task; showProject?: boolean }>(),
    { showProject: true },
);

const page = usePage();
const activeProjects = computed(() => page.props.activeProjects ?? []);

const editing = ref(false);

const bucket = computed(() => bucketFor(props.task));

const dueClass = computed(() => {
    switch (bucket.value) {
        case 'overdue':
            return 'text-destructive';
        case 'today':
            return 'text-solar-strong';
        default:
            return 'text-muted-foreground';
    }
});

const toggle = () => {
    router.patch(
        TaskController.toggle(props.task).url,
        {},
        { preserveScroll: true },
    );
};

const setDue = (due: string | null) => {
    router.patch(
        TaskController.setDueDate(props.task).url,
        { due_date: due },
        { preserveScroll: true },
    );
};

const tomorrowIso = (): string => {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    return date.toISOString().slice(0, 10);
};

const moveTo = (projectId: string | null) => {
    router.patch(
        TaskController.move(props.task).url,
        { project_id: projectId },
        { preserveScroll: true },
    );
};

const suggest = () => {
    router.patch(
        TaskController.suggest(props.task).url,
        {},
        { preserveScroll: true },
    );
};

const destroy = () => {
    router.delete(TaskController.destroy(props.task).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div
        class="group flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:border-solar/40"
    >
        <button
            type="button"
            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                task.completed_at
                    ? 'border-solar bg-solar text-solar-foreground'
                    : 'border-muted-foreground/50 hover:border-solar'
            "
            :aria-pressed="task.completed_at !== null"
            :aria-label="
                task.completed_at ? 'Mark as not done' : 'Mark as done'
            "
            @click="toggle"
        >
            <Check v-if="task.completed_at" class="h-3 w-3" />
        </button>

        <div class="min-w-0 flex-1">
            <p
                class="text-sm leading-snug"
                :class="
                    task.completed_at
                        ? 'text-muted-foreground line-through'
                        : 'text-foreground'
                "
            >
                {{ task.title }}
            </p>

            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <Meta v-if="task.due_date" :class="dueClass">
                    {{ dueLabel(task.due_date) }}
                </Meta>

                <span
                    v-if="showProject && task.project"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :style="{
                            backgroundColor: task.project.color ?? '#6B7280',
                        }"
                        aria-hidden="true"
                    />
                    {{ task.project.name }}
                </span>

                <Meta
                    v-if="task.source === 'fireflies'"
                    class="text-aqua-strong"
                >
                    from meeting{{
                        task.meeting_import?.title
                            ? ` · ${task.meeting_import.title}`
                            : ''
                    }}
                </Meta>
            </div>

            <SuggestionBanner v-if="task.suggested_project_id" :task="task" />
        </div>

        <DropdownMenu>
            <DropdownMenuTrigger
                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Task actions"
            >
                <MoreHorizontal class="h-4 w-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuItem @click="setDue(todayIso())">
                    <Calendar class="mr-2 h-4 w-4" /> Today
                </DropdownMenuItem>
                <DropdownMenuItem @click="setDue(tomorrowIso())">
                    <Calendar class="mr-2 h-4 w-4" /> Tomorrow
                </DropdownMenuItem>
                <DropdownMenuItem v-if="task.due_date" @click="setDue(null)">
                    <Calendar class="mr-2 h-4 w-4" /> No date
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuSub>
                    <DropdownMenuSubTrigger>
                        <FolderInput class="mr-2 h-4 w-4" /> Move to
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                        <DropdownMenuItem
                            v-if="task.project_id"
                            @click="moveTo(null)"
                        >
                            Inbox
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-for="project in activeProjects"
                            :key="project.id"
                            :disabled="project.id === task.project_id"
                            @click="moveTo(project.id)"
                        >
                            {{ project.name }}
                        </DropdownMenuItem>
                    </DropdownMenuSubContent>
                </DropdownMenuSub>

                <DropdownMenuItem v-if="!task.project_id" @click="suggest">
                    <Lightbulb class="mr-2 h-4 w-4" /> Suggest a project
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem @click="editing = true">
                    <Pencil class="mr-2 h-4 w-4" /> Edit
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="text-destructive focus:text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="mr-2 h-4 w-4" /> Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Dialog v-model:open="editing">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit task</DialogTitle>
                </DialogHeader>
                <TaskForm :task="task" @saved="editing = false" />
            </DialogContent>
        </Dialog>
    </div>
</template>
