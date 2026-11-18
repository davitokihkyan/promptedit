import { useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import GenerationEstimateController from '@/actions/App/Http/Controllers/GenerationEstimateController';
import type { ChargeEstimate, GenerationRequest } from '@/types';

/** Asks the server for a price whenever the request changes; the server owns the pricing rules. */
export function useChargeEstimate(request: Ref<GenerationRequest>) {
    const http = useHttp<GenerationRequest, ChargeEstimate>({
        ...request.value,
    });
    const estimate = ref<ChargeEstimate | null>(null);
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
                // Invalid input (e.g. a prompt that's too long) has no price.
                estimate.value = null;
            },
            onHttpException: () => {
                unavailable.value = true;
            },
            onNetworkError: () => {
                unavailable.value = true;
            },
        }).catch(() => {
            // Cancelled and failed requests reject; their outcome is handled in the callbacks above.
        });
    }, 250);

    watch(request, refresh, { deep: true, immediate: true });

    return { estimate, unavailable, loading: computed(() => http.processing) };
}
