<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import BuyCreditsDialog from '@/components/credits/BuyCreditsDialog.vue';
import OptionPicker from '@/components/generations/OptionPicker.vue';
import PriceEstimate from '@/components/generations/PriceEstimate.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useChargeEstimate } from '@/composables/useChargeEstimate';
import { qualityLabels, sizeLabels } from '@/lib/generation';
import { formatUsd, toNanos } from '@/lib/money';
import type { GenerationRequest, ImageQuality, ImageSize } from '@/types';

const props = defineProps<{
    sizes: ImageSize[];
    qualities: ImageQuality[];
    maxPromptLength: number;
    maxReferenceImages: number;
}>();

const REFERENCE_PLACEHOLDER = 'https://example.com/style.png';

const sizeOptions = computed(() =>
    props.sizes.map((size) => ({
        value: size,
        label: sizeLabels[size],
        hint: size.replace('x', '×'),
    })),
);

const qualityOptions = computed(() =>
    props.qualities.map((quality) => ({
        value: quality,
        label: qualityLabels[quality],
    })),
);

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

const { estimate, unavailable, loading } = useChargeEstimate(request);

const page = usePage();
const balance = computed(() => page.props.credits?.balance ?? '0.000000000');

// Generating holds the maximum price, so that's what the balance must cover.
// Without an estimate yet, the server makes the call.
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

    // Drop empty rows first, so "reference_images.N" errors point at the rows the user sees.
    form.reference_images = filledReferences();
    form.submit(GenerationController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
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
                        promptLength > maxPromptLength
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    {{ promptLength.toLocaleString() }} /
                    {{ maxPromptLength.toLocaleString() }}
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

        <OptionPicker
            v-model="form.size"
            legend="Size"
            :options="sizeOptions"
            :error="form.errors.size"
        />

        <OptionPicker
            v-model="form.quality"
            legend="Quality"
            :options="qualityOptions"
            :error="form.errors.quality"
        />

        <fieldset class="grid gap-2">
            <legend class="text-sm font-medium">
                Reference images
                <span class="font-normal text-muted-foreground"
                    >(optional)</span
                >
            </legend>
            <p class="text-xs text-muted-foreground">
                Image URLs that guide the style. Each one adds to the price.
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
                v-if="form.reference_images.length < maxReferenceImages"
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
