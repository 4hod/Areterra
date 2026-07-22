import { Link } from '@inertiajs/react';

interface Crumb {
    label: string;
    href?: string;
}

export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav className="flex items-center gap-1.5 text-sm text-ink/45 mb-3" aria-label="Breadcrumb">
            {items.map((item, i) => (
                <span key={i} className="flex items-center gap-1.5">
                    {i > 0 && <span aria-hidden>/</span>}
                    {item.href ? (
                        <Link href={item.href} className="hover:text-brand font-medium">
                            {item.label}
                        </Link>
                    ) : (
                        <span className="font-medium text-ink/70">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
