import type { Usd } from './generation';

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
