import { Link } from '@inertiajs/react';
interface Crumb { label: string; href?: string; }
export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return <nav className="hub-breadcrumbs" aria-label="Breadcrumb">{items.map((item,i) => <span key={i}>{i>0 && <i>›</i>}{item.href ? <Link href={item.href}>{item.label}</Link> : <b>{item.label}</b>}</span>)}</nav>;
}
