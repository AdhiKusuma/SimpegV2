import { cn } from '../../lib/utils';

const variants = {
    default: 'simpeg-ui-badge--default',
    success: 'simpeg-ui-badge--success',
    warning: 'simpeg-ui-badge--warning',
    danger: 'simpeg-ui-badge--danger',
    info: 'simpeg-ui-badge--info',
};

export function Badge({ className, variant = 'default', ...props }) {
    return <span data-slot="badge" className={cn('simpeg-ui-badge inline-flex items-center rounded-full border px-2.5 py-1 text-[0.72rem] font-medium leading-none', variants[variant] || variants.default, className)} {...props} />;
}
