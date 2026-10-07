const WELFARE_STATUS_LABELS: Record<string, string> = {
    green: 'No welfare concerns',
    amber: 'Welfare attention needed',
    red: 'Urgent welfare concern',
};

export function welfareStatusLabel(status: string): string {
    return WELFARE_STATUS_LABELS[status.toLowerCase()] ?? 'Welfare status unavailable';
}
