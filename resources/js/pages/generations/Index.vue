<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import Heading from '@/components/Heading.vue';
import { isFinished } from '@/lib/generation';
import generationRoutes from '@/routes/generations';
import type { Generation, ImageQuality, ImageSize } from '@/types';

const props = defineProps<{
    generations: Generation[];
    sizes: ImageSize[];
    qualities: ImageQuality[];
    maxPromptLength: number;
    maxReferenceImages: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Studio', href: generationRoutes.index() }],
    },
});

// Poll only while something is still running; credits change when one settles.
const { start, stop } = usePoll(
    2000,
    { only: ['generations', 'credits'] },
    { autoStart: false },
);

const hasUnfinished = computed(() =>
    props.generations.some((generation) => !isFinished(generation.status)),
);

watch(hasUnfinished, (unfinished) => (unfinished ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Studio" />

    <div
        class="grid flex-1 items-start gap-8 p-4 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]"
    >
        <section class="lg:sticky lg:top-4">
            <Heading
                title="New image"
                description="Describe what you want; you'll see the price before you generate."
            />
            <GenerationForm
                :sizes="sizes"
                :qualities="qualities"
                :max-prompt-length="maxPromptLength"
                :max-reference-images="maxReferenceImages"
            />
        </section>

        <section class="@container">
            <Heading
                title="Your generations"
                description="Status updates live. Charges are final once an image is ready."
            />

            <div
                v-if="generations.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground"
            >
                <ImagePlus class="size-6" />
                Nothing yet. Your images will appear here.
            </div>

            <div v-else class="grid gap-4 @lg:grid-cols-2 @4xl:grid-cols-3">
                <GenerationCard
                    v-for="generation in generations"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>
        </section>
    </div>
</template>
