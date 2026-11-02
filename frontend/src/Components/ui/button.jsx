import { cn } from '../../lib/utils';

const variants = {
    default: 'simpeg-primary shadow-sm active:scale-[0.98]',
    dark: 'simpeg-primary shadow-sm',
    outline: 'simpeg-utility border text-[#131822]/75 hover:border-[#F5CE59] hover:bg-white hover:text-[#131822]',
    ghost: 'text-[#131822]/70 hover:bg-[#FAD873]/20 hover:text-[#131822]',
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
        'inline-flex items-center justify-center gap-2 rounded-xl text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#F5CE59]/30 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        variants[variant] || variants.default,
        sizes[size] || sizes.default,
        className,
    );
}

export function Button({ className, variant = 'default', size = 'default', type = 'button', ...props }) {
    return <button type={type} className={buttonVariants({ variant, size, className })} {...props} />;
}
