import { cn } from '../../lib/utils';

const variants = {
    default: 'bg-[var(--primary)] text-[var(--primary-foreground)] shadow-sm active:scale-[0.98] hover:opacity-90',
    dark: 'bg-[var(--primary)] text-[var(--primary-foreground)] shadow-sm hover:opacity-90',
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

export function Button({ className, variant = 'default', size = 'default', type = 'button', ...props }) {
    return <button data-slot="button" type={type} className={buttonVariants({ variant, size, className })} {...props} />;
}
