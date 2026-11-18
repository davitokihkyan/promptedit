import { useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import GenerationEstimateController from '@/actions/App/Http/Controllers/GenerationEstimateController';
import type { CostEstimate, GenerationRequest } from '@/types';

/** Asks the server for a price whenever the request changes; the server owns the pricing rules. */
export function useCostEstimate(request: Ref<GenerationRequest>) {
    const http = useHttp<GenerationRequest, CostEstimate>({ ...request.value });
    const estimate = ref<CostEstimate | null>(null);
    const unavailable = ref(false);

    const refresh = useDebounceFn(() => {
        // Only the latest form state counts; an older response must not overwrite it.
        http.cancel();
        http.transform(() => ({ ...request.value }));
        http.submit(GenerationEstimateController(), {
            onSuccess: (response) => {
                estimate.value = response;
                unavailable.value = false;
            },
            onError: () => {
                // Invalid input (e.g. a prompt over 4,000 characters) has no price.
                estimate.value = null;
            },
            onHttpException: () => {
                unavailable.value = true;
            },
            onNetworkError: () => {
                unavailable.value = true;
            },
        }).catch(() => {});
    }, 250);

    watch(request, refresh, { deep: true, immediate: true });

    return { estimate, unavailable, loading: computed(() => http.processing) };
}
