<script setup lang="ts">
import TaskForm from '@/components/todai/TaskForm.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTaskModal } from '@/composables/useTaskModal';

/**
 * The global "new task" modal. Its open state and default project come from the
 * useTaskModal() singleton, so any trigger (plus button, command palette) shares
 * one instance.
 */
const { open, defaultProjectId, closeTaskModal } = useTaskModal();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>New task</DialogTitle>
            </DialogHeader>
            <TaskForm
                :default-project-id="defaultProjectId"
                @saved="closeTaskModal"
            />
        </DialogContent>
    </Dialog>
</template>
