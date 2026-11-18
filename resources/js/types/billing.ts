import type { Usd } from './generation';

/** The signed-in user's credits, shared with every page. */
export type Credits = {
    balance: Usd;
    held: Usd;
    packs: number[];
};
