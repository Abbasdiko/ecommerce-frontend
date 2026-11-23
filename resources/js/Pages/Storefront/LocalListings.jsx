import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ProductCard } from './Home';

const storageKeys = { compare: 'mm.compare', wishlist: 'mm.wishlist' };

function readIds(key) {
    try {
        const value = JSON.parse(localStorage.getItem(key) || '[]');
        return Array.isArray(value) ? value.map(String) : [];
    } catch {
        return [];
    }
}

export default function LocalListings({ page }) {
    const [listings, setListings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const isWishlist = page === 'wishlist';
    const title = isWishlist ? 'Your wishlist' : 'Compare products';
    const storageKey = storageKeys[page];

    useEffect(() => {
        let cancelled = false;
        const ids = readIds(storageKey);

        if (ids.length === 0) {
            setListings([]);
            setLoading(false);
            return () => { cancelled = true; };
        }

        fetch(`/storefront/listings?ids=${encodeURIComponent(ids.join(','))}`, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error('Saved listings could not be loaded. Please try again.');
                return response.json();
            })
            .then((items) => {
                if (cancelled) return;
                const byId = new Map(items.map((item) => [String(item.id), item]));
                setListings(ids.map((id) => byId.get(id)).filter(Boolean));
                setLoading(false);
            })
            .catch((requestError) => {
                if (!cancelled) {
                    setError(requestError.message);
                    setLoading(false);
                }
            });

        return () => { cancelled = true; };
    }, [storageKey]);

    function removeListing(id) {
        const ids = readIds(storageKey).filter((savedId) => savedId !== String(id));
        localStorage.setItem(storageKey, JSON.stringify(ids));
        setListings((current) => current.filter((listing) => String(listing.id) !== String(id)));
    }

    return (
        <main className="marketplace-public-page marketplace-container">
            <Head title={title} />
            <header className="marketplace-public-heading">
                <p><Link href="/">Home</Link> · My shopping</p>
                <h1>{title}</h1>
                <p>{isWishlist ? 'Products and services you have saved for later.' : 'Review your selected listings side by side.'}</p>
            </header>
            {error && <p className="marketplace-form-error" role="alert">{error}</p>}
            {loading
                ? <div className="marketplace-public-card"><p>Loading saved listings…</p></div>
                : listings.length > 0
                    ? <div className="marketplace-local-listings">
                        {listings.map((listing) => <div className="marketplace-local-listing" key={listing.id}>
                            <ProductCard listing={listing} presentation="shop" />
                            <button type="button" onClick={() => removeListing(listing.id)}>{isWishlist ? 'Remove from wishlist' : 'Remove from comparison'}</button>
                        </div>)}
                    </div>
                    : <section className="marketplace-public-card marketplace-empty-list">
                        <span aria-hidden="true">{isWishlist ? '♡' : '⇄'}</span>
                        <h2>{isWishlist ? 'Your wishlist is empty' : 'Nothing to compare yet'}</h2>
                        <p>{isWishlist ? 'Save listings with the heart button and they’ll appear here.' : 'Add listings to compare from the product cards.'}</p>
                        <Link className="marketplace-public-button" href="/shop">Browse listings</Link>
                    </section>}
        </main>
    );
}
