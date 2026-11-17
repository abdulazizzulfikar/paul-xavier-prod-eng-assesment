<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatUsd } from '@/lib/money';
import { create, index } from '@/routes/generations';

type Status = 'queued' | 'processing' | 'succeeded' | 'failed';

type Generation = {
    id: string;
    status: Status;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_min_nano: number;
    estimated_max_nano: number;
    charged_nano: number | null;
    image_url: string | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
};

const props = defineProps<{
    generations: {
        data: Generation[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: index() }],
    },
});

const statusLabels: Record<Status, string> = {
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const statusVariants: Record<
    Status,
    'secondary' | 'outline' | 'default' | 'destructive'
> = {
    queued: 'outline',
    processing: 'secondary',
    succeeded: 'default',
    failed: 'destructive',
};

// Poll only while something is still running.
const hasPending = computed(() =>
    props.generations.data.some(
        (g) => g.status === 'queued' || g.status === 'processing',
    ),
);

const { start, stop } = usePoll(
    3000,
    { only: ['generations'] },
    { autoStart: false },
);

watchEffect(() => (hasPending.value ? start() : stop()));
</script>

<template>
    <Head title="History" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Your generations"
                description="Status updates live while images are being made."
            />
            <Button as-child>
                <Link :href="create()">New generation</Link>
            </Button>
        </div>

        <p
            v-if="generations.data.length === 0"
            class="text-sm text-muted-foreground"
        >
            Nothing yet.
            <Link :href="create()" class="underline"
                >Generate your first image.</Link
            >
        </p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="generation in generations.data"
                :key="generation.id"
                class="flex flex-col overflow-hidden rounded-xl border"
            >
                <div
                    class="flex aspect-video items-center justify-center bg-muted"
                >
                    <a
                        v-if="generation.image_url"
                        :href="generation.image_url"
                        target="_blank"
                        class="h-full w-full"
                    >
                        <img
                            :src="generation.image_url"
                            :alt="generation.prompt"
                            class="h-full w-full object-contain"
                        />
                    </a>
                    <span
                        v-else-if="generation.status === 'failed'"
                        class="px-4 text-center text-sm text-red-600 dark:text-red-400"
                    >
                        {{ generation.error_message || 'Generation failed.' }}
                    </span>
                    <span
                        v-else
                        class="animate-pulse text-sm text-muted-foreground"
                    >
                        {{
                            generation.status === 'queued'
                                ? 'Waiting in line…'
                                : 'Drawing…'
                        }}
                    </span>
                </div>

                <div class="flex flex-1 flex-col gap-3 p-4 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <Badge :variant="statusVariants[generation.status]">
                            {{ statusLabels[generation.status] }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            {{ generation.size }} ·
                            <span class="capitalize">{{
                                generation.quality
                            }}</span>
                            <template v-if="generation.reference_images.length">
                                · {{ generation.reference_images.length }} ref
                            </template>
                        </span>
                    </div>

                    <p class="line-clamp-3">{{ generation.prompt }}</p>

                    <dl
                        class="mt-auto grid grid-cols-2 gap-2 border-t pt-3 text-xs"
                    >
                        <div>
                            <dt class="text-muted-foreground">Estimated</dt>
                            <dd>
                                {{ formatUsd(generation.estimated_min_nano) }} –
                                {{ formatUsd(generation.estimated_max_nano) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Charged</dt>
                            <dd class="font-medium">
                                <template
                                    v-if="generation.charged_nano !== null"
                                >
                                    {{ formatUsd(generation.charged_nano) }}
                                    <span
                                        v-if="generation.status === 'failed'"
                                        class="font-normal text-muted-foreground"
                                    >
                                        (failed, not charged)
                                    </span>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >Pending</span
                                >
                            </dd>
                        </div>
                    </dl>
                </div>
            </article>
        </div>

        <div
            v-if="generations.prev_page_url || generations.next_page_url"
            class="flex justify-between"
        >
            <Button
                variant="outline"
                :disabled="!generations.prev_page_url"
                as-child
            >
                <Link :href="generations.prev_page_url ?? ''">Previous</Link>
            </Button>
            <Button
                variant="outline"
                :disabled="!generations.next_page_url"
                as-child
            >
                <Link :href="generations.next_page_url ?? ''">Next</Link>
            </Button>
        </div>
    </div>
</template>
