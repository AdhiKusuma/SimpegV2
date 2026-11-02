import { cn } from '../../lib/utils';

export function Label({ className, ...props }) {
    return <label className={cn('text-sm font-bold leading-none text-slate-900', className)} {...props} />;
}

