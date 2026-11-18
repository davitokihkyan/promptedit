<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import type { Component } from 'vue';
import { ref } from 'vue';
import {
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu';
import { getAppearance, setAppearance } from '@/lib/theme';
import type { Appearance } from '@/lib/theme';

const options: { value: Appearance; label: string; icon: Component }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

const appearance = ref<Appearance>(getAppearance());

function choose(value: unknown): void {
    const option = options.find((option) => option.value === value);

    if (option) {
        appearance.value = option.value;
        setAppearance(option.value);
    }
}
</script>

<template>
    <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
        Theme
    </DropdownMenuLabel>
    <!-- @select.prevent keeps the menu open, so the new theme can be compared at once -->
    <DropdownMenuRadioGroup
        :model-value="appearance"
        @update:model-value="choose"
    >
        <DropdownMenuRadioItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            @select.prevent
        >
            <component :is="option.icon" />
            {{ option.label }}
        </DropdownMenuRadioItem>
    </DropdownMenuRadioGroup>
</template>
