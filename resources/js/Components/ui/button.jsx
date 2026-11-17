import { Children, cloneElement } from 'react';
import { cn } from '../../lib/utils';

const variants = {
    default: 'simpeg-primary bg-[var(--primary)] text-[var(--primary-foreground)] shadow-sm active:scale-[0.98] hover:opacity-90',
    dark: 'simpeg-primary bg-[var(--primary)] text-[var(--primary-foreground)] shadow-sm hover:opacity-90',
    outline: 'border-[var(--border)] bg-[var(--background)] text-[var(--foreground)] hover:bg-[var(--accent)] hover:text-[var(--accent-foreground)]',
    ghost: 'bg-transparent text-[var(--foreground)] hover:bg-[var(--accent)] hover:text-[var(--accent-foreground)]',
    destructive: 'bg-[var(--destructive)] text-[var(--destructive-foreground)] shadow-sm hover:opacity-90',
};

const sizes = {
    default: 'min-h-11 px-4 py-2',
    sm: 'min-h-10 px-3 text-sm',
    lg: 'min-h-12 px-5 text-base',
    icon: 'h-11 w-11',
};

export function buttonVariants({ variant = 'default', size = 'default', className } = {}) {
    return cn(
        'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--simpeg-focus-ring)] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        variants[variant] || variants.default,
        sizes[size] || sizes.default,
        className,
    );
}

export function Button({ asChild = false, className, variant = 'default', size = 'default', type = 'button', children, ...props }) {
    const classes = buttonVariants({ variant, size, className });

    if (asChild) {
        const child = Children.only(children);

        return cloneElement(child, {
            ...props,
            'data-slot': 'button',
            className: cn(classes, child.props.className),
        });
    }

    return <button data-slot="button" type={type} className={classes} {...props}>{children}</button>;
}
