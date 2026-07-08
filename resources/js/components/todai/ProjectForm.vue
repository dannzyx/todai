<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Project } from '@/types';

const props = withDefaults(
    defineProps<{
        project?: Project | null;
        submitLabel?: string;
    }>(),
    { project: null, submitLabel: 'Toevoegen' },
);

const swatches = ['#F2A93B', '#22A9B8', '#D64545', '#6B7280', '#7C9A3B'];

const form = useForm({
    name: props.project?.name ?? '',
    description: props.project?.description ?? '',
    color: props.project?.color ?? null,
});

const submit = () => {
    if (props.project) {
        form.put(ProjectController.update(props.project).url, {
            preserveScroll: true,
        });

        return;
    }

    form.post(ProjectController.store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-1.5">
            <Label for="project-name">Naam</Label>
            <Input
                id="project-name"
                v-model="form.name"
                type="text"
                placeholder="Bijv. Website herontwerp"
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-1.5">
            <Label for="project-description">Omschrijving (optioneel)</Label>
            <textarea
                id="project-description"
                v-model="form.description"
                rows="2"
                placeholder="Waar gaat dit project over?"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="space-y-1.5">
            <Label>Kleur</Label>
            <div class="flex items-center gap-2">
                <button
                    v-for="swatch in swatches"
                    :key="swatch"
                    type="button"
                    class="h-6 w-6 rounded-full border transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        form.color === swatch
                            ? 'ring-2 ring-ring ring-offset-2 ring-offset-background'
                            : 'border-border'
                    "
                    :style="{ backgroundColor: swatch }"
                    :aria-label="`Kleur ${swatch}`"
                    :aria-pressed="form.color === swatch"
                    @click="form.color = form.color === swatch ? null : swatch"
                />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
