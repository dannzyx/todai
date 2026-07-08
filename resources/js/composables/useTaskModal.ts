import { ref } from 'vue';

/**
 * Shared state for the global "new task" modal. Rendered once in TodaiLayout and
 * opened from anywhere (the project-page plus button, the Cmd/Ctrl+K palette).
 *
 * Module-level refs make this a singleton across every import.
 */
const open = ref(false);
const defaultProjectId = ref<string | null>(null);

export function useTaskModal() {
    const openTaskModal = (projectId: string | null = null): void => {
        defaultProjectId.value = projectId;
        open.value = true;
    };

    const closeTaskModal = (): void => {
        open.value = false;
    };

    return { open, defaultProjectId, openTaskModal, closeTaskModal };
}
