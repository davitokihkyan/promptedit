<script setup lang="ts">
import { ImageOff, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import { Spinner } from '@/components/ui/spinner';
import { qualityLabels, sizeLabels } from '@/lib/generation';
import { formatUsd } from '@/lib/money';
import type { Generation, GenerationStatus, ImageSize } from '@/types';

const props = defineProps<{ generation: Generation }>();

const aspectClasses: Record<ImageSize, string> = {
    '1024x1024': 'aspect-square',
    '1536x1024': 'aspect-[3/2]',
    '1024x1536': 'aspect-[2/3]',
};

const progressText: Partial<Record<GenerationStatus, string>> = {
    pending: 'Waiting for Pixelforge…',
    queued: "In Pixelforge's queue…",
    processing: 'Generating, usually 5 to 30 seconds…',
};

const createdAt = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);

const referenceCount = computed(() => props.generation.reference_images.length);
</script>

<template>
    <article class="overflow-hidden rounded-xl border bg-card">
        <div
            class="relative flex items-center justify-center bg-muted"
            :class="aspectClasses[generation.size]"
        >
            <a
                v-if="generation.image_url"
                :href="generation.image_url"
                target="_blank"
                rel="noopener"
                class="size-full"
            >
                <img
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    loading="lazy"
                    class="size-full object-cover"
                />
            </a>

            <div
                v-else-if="generation.image_expired"
                class="flex flex-col items-center gap-1 p-4 text-center text-sm text-muted-foreground"
            >
                <ImageOff class="size-5" />
                Image expired
                <span class="text-xs">Pixelforge links last 24 hours.</span>
            </div>

            <div
                v-else-if="generation.status === 'failed'"
                class="flex flex-col items-center gap-1 p-4 text-center text-sm"
            >
                <TriangleAlert class="size-5 text-destructive" />
                {{ generation.failure }}
                <span class="text-xs text-muted-foreground">
                    You weren't charged.
                </span>
            </div>

            <div
                v-else
                class="flex flex-col items-center gap-2 p-4 text-center text-sm text-muted-foreground"
            >
                <Spinner class="size-5" />
                {{ progressText[generation.status] }}
            </div>

            <GenerationStatusBadge
                :status="generation.status"
                class="absolute top-2 left-2 shadow-sm"
            />
        </div>

        <div class="space-y-2 p-3">
            <p class="line-clamp-2 text-sm" :title="generation.prompt">
                {{ generation.prompt }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ sizeLabels[generation.size] }} ·
                {{ qualityLabels[generation.quality] }} quality
                <template v-if="referenceCount">
                    · {{ referenceCount }} reference{{
                        referenceCount === 1 ? '' : 's'
                    }}
                </template>
                · {{ createdAt }}
            </p>

            <dl class="grid grid-cols-2 gap-y-0.5 text-xs">
                <dt class="text-muted-foreground">Estimated</dt>
                <dd
                    class="text-right tabular-nums"
                    :title="`Up to ${formatUsd(generation.max_charge)}`"
                >
                    {{ formatUsd(generation.estimated_charge) }}
                </dd>

                <dt class="text-muted-foreground">Charged</dt>
                <dd
                    v-if="generation.status === 'failed'"
                    class="text-right text-muted-foreground"
                >
                    Nothing
                </dd>
                <dd
                    v-else-if="generation.charge !== null"
                    class="text-right font-medium tabular-nums"
                    :title="`Exactly $${generation.charge}`"
                >
                    {{ formatUsd(generation.charge) }}
                </dd>
                <dd v-else class="text-right text-muted-foreground">
                    When it finishes
                </dd>
            </dl>
        </div>
    </article>
</template>
