export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type ImageSize = '1024x1024' | '1536x1024' | '1024x1536';

export type ImageQuality = 'low' | 'medium' | 'high';

/** Money arrives as exact 9-decimal USD strings, e.g. "0.381887500". */
export type Usd = string;

export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    reference_images: string[];
    estimated_charge: Usd;
    max_charge: Usd;
    charge: Usd | null;
    image_url: string | null;
    image_expired: boolean;
    failure: string | null;
    created_at: string;
    completed_at: string | null;
};

export type GenerationRequest = {
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    reference_images: string[];
};

export type TokenUsage = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
};

export type CostEstimate = {
    expected: Usd;
    maximum: Usd;
    tokens: { expected: TokenUsage; maximum: TokenUsage };
};

export type Credits = {
    balance: Usd;
    reserved: Usd;
    packs: number[];
};

export type Spend = {
    generations: number;
    charged: Usd;
    provider_cost: Usd;
    profit: Usd;
};

export type SpendDay = Spend & {
    date: string;
    users: (Spend & {
        id: number | null;
        name: string;
        email: string | null;
    })[];
    pixelforge: { generations: number; cost: Usd; matches: boolean } | null;
};

export type SpendReport = {
    days: SpendDay[];
    totals: Spend;
    pixelforge_available: boolean;
};
