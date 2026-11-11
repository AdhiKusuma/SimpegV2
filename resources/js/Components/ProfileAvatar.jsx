import { useEffect, useState } from 'react';

function isFemale(gender) {
    return ['p', 'perempuan', 'wanita', 'female', 'f'].includes(
        String(gender || '').trim().toLowerCase(),
    );
}

export function defaultProfileAvatarUrl(gender) {
    return isFemale(gender) ? '/images/avatar-wanita.png' : '/images/avatar-pria.png';
}

export default function ProfileAvatar({ gender, photoUrl = null, alt = 'Avatar profil', className = '' }) {
    const [src, setSrc] = useState(photoUrl || defaultProfileAvatarUrl(gender));

    useEffect(() => {
        setSrc(photoUrl || defaultProfileAvatarUrl(gender));
    }, [gender, photoUrl]);

    return (
        <img
            src={src}
            alt={alt}
            className={`h-full w-full object-cover ${className}`}
            decoding="async"
            onError={() => {
                const fallback = defaultProfileAvatarUrl(gender);

                if (src !== fallback) {
                    setSrc(fallback);
                }
            }}
        />
    );
}
