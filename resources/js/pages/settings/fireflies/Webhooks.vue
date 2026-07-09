<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import Meta from '@/components/todai/Meta.vue';
import { Badge } from '@/components/ui/badge';
import { edit } from '@/routes/fireflies';

type WebhookEvent = {
    id: string;
    outcome: string;
    outcome_label: string;
    event_type: string | null;
    fireflies_meeting_id: string | null;
    signed: boolean;
    ip: string | null;
    payload: Record<string, unknown> | null;
    user: { name: string; email: string } | null;
    created_at: string | null;
};

defineProps<{ events: WebhookEvent[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fireflies', href: '/settings/fireflies' },
            { title: 'Webhooks', href: '/settings/fireflies/webhooks' },
        ],
    },
});

const badgeVariant = (
    outcome: string,
): 'default' | 'secondary' | 'destructive' => {
    if (outcome === 'accepted') {
        return 'default';
    }

    if (outcome === 'unknown_token' || outcome === 'invalid_signature') {
        return 'destructive';
    }

    return 'secondary';
};

const formatTime = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleString() : '—';
</script>

<template>
    <Head title="Fireflies webhooks" />

    <h1 class="sr-only">Fireflies webhooks</h1>

    <div class="flex flex-col space-y-8">
        <div class="space-y-3">
            <Link
                :href="edit().url"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4" />
                Fireflies settings
            </Link>

            <Heading
                variant="small"
                title="Incoming webhooks"
                description="Every delivery Fireflies has sent, with its outcome. The 200 most recent are shown."
            />
        </div>

        <div
            v-if="events.length === 0"
            class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
        >
            No webhooks received yet.
        </div>

        <ul v-else class="space-y-2">
            <li
                v-for="event in events"
                :key="event.id"
                class="rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="badgeVariant(event.outcome)">
                            {{ event.outcome_label }}
                        </Badge>
                        <span class="text-sm font-medium text-foreground">
                            {{ event.event_type ?? 'no event type' }}
                        </span>
                    </div>
                    <Meta>{{ formatTime(event.created_at) }}</Meta>
                </div>

                <div
                    class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                >
                    <span v-if="event.fireflies_meeting_id">
                        meeting: {{ event.fireflies_meeting_id }}
                    </span>
                    <span>
                        {{ event.user ? event.user.email : 'unattributed' }}
                    </span>
                    <span>{{ event.signed ? 'signed' : 'unsigned' }}</span>
                    <span v-if="event.ip">{{ event.ip }}</span>
                </div>

                <details v-if="event.payload" class="mt-2">
                    <summary
                        class="cursor-pointer text-xs text-muted-foreground hover:text-foreground"
                    >
                        Payload
                    </summary>
                    <pre
                        class="mt-2 overflow-x-auto rounded-lg border border-border bg-muted/40 p-3 text-xs text-foreground"
                        >{{ JSON.stringify(event.payload, null, 2) }}</pre>
                </details>
            </li>
        </ul>
    </div>
</template>
