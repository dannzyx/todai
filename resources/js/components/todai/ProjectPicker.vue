<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const INBOX = '__inbox__';

const model = defineModel<string | null>({ default: null });

withDefaults(defineProps<{ id?: string }>(), { id: undefined });

const page = usePage();
const projects = computed(() => page.props.activeProjects ?? []);

// Bridge between the Select's string value and our nullable project_id.
const selected = computed<string>({
    get: () => model.value ?? INBOX,
    set: (value) => {
        model.value = value === INBOX ? null : value;
    },
});
</script>

<template>
    <Select v-model="selected">
        <SelectTrigger :id="id" class="w-full">
            <SelectValue placeholder="Kies een project" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="INBOX">Inbox (geen project)</SelectItem>
            <SelectItem
                v-for="project in projects"
                :key="project.id"
                :value="project.id"
            >
                {{ project.name }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
