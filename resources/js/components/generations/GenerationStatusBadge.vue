<script setup lang="ts">
import {
    CircleCheck,
    CircleX,
    Clock,
    Hourglass,
    LoaderCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { GenerationStatus } from '@/types';

const props = defineProps<{ status: GenerationStatus }>();

type Appearance = {
    label: string;
    hint: string;
    icon: Component;
    classes: string;
    spin?: boolean;
};

const appearances: Record<GenerationStatus, Appearance> = {
    pending: {
        label: 'Waiting',
        hint: 'Not sent yet: waiting for a free slot at Pixelforge, or retrying after it was busy.',
        icon: Hourglass,
        classes: 'bg-muted text-muted-foreground',
    },
    queued: {
        label: 'Queued',
        hint: 'Accepted by Pixelforge and waiting in its queue.',
        icon: Clock,
        classes:
            'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    },
    processing: {
        label: 'Processing',
        hint: 'Pixelforge is generating the image.',
        icon: LoaderCircle,
        classes: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
        spin: true,
    },
    succeeded: {
        label: 'Succeeded',
        hint: 'Finished and charged at its actual cost.',
        icon: CircleCheck,
        classes:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    failed: {
        label: 'Failed',
        hint: "Didn't produce an image. You weren't charged.",
        icon: CircleX,
        classes: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
    },
};

const appearance = computed(() => appearances[props.status]);
</script>

<template>
    <span
        :title="appearance.hint"
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                appearance.classes,
            )
        "
    >
        <component
            :is="appearance.icon"
            class="size-3"
            :class="{ 'animate-spin': appearance.spin }"
        />
        {{ appearance.label }}
    </span>
</template>
