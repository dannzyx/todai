<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Radio } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Meta from '@/components/todai/Meta.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, rotate, update, webhooks } from '@/routes/fireflies';

const props = defineProps<{
    connected: boolean;
    firefliesEmail: string | null;
    hasSecret: boolean;
    webhookUrl: string | null;
    isAdmin: boolean;
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

const copyToClipboard = async (text: string): Promise<boolean> => {
    // The async Clipboard API is only available in secure contexts (HTTPS or
    // localhost). Over plain HTTP (e.g. herd's *.test) it's undefined, so fall
    // back to a temporary textarea + execCommand.
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall through to the legacy path.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    let succeeded = false;

    try {
        succeeded = document.execCommand('copy');
    } catch {
        succeeded = false;
    }

    document.body.removeChild(textarea);

    return succeeded;
};

const copyWebhook = async () => {
    if (!props.webhookUrl) {
        return;
    }

    if (!(await copyToClipboard(props.webhookUrl))) {
        return;
    }

    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1500);
};

const rotateToken = () =>
    router.patch(rotate().url, {}, { preserveScroll: true });

const disconnect = () => router.delete(destroy().url, { preserveScroll: true });
</script>

<template>
    <Head title="Fireflies" />

    <h1 class="sr-only">Fireflies settings</h1>

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Fireflies"
            description="Connect your own Fireflies account. Todai turns completed meetings into tasks."
        />

        <div
            v-if="connected"
            class="flex items-center gap-2 rounded-lg border border-aqua/40 bg-aqua-surface px-4 py-3"
        >
            <Check class="h-4 w-4 text-aqua-strong" />
            <p class="text-sm text-foreground">
                Connected<span v-if="firefliesEmail">
                    as {{ firefliesEmail }}</span
                >.
            </p>
        </div>

        <!-- Personal webhook URL -->
        <section v-if="connected && webhookUrl" class="space-y-2">
            <Label>Your webhook URL</Label>
            <div class="flex items-center gap-2">
                <Input
                    :model-value="webhookUrl"
                    readonly
                    class="font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="copyWebhook"
                >
                    <component
                        :is="copied ? Check : Copy"
                        class="mr-1.5 h-4 w-4"
                    />
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>
            </div>
            <Meta>
                Paste this URL into Fireflies under app.fireflies.ai/settings →
                Developer settings.
            </Meta>
        </section>

        <!-- Connect / update key -->
        <form class="space-y-4" @submit.prevent="save">
            <div class="grid gap-2">
                <Label for="api_key">Fireflies API key</Label>
                <Input
                    id="api_key"
                    v-model="form.api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        connected
                            ? 'Enter a new key to change it'
                            : 'Paste your API key'
                    "
                />
                <InputError :message="form.errors.api_key" />
                <Meta> Found in Fireflies under Developer settings. </Meta>
            </div>

            <div class="grid gap-2">
                <Label for="webhook_secret">Webhook secret (optional)</Label>
                <Input
                    id="webhook_secret"
                    v-model="form.webhook_secret"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        hasSecret ? 'Set' : 'Same value as in Fireflies'
                    "
                />
                <InputError :message="form.errors.webhook_secret" />
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ connected ? 'Update' : 'Connect' }}
            </Button>
        </form>

        <!-- Incoming webhook history (admin only) -->
        <Link
            v-if="isAdmin"
            :href="webhooks().url"
            class="flex items-center gap-2 rounded-lg border border-border px-4 py-3 text-sm text-foreground transition-colors hover:border-solar/50"
        >
            <Radio class="h-4 w-4 text-muted-foreground" />
            View incoming webhooks
        </Link>

        <!-- Management -->
        <div
            v-if="connected"
            class="flex flex-wrap gap-3 border-t border-border pt-6"
        >
            <Button variant="outline" size="sm" @click="rotateToken">
                Rotate webhook URL
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="disconnect"
            >
                Disconnect
            </Button>
        </div>
    </div>
</template>
