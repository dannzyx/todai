<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import Meta from '@/components/todai/Meta.vue';
import VoiceCard from '@/components/todai/VoiceCard.vue';
import { Button } from '@/components/ui/button';
import type { SuggestionConfidence, Task } from '@/types';

const props = defineProps<{ task: Task }>();

const confidenceLabel: Record<SuggestionConfidence, string> = {
    low: 'low confidence',
    medium: 'medium confidence',
    high: 'high confidence',
};

const projectName = computed(() => props.task.suggested_project?.name ?? '');

const accept = () => {
    router.patch(
        TaskController.acceptSuggestion(props.task).url,
        {},
        { preserveScroll: true },
    );
};

const dismiss = () => {
    router.patch(
        TaskController.dismissSuggestion(props.task).url,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <VoiceCard voice="ai" reveal class="mt-2 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-sm text-foreground">
                    Todai suggests:
                    <span class="font-medium text-aqua-strong">{{
                        projectName
                    }}</span>
                </p>
                <Meta v-if="task.suggestion_confidence" class="mt-0.5 block">
                    {{ confidenceLabel[task.suggestion_confidence] }}
                    <template v-if="task.suggestion_reasoning">
                        · {{ task.suggestion_reasoning }}
                    </template>
                </Meta>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button size="sm" variant="ghost" @click="dismiss">
                    Dismiss
                </Button>
                <Button
                    size="sm"
                    class="bg-aqua text-aqua-foreground hover:bg-aqua/90"
                    @click="accept"
                >
                    Assign
                </Button>
            </div>
        </div>
    </VoiceCard>
</template>
