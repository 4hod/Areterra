export interface AuthUser {
    id: number;
    name: string;
    role: string;
    capabilities: string[];
}

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: { success?: string; error?: string };
    [key: string]: unknown;
}

export type Mood = 'happy' | 'neutral' | 'sad' | 'angry' | 'anxious';

export const MOOD_EMOJI: Record<Mood, string> = {
    happy: '😊',
    neutral: '😐',
    sad: '😟',
    angry: '😠',
    anxious: '😰',
};

export type WelfareStatus = 'green' | 'amber' | 'red';

export interface ChecklistItem {
    key: string;
    label: string;
    done: boolean;
    detail: string;
}
