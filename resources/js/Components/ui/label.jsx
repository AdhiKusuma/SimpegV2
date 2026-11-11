import { cn } from '../../lib/utils';

export function Label({ className, ...props }) {
    return <label data-slot="label" className={cn('text-sm font-bold leading-none text-[var(--foreground)]', className)} {...props} />;
}

