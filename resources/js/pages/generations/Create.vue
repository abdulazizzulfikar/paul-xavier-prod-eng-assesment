<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatUsd } from '@/lib/money';
import { create, estimate, store } from '@/routes/generations';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const sizes = ['1024x1024', '1536x1024', '1024x1536'];
const qualities = ['low', 'medium', 'high'];

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const cost = ref<{ min: number; max: number } | null>(null);
let debounce: ReturnType<typeof setTimeout>;

// The server computes the estimate, so the price shown and the price stored never disagree.
async function refreshEstimate() {
    const url = estimate.url({
        query: {
            prompt: form.prompt,
            size: form.size,
            quality: form.quality,
            reference_image_count: form.reference_images.length,
        },
    });
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (response.ok) {
        cost.value = await response.json();
    }
}

watch(
    () => [form.prompt, form.size, form.quality, form.reference_images.length],
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(refreshEstimate, 300);
    },
);

onMounted(refreshEstimate);

function addReferenceImage() {
    form.reference_images.push('');
}

function removeReferenceImage(position: number) {
    form.reference_images.splice(position, 1);
}

function referenceImageError(position: number): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `reference_images.${position}`
    ];
}

function submit() {
    form.submit(store());
}
</script>

<template>
    <Head title="Generate" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            title="Generate an image"
            description="Describe what you want, pick a size and quality, and we'll draw it."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="prompt">Prompt</Label>
                <textarea
                    id="prompt"
                    v-model="form.prompt"
                    rows="4"
                    maxlength="4000"
                    required
                    placeholder="A lighthouse on a cliff at dawn, oil painting"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                ></textarea>
                <InputError :message="form.errors.prompt" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Size</Label>
                    <Select v-model="form.size">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="size in sizes"
                                :key="size"
                                :value="size"
                            >
                                {{ size }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.size" />
                </div>

                <div class="grid gap-2">
                    <Label>Quality</Label>
                    <Select v-model="form.quality">
                        <SelectTrigger class="w-full capitalize"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="quality in qualities"
                                :key="quality"
                                :value="quality"
                                class="capitalize"
                            >
                                {{ quality }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.quality" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Reference images (optional, up to 4)</Label>
                <div
                    v-for="(_url, position) in form.reference_images"
                    :key="position"
                    class="grid gap-1"
                >
                    <div class="flex gap-2">
                        <input
                            v-model="form.reference_images[position]"
                            type="url"
                            required
                            placeholder="https://example.com/style.png"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove reference image"
                            @click="removeReferenceImage(position)"
                        >
                            <X />
                        </Button>
                    </div>
                    <InputError :message="referenceImageError(position)" />
                </div>
                <InputError :message="form.errors.reference_images" />
                <Button
                    v-if="form.reference_images.length < 4"
                    type="button"
                    variant="outline"
                    class="w-fit"
                    @click="addReferenceImage"
                >
                    <Plus /> Add reference image
                </Button>
            </div>

            <div class="rounded-lg border bg-muted/40 p-4 text-sm">
                <p class="font-medium">
                    Estimated cost:
                    <span v-if="cost">
                        {{ formatUsd(cost.min) }} – {{ formatUsd(cost.max) }}
                    </span>
                    <span v-else>…</span>
                </p>
                <p class="mt-1 text-muted-foreground">
                    The exact price depends on how long the model thinks, which
                    is only known once the image is done. You pay the actual
                    cost, never more than the top of this range, and nothing if
                    it fails.
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">Generate</Button>
        </form>
    </div>
</template>
