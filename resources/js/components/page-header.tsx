import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
};

export function PageHeader({ title, description, actions }: Props) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold text-navy">{title}</h1>
                {description && (
                    <p className="mt-1 text-sm text-text-muted">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex items-center gap-2">{actions}</div>
            )}
        </div>
    );
}
