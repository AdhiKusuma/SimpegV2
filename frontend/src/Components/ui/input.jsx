import { forwardRef } from 'react';
import { cn } from '../../lib/utils';

export const Input = forwardRef(function Input({ className, type = 'text', unstyled = false, ...props }, ref) {
    return <input ref={ref} type={type} className={cn(!unstyled && 'simpeg-input h-11 w-full rounded-xl border px-3 text-sm shadow-sm outline-none transition focus:border-[#F5CE59] focus:ring-4 focus:ring-[#F5CE59]/20 disabled:cursor-not-allowed disabled:opacity-60', className)} {...props} />;
});
