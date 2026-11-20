import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import PageSizeSelect from './PageSizeSelect';

const pageSizePaths = new Set(['/pegawai', '/pendidikan', '/pangkat', '/pengangkatan', '/penghargaan', '/penugasan', '/seminar', '/cuti', '/bahasa', '/dp3', '/jabatan', '/mutasi', '/diklat', '/pelanggaran', '/latihan-jabatan', '/sertifikasi', '/biaya-pendidikan', '/kantor', '/users', '/laporan/nominatif', '/audit-log']);

function numericLabel(label) {
    const plain = String(label || '').replace(/<[^>]*>/g, '').replace(/&[^;]+;/g, '').trim();
    return /^\d+$/.test(plain) ? Number(plain) : null;
}

function queryDetails(url) {
    const [path, query = ''] = String(url || '').split('?');
    const params = new URLSearchParams(query.split('#')[0]);
    const pageKey = [...params.keys()].find((key) => key === 'page' || key.endsWith('_page')) || 'page';
    return { path: path || '/', params, pageKey };
}

export default function PageJumpControls({ paginator, label = 'data', ariaLabel = 'Paginasi' }) {
    const { url } = usePage();
    const links = Array.isArray(paginator?.links) ? paginator.links : [];
    const activePage = links.map((link) => link.active ? numericLabel(link.label) : null).find(Boolean);
    const urlDetails = useMemo(() => queryDetails(url), [url]);
    const pageKey = useMemo(() => {
        for (const link of links) {
            if (!link.url) continue;
            const details = queryDetails(link.url);
            if (details.params.has(details.pageKey)) return details.pageKey;
        }
        return urlDetails.pageKey;
    }, [links, urlDetails.pageKey]);
    const queryPage = Number(urlDetails.params.get(pageKey) || 1);
    const currentPage = activePage || (Number.isFinite(queryPage) && queryPage > 0 ? queryPage : 1);
    const nextLink = links.find((link) => /next|berikut/i.test(String(link.label)));
    const previousLink = links.find((link) => /previous|sebelumnya/i.test(String(link.label)));
    const numberedPages = links.map((link) => numericLabel(link.label)).filter(Boolean);
    const nextPage = nextLink?.url ? Number(queryDetails(nextLink.url).params.get(pageKey)) : null;
    const lastPage = Math.max(1, ...numberedPages, currentPage, nextPage || 1);
    const [jumpPage, setJumpPage] = useState(String(currentPage));
    const paginationRef = useRef(null);
    const [toolbar, setToolbar] = useState(null);
    const supportsPageSize = pageSizePaths.has(urlDetails.path) || /^\/(kantor|keluarga)\/[^/]+$/.test(urlDetails.path);

    useEffect(() => setJumpPage(String(currentPage)), [currentPage]);

    useEffect(() => {
        if (!supportsPageSize || !paginationRef.current) return undefined;
        const section = paginationRef.current.closest('section');
        const table = section?.querySelector('table');
        if (!table) return undefined;
        const tableSurface = [...(section?.children || [])].find((child) => child.contains(table));
        if (!tableSurface) return undefined;

        let tableContainer = table;
        while (tableContainer.parentElement && tableContainer.parentElement !== tableSurface) tableContainer = tableContainer.parentElement;
        if (tableContainer.parentElement !== tableSurface) return undefined;

        const slot = document.createElement('div');
        slot.className = 'simpeg-page-size-toolbar';
        tableSurface.insertBefore(slot, tableContainer);
        setToolbar(slot);
        return () => { slot.remove(); };
    }, [supportsPageSize, urlDetails.path]);

    const pageHref = (page) => {
        const params = new URLSearchParams(urlDetails.params);
        if (page <= 1) params.delete(pageKey);
        else params.set(pageKey, String(page));
        const query = params.toString();
        return query ? `${urlDetails.path}?${query}` : urlDetails.path;
    };

    const visiblePages = useMemo(() => {
        if (lastPage <= 7) return Array.from({ length: lastPage }, (_, index) => index + 1);

        const pages = new Set([1, lastPage, currentPage - 1, currentPage, currentPage + 1]);
        if (currentPage <= 3) [2, 3, 4, 5].forEach((page) => pages.add(page));
        if (currentPage >= lastPage - 2) [lastPage - 4, lastPage - 3, lastPage - 2, lastPage - 1].forEach((page) => pages.add(page));

        return [...pages].filter((page) => page >= 1 && page <= lastPage).sort((a, b) => a - b);
    }, [currentPage, lastPage]);

    function jump(event) {
        event.preventDefault();
        const requestedPage = Number.parseInt(jumpPage, 10);
        if (!Number.isFinite(requestedPage)) return;

        const targetPage = Math.min(lastPage, Math.max(1, requestedPage));
        setJumpPage(String(targetPage));
        router.get(pageHref(targetPage), {}, { preserveState: true, preserveScroll: true });
    }

    function changePageSize(size) {
        const params = new URLSearchParams(urlDetails.params);
        params.set('per_page', String(size));
        params.delete(pageKey);
        router.get(`${urlDetails.path}?${params.toString()}`, {}, { preserveState: true, preserveScroll: true });
    }

    if (lastPage <= 1 && !supportsPageSize) return null;

    return (
        <nav ref={paginationRef} className="simpeg-page-pagination" aria-label={ariaLabel}>
            {supportsPageSize && toolbar && createPortal(<PageSizeSelect value={Number(paginator?.per_page) || 10} onChange={changePageSize} />, toolbar)}
            {lastPage > 1 && <>
            <div className="simpeg-page-pagination__links">
                {currentPage > 1 && previousLink?.url && (
                    <Link href={previousLink.url} preserveState preserveScroll className="simpeg-page-pagination__step" aria-label="Halaman sebelumnya">‹</Link>
                )}
                {visiblePages.map((page, index) => {
                    const previousPage = visiblePages[index - 1];
                    return (
                        <span className="simpeg-page-pagination__group" key={page}>
                            {previousPage && page - previousPage > 1 && <span className="simpeg-page-pagination__ellipsis" aria-hidden="true">…</span>}
                            <Link
                                href={pageHref(page)}
                                preserveState
                                preserveScroll
                                aria-current={page === currentPage ? 'page' : undefined}
                                className={`simpeg-page-pagination__number${page === currentPage ? ' is-active' : ''}`}
                            >
                                {page}
                            </Link>
                        </span>
                    );
                })}
                {currentPage < lastPage && nextLink?.url && (
                    <Link href={nextLink.url} preserveState preserveScroll className="simpeg-page-pagination__step" aria-label="Halaman berikutnya">›</Link>
                )}
            </div>
            <form className="simpeg-page-pagination__jump" onSubmit={jump}>
                <label htmlFor={`page-jump-${ariaLabel.replace(/[^a-z0-9]/gi, '-').toLowerCase()}`}>Ke halaman</label>
                <input
                    id={`page-jump-${ariaLabel.replace(/[^a-z0-9]/gi, '-').toLowerCase()}`}
                    type="number"
                    inputMode="numeric"
                    min="1"
                    max={lastPage}
                    value={jumpPage}
                    onChange={(event) => setJumpPage(event.target.value)}
                    aria-label={`Lompat ke halaman, ${label}`}
                />
                <button type="submit">Lompat</button>
            </form>
            </>}
        </nav>
    );
}
