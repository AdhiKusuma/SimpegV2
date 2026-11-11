import { cn } from '../../lib/utils';

export function Table({ className, ...props }) {
    return <div data-slot="table-wrapper" className="relative w-full overflow-auto"><table data-slot="table" className={cn('w-full caption-bottom text-sm text-[var(--foreground)]', className)} {...props} /></div>;
}

export function TableHeader({ className, ...props }) {
    return <thead data-slot="table-header" className={cn('border-b border-[var(--border)] text-xs uppercase tracking-wide text-[var(--muted-foreground)] [&_tr]:border-b-0', className)} {...props} />;
}

export function TableBody({ className, ...props }) {
    return <tbody data-slot="table-body" className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

export function TableRow({ className, ...props }) {
    return <tr data-slot="table-row" className={cn('border-b border-[var(--border)] align-top transition-colors hover:bg-[var(--accent)]', className)} {...props} />;
}

export function TableHead({ className, ...props }) {
    return <th data-slot="table-head" className={cn('h-auto px-5 py-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-[var(--muted-foreground)] [&:has([role=checkbox])]:pr-0', className)} {...props} />;
}

export function TableCell({ className, ...props }) {
    return <td data-slot="table-cell" className={cn('px-5 py-4 align-top text-sm text-[var(--muted-foreground)] [&:has([role=checkbox])]:pr-0', className)} {...props} />;
}
