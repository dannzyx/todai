<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    FolderKanban,
    Inbox,
    ListChecks,
    MessageSquare,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useTaskModal } from '@/composables/useTaskModal';

const open = defineModel<boolean>('open', { default: false });

const { openTaskModal } = useTaskModal();

type Command = {
    id: string;
    label: string;
    hint?: string;
    icon: unknown;
    url?: string;
    action?: () => void;
};

const page = usePage();

// When viewing a project (projects/Show sets a `project` prop), a new task
// should default to that project; otherwise it lands in the inbox.
const currentProjectId = computed<string | null>(() => {
    const project = page.props.project as { id?: string } | undefined;

    return project?.id ?? null;
});

const search = ref('');
const activeIndex = ref(0);
const inputEl = ref<HTMLInputElement | null>(null);

const baseCommands = computed<Command[]>(() => [
    {
        id: 'new-task',
        label: 'New task',
        hint: 'Add',
        icon: Plus,
        action: () => openTaskModal(currentProjectId.value),
    },
    { id: 'vandaag', label: 'Today', icon: CalendarDays, url: '/' },
    { id: 'inbox', label: 'Inbox', icon: Inbox, url: '/inbox' },
    {
        id: 'tasks',
        label: 'All tasks',
        icon: ListChecks,
        url: TaskController.index().url,
    },
    {
        id: 'projecten',
        label: 'Projects',
        icon: FolderKanban,
        url: ProjectController.index().url,
    },
    { id: 'chat', label: 'Chat', icon: MessageSquare, url: '/chat' },
    ...(page.props.activeProjects ?? []).map((project) => ({
        id: `project-${project.id}`,
        label: project.name,
        hint: 'Project',
        icon: FolderKanban,
        url: ProjectController.show(project.id).url,
    })),
]);

const results = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return baseCommands.value;
    }

    return baseCommands.value.filter((command) =>
        command.label.toLowerCase().includes(term),
    );
});

watch(results, () => {
    activeIndex.value = 0;
});

watch(open, async (isOpen) => {
    if (isOpen) {
        search.value = '';
        activeIndex.value = 0;
        await nextTick();
        inputEl.value?.focus();
    }
});

const select = (command: Command | undefined) => {
    if (!command) {
        return;
    }

    open.value = false;

    if (command.action) {
        command.action();

        return;
    }

    if (command.url) {
        router.visit(command.url);
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + results.value.length) %
            results.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        select(results.value[activeIndex.value]);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="gap-0 p-0 sm:max-w-lg" :show-close-button="false">
            <div class="flex items-center gap-2 border-b border-border px-4">
                <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="search"
                    type="text"
                    placeholder="Search or jump to..."
                    aria-label="Command palette"
                    class="w-full bg-transparent py-3.5 text-sm placeholder:text-muted-foreground focus:outline-none"
                    @keydown="onKeydown"
                />
            </div>

            <ul class="max-h-80 overflow-y-auto p-2">
                <li v-for="(command, index) in results" :key="command.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors"
                        :class="
                            index === activeIndex
                                ? 'bg-accent text-foreground'
                                : 'text-muted-foreground hover:bg-accent/60'
                        "
                        @click="select(command)"
                        @mouseenter="activeIndex = index"
                    >
                        <component
                            :is="command.icon"
                            class="h-4 w-4 shrink-0"
                        />
                        <span class="flex-1 truncate text-foreground">{{
                            command.label
                        }}</span>
                        <span
                            v-if="command.hint"
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ command.hint }}</span
                        >
                    </button>
                </li>
                <li
                    v-if="results.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Nothing found.
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
