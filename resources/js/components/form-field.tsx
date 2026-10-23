import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: ReactNode;
};

export function FormField({
    id,
    label,
    error,
    hint,
    required,
    children,
}: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-danger"> *</span>}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-text-muted">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

/** Kelas select bawaan agar seragam dengan komponen Input. */
export const selectClassName =
    'h-10 w-full rounded-control border border-input bg-surface px-3 text-sm focus:border-primary-bright focus:ring-2 focus:ring-primary-bright/20 focus:outline-none disabled:opacity-50';
