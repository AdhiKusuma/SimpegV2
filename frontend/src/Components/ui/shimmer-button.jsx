import { Children, cloneElement } from 'react';
import { cn } from '../../lib/utils';

export const shimmerButtonVariants = ({ className = '' } = {}) => cn(
    'simpeg-shimmer-button inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white shadow-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
    className,
);

export function ShimmerButton({ asChild = false, className, children, ...props }) {
    const classes = shimmerButtonVariants({ className });

    if (asChild) {
        const child = Children.only(children);
        return cloneElement(child, { ...props, className: cn(classes, child.props.className) });
    }

    return <button className={classes} {...props}>{children}</button>;
}

