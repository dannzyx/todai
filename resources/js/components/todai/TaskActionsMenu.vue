<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Calendar, FolderInput, Lightbulb, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { todayIso } from '@/lib/dates';
import type { Task } from '@/types';

/**
 * The task action set, shared between the "…" dropdown and the right-click
 * context menu. Both render reka-ui menu primitives with matching APIs, so we
 * pick the component set from `variant` and keep a single source of truth.
 */
const props = defineProps<{
    task: Task;
    variant: 'dropdown' | 'context';
}>();

const emit = defineEmits<{ (e: 'edit'): void }>();

const page = usePage();
const activeProjects = computed(() => page.props.activeProjects ?? []);

const menu = computed(() =>
    props.variant === 'context'
        ? {
              Content: ContextMenuContent,
              Item: ContextMenuItem,
              Separator: ContextMenuSeparator,
              Sub: ContextMenuSub,
              SubTrigger: ContextMenuSubTrigger,
              SubContent: ContextMenuSubContent,
          }
        : {
              Content: DropdownMenuContent,
              Item: DropdownMenuItem,
              Separator: DropdownMenuSeparator,
              Sub: DropdownMenuSub,
              SubTrigger: DropdownMenuSubTrigger,
              SubContent: DropdownMenuSubContent,
          },
);

const contentProps = computed(() =>
    props.variant === 'dropdown' ? { align: 'end' as const } : {},
);

// ContextMenuItem forwards reka-ui's `select` emit; DropdownMenuItem forwards
// props only, so it relies on the native `click`. Bind the right one per variant.
const onSelect = (handler: () => void): Record<string, () => void> => ({
    [props.variant === 'context' ? 'select' : 'click']: handler,
});

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
    <component :is="menu.Content" v-bind="contentProps" class="w-48">
        <component :is="menu.Item" v-on="onSelect(() => setDue(todayIso()))">
            <Calendar class="mr-2 h-4 w-4" /> Today
        </component>
        <component :is="menu.Item" v-on="onSelect(() => setDue(tomorrowIso()))">
            <Calendar class="mr-2 h-4 w-4" /> Tomorrow
        </component>
        <component
            :is="menu.Item"
            v-if="task.due_date"
            v-on="onSelect(() => setDue(null))"
        >
            <Calendar class="mr-2 h-4 w-4" /> No date
        </component>

        <component :is="menu.Separator" />

        <component :is="menu.Sub">
            <component :is="menu.SubTrigger">
                <FolderInput class="mr-2 h-4 w-4" /> Move to
            </component>
            <component :is="menu.SubContent">
                <component
                    :is="menu.Item"
                    v-if="task.project_id"
                    v-on="onSelect(() => moveTo(null))"
                >
                    Inbox
                </component>
                <component
                    :is="menu.Item"
                    v-for="project in activeProjects"
                    :key="project.id"
                    :disabled="project.id === task.project_id"
                    v-on="onSelect(() => moveTo(project.id))"
                >
                    {{ project.name }}
                </component>
            </component>
        </component>

        <component
            :is="menu.Item"
            v-if="!task.project_id"
            v-on="onSelect(() => suggest())"
        >
            <Lightbulb class="mr-2 h-4 w-4" /> Suggest a project
        </component>

        <component :is="menu.Separator" />

        <component :is="menu.Item" v-on="onSelect(() => emit('edit'))">
            <Pencil class="mr-2 h-4 w-4" /> Edit
        </component>
        <component
            :is="menu.Item"
            class="text-destructive focus:text-destructive"
            v-on="onSelect(() => destroy())"
        >
            <Trash2 class="mr-2 h-4 w-4" /> Delete
        </component>
    </component>
</template>
