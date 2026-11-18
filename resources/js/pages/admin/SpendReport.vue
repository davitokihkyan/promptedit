<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronRight, CircleCheck, CircleX } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import admin from '@/routes/admin';
import type { SpendReport } from '@/types';

const props = defineProps<{ from: string; to: string; report: SpendReport }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: admin.spend() }],
    },
});

const expanded = ref(new Set<string>());

function toggle(date: string): void {
    if (expanded.value.has(date)) {
        expanded.value.delete(date);
    } else {
        expanded.value.add(date);
    }
}

const mismatches = computed(
    () =>
        props.report.days.filter(
            (day) => day.pixelforge && !day.pixelforge.matches,
        ).length,
);

const summary = computed(() => [
    { label: 'Charged to users', value: props.report.totals.charged },
    { label: 'Pixelforge cost', value: props.report.totals.provider_cost },
    { label: 'Profit', value: props.report.totals.profit },
]);
</script>

<template>
    <Head title="Spend report" />

    <div class="space-y-6 p-4">
        <Heading
            title="Spend report"
            :description="`${from} to ${to}, by the UTC day each generation completed, the same way Pixelforge bills. Amounts are exact.`"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in summary"
                :key="card.label"
                class="rounded-xl border p-4"
            >
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="mt-1 font-mono text-lg font-semibold tabular-nums">
                    ${{ card.value }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">
                    Against Pixelforge /v1/usage
                </p>
                <p
                    v-if="!report.pixelforge_available"
                    class="mt-1 text-sm text-amber-600"
                >
                    Couldn't reach Pixelforge.
                </p>
                <p
                    v-else-if="mismatches === 0"
                    class="mt-1 flex items-center gap-1 font-semibold text-emerald-600"
                >
                    <CircleCheck class="size-4" /> Every day matches
                </p>
                <p
                    v-else
                    class="mt-1 flex items-center gap-1 font-semibold text-destructive"
                >
                    <CircleX class="size-4" /> {{ mismatches }} day(s) differ
                </p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">Day (UTC)</th>
                        <th class="p-3 text-right font-medium">Images</th>
                        <th class="p-3 text-right font-medium">Charged</th>
                        <th class="p-3 text-right font-medium">
                            Pixelforge cost
                        </th>
                        <th class="p-3 text-right font-medium">Profit</th>
                        <th class="p-3 font-medium">Pixelforge reports</th>
                    </tr>
                </thead>
                <tbody v-if="report.days.length === 0">
                    <tr>
                        <td
                            colspan="6"
                            class="p-8 text-center text-muted-foreground"
                        >
                            No completed generations in this period.
                        </td>
                    </tr>
                </tbody>
                <tbody
                    v-for="day in report.days"
                    :key="day.date"
                    class="border-t font-mono tabular-nums"
                >
                    <tr
                        class="cursor-pointer hover:bg-muted/40"
                        :aria-expanded="expanded.has(day.date)"
                        @click="toggle(day.date)"
                    >
                        <td class="p-3 font-sans font-medium">
                            <span class="flex items-center gap-1">
                                <ChevronRight
                                    class="size-4 transition-transform"
                                    :class="{
                                        'rotate-90': expanded.has(day.date),
                                    }"
                                />
                                {{ day.date }}
                            </span>
                        </td>
                        <td class="p-3 text-right">{{ day.generations }}</td>
                        <td class="p-3 text-right">${{ day.charged }}</td>
                        <td class="p-3 text-right">${{ day.provider_cost }}</td>
                        <td class="p-3 text-right">${{ day.profit }}</td>
                        <td class="p-3 font-sans">
                            <span v-if="!day.pixelforge">—</span>
                            <span
                                v-else-if="day.pixelforge.matches"
                                class="flex items-center gap-1 text-emerald-600"
                            >
                                <CircleCheck class="size-4" /> Matches
                            </span>
                            <span
                                v-else
                                class="flex items-center gap-1 text-destructive"
                            >
                                <CircleX class="size-4" />
                                Billed {{ day.pixelforge.generations }}
                                {{
                                    day.pixelforge.generations === 1
                                        ? 'image'
                                        : 'images'
                                }}, ${{ day.pixelforge.cost }}
                            </span>
                        </td>
                    </tr>
                    <template v-if="expanded.has(day.date)">
                        <tr
                            v-for="user in day.users"
                            :key="user.id ?? 'deleted'"
                            class="bg-muted/20 text-muted-foreground"
                        >
                            <td class="py-2 pr-3 pl-10 font-sans">
                                {{ user.name }}
                                <span v-if="user.email" class="block text-xs">
                                    {{ user.email }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                {{ user.generations }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                ${{ user.charged }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                ${{ user.provider_cost }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                ${{ user.profit }}
                            </td>
                            <td></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
