<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, MoreHorizontal, Pencil } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import Markdown from '@/components/todai/Markdown.vue';
import Meta from '@/components/todai/Meta.vue';
import SuggestionBanner from '@/components/todai/SuggestionBanner.vue';
import TaskActionsMenu from '@/components/todai/TaskActionsMenu.vue';
import TaskForm from '@/components/todai/TaskForm.vue';
import { Button } from '@/components/ui/button';
import { ContextMenu, ContextMenuTrigger } from '@/components/ui/context-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { bucketFor, dueLabel } from '@/lib/dates';
import type { Task } from '@/types';

const props = withDefaults(
    defineProps<{ task: Task; showProject?: boolean }>(),
    { showProject: true },
);

// One dialog, two modes: 'view' shows the task detail, 'edit' the form.
const dialogOpen = ref(false);
const mode = ref<'view' | 'edit'>('view');

const openDetails = () => {
    mode.value = 'view';
    dialogOpen.value = true;
};

const openEdit = () => {
    mode.value = 'edit';
    dialogOpen.value = true;
};

// When linked to directly (e.g. from the "Task added" toast via #task-{id}),
// scroll this item into view and briefly highlight it.
const root = ref<HTMLElement | null>(null);
const highlighted = ref(false);

onMounted(() => {
    if (window.location.hash !== `#task-${props.task.id}`) {
        return;
    }

    root.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlighted.value = true;
    window.setTimeout(() => {
        highlighted.value = false;
    }, 2000);
});

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
</script>

<template>
    <ContextMenu>
        <ContextMenuTrigger as-child>
            <div
                :id="`task-${task.id}`"
                ref="root"
                class="group flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-all hover:border-solar/40"
                :class="
                    highlighted
                        ? 'border-solar ring-2 ring-solar/50 ring-offset-2 ring-offset-background'
                        : ''
                "
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
                    <button
                        type="button"
                        class="block w-full text-left text-sm leading-snug break-words hover:underline focus-visible:underline focus-visible:outline-none"
                        :class="
                            task.completed_at
                                ? 'text-muted-foreground line-through'
                                : 'text-foreground'
                        "
                        @click="openDetails"
                    >
                        {{ task.title }}
                    </button>

                    <div
                        class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1"
                    >
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
                                    backgroundColor:
                                        task.project.color ?? '#6B7280',
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
                                task.meeting?.title
                                    ? ` · ${task.meeting.title}`
                                    : ''
                            }}
                        </Meta>
                    </div>

                    <SuggestionBanner
                        v-if="task.suggested_project_id"
                        :task="task"
                    />
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger
                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground opacity-100 transition-opacity focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:opacity-0 sm:group-hover:opacity-100"
                        aria-label="Task actions"
                    >
                        <MoreHorizontal class="h-4 w-4" />
                    </DropdownMenuTrigger>
                    <TaskActionsMenu
                        variant="dropdown"
                        :task="task"
                        @edit="openEdit"
                    />
                </DropdownMenu>
            </div>
        </ContextMenuTrigger>

        <TaskActionsMenu variant="context" :task="task" @edit="openEdit" />
    </ContextMenu>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <template v-if="mode === 'edit'">
                <DialogHeader>
                    <DialogTitle>Edit task</DialogTitle>
                </DialogHeader>
                <TaskForm :task="task" @saved="dialogOpen = false" />
            </template>

            <template v-else>
                <DialogHeader>
                    <DialogTitle class="pr-6">{{ task.title }}</DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        v-if="
                            task.due_date ||
                            (showProject && task.project) ||
                            task.source === 'fireflies'
                        "
                        class="flex flex-wrap items-center gap-x-3 gap-y-1"
                    >
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
                                    backgroundColor:
                                        task.project.color ?? '#6B7280',
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
                                task.meeting?.title
                                    ? ` · ${task.meeting.title}`
                                    : ''
                            }}
                        </Meta>
                    </div>

                    <Markdown
                        v-if="task.description"
                        :content="task.description"
                    />
                    <p v-else class="text-sm text-muted-foreground italic">
                        No description.
                    </p>

                    <div class="flex justify-end">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="mode = 'edit'"
                        >
                            <Pencil class="mr-1.5 h-4 w-4" />
                            Edit
                        </Button>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
