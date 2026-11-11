import { cn } from '../../lib/utils';

const variants = {
    default: 'simpeg-primary shadow-sm active:scale-[0.98] hover:shadow',
    dark: 'simpeg-primary shadow-sm hover:shadow',
    outline: 'simpeg-utility border text-slate-700 hover:border-indigo-500 hover:bg-indigo-50 hover:text-indigo-700 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-200',
    ghost: 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white',
    destructive: 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
};

const sizes = {
    default: 'min-h-11 px-4 py-2',
    sm: 'min-h-10 px-3 text-sm',
    lg: 'min-h-12 px-5 text-base',
    icon: 'h-11 w-11',
};

export function buttonVariants({ variant = 'default', size = 'default', className } = {}) {
    return cn(
        'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-500/20 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        variants[variant] || variants.default,
        sizes[size] || sizes.default,
        className,
    );
}

export function Button({ className, variant = 'default', size = 'default', type = 'button', ...props }) {
    return <button type={type} className={buttonVariants({ variant, size, className })} {...props} />;
}
