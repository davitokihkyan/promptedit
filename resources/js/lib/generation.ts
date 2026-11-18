import type { GenerationStatus, ImageQuality, ImageSize } from '@/types';

export const sizeLabels: Record<ImageSize, string> = {
    '1024x1024': 'Square',
    '1536x1024': 'Landscape',
    '1024x1536': 'Portrait',
};

export const qualityLabels: Record<ImageQuality, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
};

export function isFinished(status: GenerationStatus): boolean {
    return status === 'succeeded' || status === 'failed';
}
