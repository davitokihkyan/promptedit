<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';
import { formatExactUsd, formatUsd } from '@/lib/money';
import type { ChargeEstimate, TokenUsage } from '@/types';

defineProps<{
    estimate: ChargeEstimate | null;
    loading: boolean;
    unavailable: boolean;
}>();

const rows: { label: string; key: keyof TokenUsage; note: string }[] = [
    { label: 'Prompt text', key: 'input_text_tokens', note: 'estimated' },
    { label: 'Reference images', key: 'input_image_tokens', note: 'exact' },
    { label: 'Image output', key: 'output_tokens', note: 'exact' },
    { label: 'Reasoning', key: 'reasoning_tokens', note: 'varies' },
];
</script>

<template>
    <section
        class="space-y-2 rounded-xl border bg-muted/40 p-4"
        aria-live="polite"
    >
        <div class="flex items-baseline justify-between gap-2">
            <span class="flex items-center gap-2 text-sm text-muted-foreground">
                Estimated price
                <Spinner v-if="loading" class="size-3" />
            </span>
            <span
                v-if="estimate"
                class="text-2xl font-semibold tabular-nums"
                :title="formatExactUsd(estimate.expected)"
            >
                {{ formatUsd(estimate.expected) }}
            </span>
        </div>

        <p v-if="unavailable" class="text-xs text-destructive">
            Couldn't load Pixelforge's prices right now.
        </p>

        <template v-if="estimate">
            <p class="text-xs text-muted-foreground">
                At most
                <span class="font-medium text-foreground tabular-nums">{{
                    formatUsd(estimate.maximum)
                }}</span
                >. You pay the actual cost once the image is ready, since the
                model's reasoning varies. Failed generations are free.
            </p>

            <details class="text-xs">
                <summary
                    class="cursor-pointer text-muted-foreground hover:text-foreground"
                >
                    How it's calculated
                </summary>
                <table class="mt-2 w-full tabular-nums">
                    <thead class="text-muted-foreground">
                        <tr>
                            <th class="text-left font-normal">Tokens</th>
                            <th class="text-right font-normal">Expected</th>
                            <th class="text-right font-normal">At most</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.key">
                            <td>
                                {{ row.label }}
                                <span class="text-muted-foreground"
                                    >({{ row.note }})</span
                                >
                            </td>
                            <td class="text-right">
                                {{
                                    estimate.tokens.expected[
                                        row.key
                                    ].toLocaleString()
                                }}
                            </td>
                            <td class="text-right">
                                {{
                                    estimate.tokens.maximum[
                                        row.key
                                    ].toLocaleString()
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-muted-foreground">
                    Priced at Pixelforge's current per-token rates plus our fee.
                </p>
            </details>
        </template>
    </section>
</template>
