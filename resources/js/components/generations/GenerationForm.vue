<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import BuyCreditsDialog from '@/components/credits/BuyCreditsDialog.vue';
import PriceEstimate from '@/components/generations/PriceEstimate.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCostEstimate } from '@/composables/useCostEstimate';
import { qualityLabels, sizeLabels } from '@/lib/generation';
import { formatUsd, toNanos } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { GenerationRequest, ImageQuality, ImageSize } from '@/types';

defineProps<{ sizes: ImageSize[]; qualities: ImageQuality[] }>();

const MAX_PROMPT_LENGTH = 4000;
const MAX_REFERENCE_IMAGES = 4;
const REFERENCE_PLACEHOLDER = 'https://example.com/style.png';

const form = useForm<GenerationRequest>({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [],
});

// Characters, not UTF-16 units, matching how Pixelforge counts.
const promptLength = computed(() => [...form.prompt].length);

const request = computed<GenerationRequest>(() => ({
    prompt: form.prompt,
    size: form.size,
    quality: form.quality,
    reference_images: filledReferences(),
}));

const { estimate, unavailable, loading } = useCostEstimate(request);

const page = usePage();
const balance = computed(() => page.props.credits?.balance ?? '0');

// Generating holds the maximum price, so that's what the balance must cover.
const canAfford = computed(
    () =>
        estimate.value === null ||
        toNanos(estimate.value.maximum) <= toNanos(balance.value),
);

function filledReferences(): string[] {
    return form.reference_images
        .map((url) => url.trim())
        .filter((url) => url !== '');
}

// Errors for fields outside the form's own shape, e.g. "reference_images.2" or "credits".
function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field];
}

function addReference(): void {
    form.reference_images.push('');
}

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
}

function submit(): void {
    // Cmd/Ctrl+Enter lands here too, so repeat the button's guards.
    if (form.processing || !canAfford.value) {
        return;
    }

    form.reference_images = filledReferences();
    form.submit(GenerationController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
}

function optionClasses(selected: boolean): string {
    return cn(
        'flex-1 rounded-md border px-3 py-2 text-left text-sm transition-colors',
        selected
            ? 'border-primary bg-primary text-primary-foreground'
            : 'hover:bg-accent',
    );
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="prompt">Prompt</Label>
                <span
                    class="text-xs tabular-nums"
                    :class="
                        promptLength > MAX_PROMPT_LENGTH
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    {{ promptLength.toLocaleString() }} /
                    {{ MAX_PROMPT_LENGTH.toLocaleString() }}
                </span>
            </div>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="5"
                required
                placeholder="A lighthouse on a cliff at dawn, oil painting"
                :aria-invalid="!!form.errors.prompt"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                @keydown.meta.enter="submit"
                @keydown.ctrl.enter="submit"
            ></textarea>
            <InputError :message="form.errors.prompt" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Size</legend>
            <div class="flex gap-2">
                <button
                    v-for="size in sizes"
                    :key="size"
                    type="button"
                    :aria-pressed="form.size === size"
                    :class="optionClasses(form.size === size)"
                    @click="form.size = size"
                >
                    <span class="block font-medium">{{
                        sizeLabels[size]
                    }}</span>
                    <span class="block text-xs opacity-75">{{
                        size.replace('x', '×')
                    }}</span>
                </button>
            </div>
            <InputError :message="form.errors.size" />
        </fieldset>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Quality</legend>
            <div class="flex gap-2">
                <button
                    v-for="quality in qualities"
                    :key="quality"
                    type="button"
                    :aria-pressed="form.quality === quality"
                    :class="optionClasses(form.quality === quality)"
                    @click="form.quality = quality"
                >
                    {{ qualityLabels[quality] }}
                </button>
            </div>
            <InputError :message="form.errors.quality" />
        </fieldset>

        <fieldset class="grid gap-2">
            <legend class="text-sm font-medium">
                Reference images
                <span class="font-normal text-muted-foreground"
                    >(optional)</span
                >
            </legend>
            <p class="text-xs text-muted-foreground">
                Image URLs that guide the style. Each one adds 765 input tokens.
            </p>
            <div
                v-for="(url, index) in form.reference_images"
                :key="index"
                class="grid gap-1"
            >
                <div class="flex gap-2">
                    <Input
                        v-model="form.reference_images[index]"
                        :placeholder="REFERENCE_PLACEHOLDER"
                        :aria-label="`Reference image ${index + 1}`"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Remove reference image ${index + 1}`"
                        @click="removeReference(index)"
                    >
                        <X />
                    </Button>
                </div>
                <InputError :message="errorFor(`reference_images.${index}`)" />
            </div>
            <Button
                v-if="form.reference_images.length < MAX_REFERENCE_IMAGES"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="addReference"
            >
                <Plus /> Add reference image
            </Button>
            <InputError :message="form.errors.reference_images" />
        </fieldset>

        <PriceEstimate
            :estimate="estimate"
            :loading="loading"
            :unavailable="unavailable"
        />

        <div
            v-if="!canAfford && estimate"
            class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <p>
                Not enough credits. This can cost up to
                <strong>{{ formatUsd(estimate.maximum) }}</strong> and you have
                <strong>{{ formatUsd(balance) }}</strong> available.
            </p>
            <BuyCreditsDialog>
                <Button type="button" size="sm" variant="outline">
                    Buy credits
                </Button>
            </BuyCreditsDialog>
        </div>
        <InputError :message="errorFor('credits')" />
        <InputError :message="errorFor('pixelforge')" />

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing || !canAfford"
        >
            <Spinner v-if="form.processing" />
            Generate
        </Button>
    </form>
</template>
