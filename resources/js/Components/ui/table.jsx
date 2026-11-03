import { cn } from '../../lib/utils';

export function Table({ className, ...props }) {
    return <div className="relative w-full overflow-auto"><table className={cn('w-full caption-bottom text-sm', className)} {...props} /></div>;
}

export function TableHeader({ className, ...props }) {
    return <thead className={cn('border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500 [&_tr]:border-b-0', className)} {...props} />;
}

export function TableBody({ className, ...props }) {
    return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

export function TableRow({ className, ...props }) {
    return <tr className={cn('border-b border-slate-100 align-top transition hover:bg-slate-50', className)} {...props} />;
}

export function TableHead({ className, ...props }) {
    return <th className={cn('h-auto px-5 py-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-slate-500 [&:has([role=checkbox])]:pr-0', className)} {...props} />;
}

export function TableCell({ className, ...props }) {
    return <td className={cn('px-5 py-4 align-top text-sm text-slate-600 [&:has([role=checkbox])]:pr-0', className)} {...props} />;
}
