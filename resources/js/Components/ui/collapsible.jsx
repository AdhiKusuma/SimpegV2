import { Children, cloneElement, createContext, useContext } from 'react';

const CollapsibleContext = createContext(null);

export function Collapsible({ open, onOpenChange, children, className = '' }) {
    return <CollapsibleContext.Provider value={{ open, onOpenChange }}><div className={className}>{children}</div></CollapsibleContext.Provider>;
}

export function CollapsibleTrigger({ asChild = false, children, ...props }) {
    const context = useContext(CollapsibleContext);
    const toggle = (event) => {
        props.onClick?.(event);
        context?.onOpenChange?.(!context.open);
    };

    if (asChild) {
        const child = Children.only(children);
        return cloneElement(child, { ...props, onClick: toggle, 'aria-expanded': context?.open });
    }

    return <button type="button" {...props} onClick={toggle} aria-expanded={context?.open}>{children}</button>;
}

export function CollapsibleContent({ children, ...props }) {
    const context = useContext(CollapsibleContext);

    return context?.open ? <div {...props}>{children}</div> : null;
}

