const KEY = 'ah-recently-viewed';
const MAX_ITEMS = 8;

export interface RecentItem {
    title: string;
    url: string;
    type: 'Member' | 'Animal';
}

export function recordRecentlyViewed(item: RecentItem) {
    try {
        const existing: RecentItem[] = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        const deduped = existing.filter((i) => i.url !== item.url);
        deduped.unshift(item);
        localStorage.setItem(KEY, JSON.stringify(deduped.slice(0, MAX_ITEMS)));
    } catch {
        // localStorage unavailable — recently-viewed is a nice-to-have, fail silently
    }
}

export function getRecentlyViewed(): RecentItem[] {
    try {
        return JSON.parse(localStorage.getItem(KEY) ?? '[]');
    } catch {
        return [];
    }
}
