import { Link, usePage } from '@inertiajs/react';

function currentPath(url) {
    return String(url || '').split('?')[0].replace(/\/$/, '') || '/';
}

function currentQuery(url) {
    return new URLSearchParams(String(url || '').split('?')[1] || '');
}

function resolveHref(item, url) {
    const href = String(item.href || '');
    const [path, queryString = ''] = href.split('?');
    const query = new URLSearchParams(queryString);

    (item.preserveQuery || []).forEach((key) => {
        const value = currentQuery(url).get(key);
        if (value !== null) query.set(key, value);
    });

    const serializedQuery = query.toString();

    return serializedQuery ? `${path}?${serializedQuery}` : path;
}

function itemIsActive(path, url, item) {
    if (item.excludePaths?.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))) {
        return false;
    }

    const matchesPath = (item.activePaths || [item.href]).some((prefix) => {
        const normalized = String(prefix).replace(/\/$/, '') || '/';

        return path === normalized || (normalized !== '/' && path.startsWith(`${normalized}/`));
    });

    return matchesPath && (!item.activeWhen || item.activeWhen(url));
}

export default function SectionTabs({ items, ariaLabel }) {
    const { url } = usePage();
    const path = currentPath(url);

    if (!items || items.length === 0) return null;

    return (
        <nav className="simpeg-section-tabs" aria-label={ariaLabel}>
            {items.map((item) => {
                const active = itemIsActive(path, url, item);
                const href = resolveHref(item, url);

                return (
                    <Link
                        key={item.href}
                        href={href}
                        preserveScroll
                        className={`simpeg-section-tab ${active ? 'simpeg-section-tab--active' : ''}`}
                        aria-current={active ? 'page' : undefined}
                    >
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
}
