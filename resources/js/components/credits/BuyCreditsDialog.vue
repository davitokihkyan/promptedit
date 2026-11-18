<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import CreditPurchaseController from '@/actions/App/Http/Controllers/CreditPurchaseController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const page = usePage();
const open = ref(false);
const buying = ref<number | null>(null);

function buy(dollars: number): void {
    buying.value = dollars;
    router.post(
        CreditPurchaseController.store.url(),
        { dollars },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onFinish: () => (buying.value = null),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Buy credits</DialogTitle>
                <DialogDescription>
                    This is a demo checkout: credits are added at once and
                    nothing is charged.
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-3 gap-2">
                <Button
                    v-for="dollars in page.props.credits?.packs ?? []"
                    :key="dollars"
                    variant="outline"
                    class="h-16 text-lg"
                    :disabled="buying !== null"
                    @click="buy(dollars)"
                >
                    ${{ dollars }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
