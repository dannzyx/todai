<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceToggle from '@/components/todai/AppearanceToggle.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

type RailItem = {
    label: string;
    href: string;
    match: string;
};

/**
 * The slim top-rail destinations. Deliberately few — everything else lives
 * behind the command palette. No fixed left sidebar.
 */
const items: RailItem[] = [
    { label: 'Today', href: '/', match: '/' },
    { label: 'Inbox', href: '/inbox', match: '/inbox' },
    { label: 'Projects', href: '/projecten', match: '/projecten' },
    { label: 'Chat', href: '/chat', match: '/chat' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

const currentPath = computed(() => {
    try {
        return new URL(page.url, 'http://localhost').pathname;
    } catch {
        return page.url;
    }
});

const isActive = (item: RailItem): boolean => {
    if (item.match === '/') {
        return currentPath.value === '/';
    }

    return currentPath.value.startsWith(item.match);
};

const emit = defineEmits<{ (e: 'open-command'): void }>();
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur"
    >
        <div
            class="mx-auto flex h-14 max-w-3xl items-center gap-6 px-4 sm:px-6"
        >
            <Link
                href="/"
                class="font-display text-xl font-semibold tracking-tight text-foreground"
            >
                todai<span class="text-solar">.</span>
            </Link>

            <nav
                class="hidden items-center gap-1 sm:flex"
                aria-label="Main navigation"
            >
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        isActive(item)
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-1.5">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md border border-border/70 px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Open command palette"
                    @click="emit('open-command')"
                >
                    <span class="hidden sm:inline">Search or jump to</span>
                    <kbd
                        class="font-mono text-[10px] tracking-tight text-muted-foreground"
                        >⌘K</kbd
                    >
                </button>

                <AppearanceToggle />

                <DropdownMenu>
                    <DropdownMenuTrigger
                        class="flex items-center rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Account menu"
                    >
                        <UserInfo
                            :user="user"
                            :show-name="false"
                            class="rounded-full"
                        />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56" align="end">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Mobile nav: the destinations collapse under the rail. -->
        <nav
            class="flex items-center gap-1 overflow-x-auto border-t border-border/60 px-4 py-2 sm:hidden"
            aria-label="Main navigation (mobile)"
        >
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                :class="[
                    'rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors',
                    isActive(item)
                        ? 'bg-accent text-foreground'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                {{ item.label }}
            </Link>
        </nav>
    </header>
</template>
