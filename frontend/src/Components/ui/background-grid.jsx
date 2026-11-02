import { cn } from '../../lib/utils';

export function BackgroundGrid({ as: Component = 'div', className, children, ...props }) {
    return (
        <Component className={cn('relative overflow-hidden', className)} {...props}>
            <div className="simpeg-background-grid pointer-events-none absolute inset-0" aria-hidden="true" />
            <div className="relative z-10">{children}</div>
        </Component>
    );
}

