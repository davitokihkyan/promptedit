<script setup lang="ts" generic="T extends string">
import InputError from '@/components/InputError.vue';
import { cn } from '@/lib/utils';

defineProps<{
    legend: string;
    options: { value: T; label: string; hint?: string }[];
    error?: string;
}>();

const selected = defineModel<T>({ required: true });
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="mb-2 text-sm font-medium">{{ legend }}</legend>
        <div class="flex gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :aria-pressed="selected === option.value"
                :class="
                    cn(
                        'flex-1 rounded-md border px-3 py-2 text-left text-sm transition-colors',
                        selected === option.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent',
                    )
                "
                @click="selected = option.value"
            >
                <span class="block font-medium">{{ option.label }}</span>
                <span v-if="option.hint" class="block text-xs opacity-75">
                    {{ option.hint }}
                </span>
            </button>
        </div>
        <InputError :message="error" />
    </fieldset>
</template>
