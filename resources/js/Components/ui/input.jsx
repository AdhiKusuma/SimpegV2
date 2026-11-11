import { forwardRef } from 'react';
import { cn } from '../../lib/utils';

export const Input = forwardRef(function Input({ className, type = 'text', unstyled = false, ...props }, ref) {
    return <input ref={ref} type={type} className={cn(!unstyled && 'simpeg-input h-11 w-full rounded-md border px-3 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-60', className)} {...props} />;
});
