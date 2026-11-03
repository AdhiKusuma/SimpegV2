import { Children, cloneElement, createContext, useContext } from 'react';
import { cn } from '../../lib/utils';

const DropdownMenuContext = createContext(null);

export function DropdownMenu({ open, onOpenChange, children }) {
    return <DropdownMenuContext.Provider value={{ open, onOpenChange }}>{children}</DropdownMenuContext.Provider>;
}

export function DropdownMenuTrigger({ asChild = false, children, ...props }) {
    const context = useContext(DropdownMenuContext);
    const toggle = (event) => {
        props.onClick?.(event);
        context?.onOpenChange?.(!context.open);
    };

    if (asChild) {
        const child = Children.only(children);
        return cloneElement(child, { ...props, onClick: toggle, 'aria-expanded': context?.open, 'aria-haspopup': 'menu' });
    }

    return <button type="button" {...props} onClick={toggle} aria-expanded={context?.open} aria-haspopup="menu">{children}</button>;
}

export function DropdownMenuContent({ className, children, ...props }) {
    const context = useContext(DropdownMenuContext);

    if (!context?.open) {
        return null;
    }

    return <div className={cn('simpeg-card z-40 rounded-2xl border p-2 shadow-2xl', className)} role="menu" {...props}>{children}</div>;
}

export function DropdownMenuItem({ asChild = false, className, children, ...props }) {
    const context = useContext(DropdownMenuContext);
    const close = (event) => {
        props.onClick?.(event);
        context?.onOpenChange?.(false);
    };

    if (asChild) {
        const child = Children.only(children);
        return cloneElement(child, { ...props, className: cn('flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5CE59]/70', className, child.props.className), onClick: (event) => { child.props.onClick?.(event); close(event); }, role: 'menuitem' });
    }

    return <button type="button" className={cn('flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5CE59]/70', className)} {...props} onClick={close} role="menuitem">{children}</button>;
}

