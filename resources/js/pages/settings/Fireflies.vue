<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Meta from '@/components/todai/Meta.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, rotate, update } from '@/routes/fireflies';

const props = defineProps<{
    connected: boolean;
    firefliesEmail: string | null;
    hasSecret: boolean;
    webhookUrl: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Fireflies', href: '/settings/fireflies' }],
    },
});

const form = useForm<{ api_key: string; webhook_secret: string }>({
    api_key: '',
    webhook_secret: '',
});

const copied = ref(false);

const save = () => {
    form.put(update().url, { preserveScroll: true });
};

const copyWebhook = async () => {
    if (!props.webhookUrl) {
        return;
    }

    await navigator.clipboard.writeText(props.webhookUrl);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1500);
};

const rotateToken = () => router.patch(rotate().url, {}, { preserveScroll: true });

const disconnect = () => router.delete(destroy().url, { preserveScroll: true });
</script>

<template>
    <Head title="Fireflies" />

    <h1 class="sr-only">Fireflies-instellingen</h1>

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Fireflies"
            description="Koppel je eigen Fireflies-account. Todai zet afgeronde meetings om in taken."
        />

        <div
            v-if="connected"
            class="flex items-center gap-2 rounded-lg border border-aqua/40 bg-aqua-surface px-4 py-3"
        >
            <Check class="h-4 w-4 text-aqua-strong" />
            <p class="text-sm text-foreground">
                Verbonden<span v-if="firefliesEmail"> als {{ firefliesEmail }}</span
                >.
            </p>
        </div>

        <!-- Personal webhook URL -->
        <section v-if="connected && webhookUrl" class="space-y-2">
            <Label>Jouw webhook-URL</Label>
            <div class="flex items-center gap-2">
                <Input :model-value="webhookUrl" readonly class="font-mono text-xs" />
                <Button type="button" variant="outline" size="sm" @click="copyWebhook">
                    <component :is="copied ? Check : Copy" class="mr-1.5 h-4 w-4" />
                    {{ copied ? 'Gekopieerd' : 'Kopieer' }}
                </Button>
            </div>
            <Meta>
                Plak deze URL in Fireflies onder app.fireflies.ai/settings →
                Developer settings.
            </Meta>
        </section>

        <!-- Connect / update key -->
        <form class="space-y-4" @submit.prevent="save">
            <div class="grid gap-2">
                <Label for="api_key">Fireflies API-sleutel</Label>
                <Input
                    id="api_key"
                    v-model="form.api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="connected ? 'Voer een nieuwe sleutel in om te wijzigen' : 'Plak je API-sleutel'"
                />
                <InputError :message="form.errors.api_key" />
                <Meta>
                    Te vinden in Fireflies onder Developer settings.
                </Meta>
            </div>

            <div class="grid gap-2">
                <Label for="webhook_secret">Webhook-secret (optioneel)</Label>
                <Input
                    id="webhook_secret"
                    v-model="form.webhook_secret"
                    type="password"
                    autocomplete="off"
                    :placeholder="hasSecret ? 'Ingesteld' : 'Zelfde waarde als in Fireflies'"
                />
                <InputError :message="form.errors.webhook_secret" />
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ connected ? 'Bijwerken' : 'Koppelen' }}
            </Button>
        </form>

        <!-- Management -->
        <div v-if="connected" class="flex flex-wrap gap-3 border-t border-border pt-6">
            <Button variant="outline" size="sm" @click="rotateToken">
                Roteer webhook-URL
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="disconnect"
            >
                Ontkoppelen
            </Button>
        </div>
    </div>
</template>
