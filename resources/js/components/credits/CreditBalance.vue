<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { computed } from 'vue';
import BuyCreditsDialog from '@/components/credits/BuyCreditsDialog.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, toNanos } from '@/lib/money';

const page = usePage();
const credits = computed(() => page.props.credits);
const hasReserved = computed(
    () => credits.value !== null && toNanos(credits.value.reserved) > 0n,
);
</script>

<template>
    <div
        v-if="credits"
        class="mx-2 space-y-2 rounded-lg border bg-background/60 p-3 group-data-[collapsible=icon]:hidden"
    >
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Wallet class="size-3.5" /> Credits
        </div>
        <p
            class="text-lg font-semibold tabular-nums"
            :title="`Exactly $${credits.balance}`"
        >
            {{ formatUsd(credits.balance) }}
        </p>
        <p v-if="hasReserved" class="text-xs text-muted-foreground">
            {{ formatUsd(credits.reserved) }} reserved for running generations
        </p>
        <BuyCreditsDialog>
            <Button size="sm" class="w-full">Buy credits</Button>
        </BuyCreditsDialog>
    </div>
</template>
