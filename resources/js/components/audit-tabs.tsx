import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { emails, index } from '@/routes/audit';

export function AuditTabs({ active }: { active: 'audit' | 'emails' }) {
    const tabs = [
        { key: 'audit', label: 'Perubahan Data', href: index.url() },
        { key: 'emails', label: 'Pengiriman Email', href: emails.url() },
    ] as const;

    return (
        <nav
            aria-label="Jenis log"
            className="inline-flex w-fit rounded-control border border-border bg-surface p-1"
        >
            {tabs.map((t) => (
                <Link
                    key={t.key}
                    href={t.href}
                    aria-current={active === t.key ? 'page' : undefined}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium',
                        active === t.key
                            ? 'bg-primary text-white'
                            : 'text-text-muted hover:text-navy',
                    )}
                >
                    {t.label}
                </Link>
            ))}
        </nav>
    );
}
