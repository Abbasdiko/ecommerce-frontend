import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const CART_KEY = 'mm.cart';

function readCart() {
    try {
        const saved = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
        return Array.isArray(saved) ? saved : [];
    } catch {
        localStorage.removeItem(CART_KEY);
        return [];
    }
}

function persistCart(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items.map(({ listing, quantity, selected_options }) => ({
        id: listing.id,
        quantity,
        selected_options: selected_options || {},
    }))));
    window.dispatchEvent(new Event('mm-cart-updated'));
}

export default function Cart({ items: serverItems = [] }) {
    const [items, setItems] = useState([]);
    const [hydrated, setHydrated] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [unavailableCount, setUnavailableCount] = useState(0);

    useEffect(() => {
        let cancelled = false;

        async function hydrateCart() {
            const saved = readCart();
            const serverMap = new Map(serverItems.map(({ listing, quantity, selected_options }) => [
                String(listing.id),
                { listing, quantity, selected_options: selected_options || {} },
            ]));

            if (serverMap.size) {
                const resolved = [...serverMap.values()].map((item) => {
                    const savedItem = saved.find((entry) => String(entry.id) === String(item.listing.id));
                    return {
                        ...item,
                        selected_options: Object.keys(savedItem?.selected_options || {}).length
                            ? savedItem.selected_options
                            : item.selected_options,
                    };
                });
                if (!cancelled) {
                    setItems(resolved);
                    setHydrated(true);
                    setLoading(false);
                }
                return;
            }

            const validSaved = saved.filter((entry) => /^\d+$/.test(String(entry.id)));
            if (!validSaved.length) {
                if (!cancelled) {
                    setItems([]);
                    setUnavailableCount(saved.length);
                    setHydrated(true);
                    setLoading(false);
                }
                return;
            }

            try {
                const response = await fetch(`/storefront/listings?ids=${encodeURIComponent(validSaved.map((entry) => entry.id).join(','))}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Unable to load your cart items. Please refresh and try again.');
                const listings = await response.json();
                const listingMap = new Map(listings.map((listing) => [String(listing.id), listing]));
                const resolved = validSaved
                    .filter((entry) => listingMap.has(String(entry.id)))
                    .map((entry) => ({
                        listing: listingMap.get(String(entry.id)),
                        quantity: Math.min(99, Math.max(1, Number(entry.quantity) || 1)),
                        selected_options: entry.selected_options || {},
                    }));
                if (!cancelled) {
                    setItems(resolved);
                    setUnavailableCount(saved.length - resolved.length);
                    setHydrated(true);
                    setLoading(false);
                }
            } catch (error) {
                if (!cancelled) {
                    setLoadError(error instanceof Error ? error.message : 'Unable to load your cart items.');
                    setLoading(false);
                }
            }
        }

        hydrateCart();
        return () => { cancelled = true; };
    }, [serverItems]);

    useEffect(() => {
        if (hydrated) persistCart(items);
    }, [items, hydrated]);

    const total = useMemo(
        () => items.reduce((sum, { listing, quantity }) => sum + Number(listing.base_price) * quantity, 0),
        [items],
    );
    const stockAvailable = items.every(({ listing, quantity }) => (
        listing.type === 'service' || Number(listing.stock) >= quantity
    ));

    function updateQuantity(id, quantity) {
        setItems((current) => current.map((item) => (
            String(item.listing.id) === String(id)
                ? {
                    ...item,
                    quantity: Math.min(
                        99,
                        item.listing.type === 'service' || Number(item.listing.stock) < 1
                            ? 99
                            : Number(item.listing.stock),
                        Math.max(1, quantity),
                    ),
                }
                : item
        )));
    }

    function remove(id) {
        setItems((current) => current.filter((item) => String(item.listing.id) !== String(id)));
    }

    function continueToCheckout() {
        const checkoutItems = items.map(({ listing, quantity, selected_options }) => ({
            listing_id: Number(listing.id),
            quantity,
            selected_options: selected_options || {},
        }));
        router.visit(`/checkout?items=${encodeURIComponent(JSON.stringify(checkoutItems))}`);
    }

    return (
        <>
            <Head title="Your cart" />
            <main className="storefront">
                <section className="product-section">
                    <p className="eyebrow">Your selection</p>
                    <h1>Your cart</h1>
                    {unavailableCount > 0 && <p className="marketplace-cart-warning" role="status">{unavailableCount} unavailable cart item{unavailableCount === 1 ? ' was' : 's were'} removed.</p>}
                    {loading ? <div className="empty-state"><p>Loading your cart…</p></div> : items.length === 0 ? (
                        <div className="empty-state">
                            {loadError ? <p className="form-error">{loadError}</p> : <p>Your cart is empty.</p>}
                            <Link className="button" href="/shop">Browse listings</Link>
                        </div>
                    ) : (
                        <div className="cart-layout">
                            <div className="cart-items">
                                {items.map(({ listing, quantity, selected_options }) => (
                                    <article className="cart-item" key={listing.id}>
                                        <img src={listing.image} alt="" />
                                        <div className="cart-item-info">
                                            <Link href={`/products/${listing.slug}`}><strong>{listing.name}</strong></Link>
                                            <span>{listing.vendorName}</span>
                                            {selected_options?.size && <span>Size: {selected_options.size}</span>}
                                            {selected_options?.color && <span>Color: {selected_options.color}</span>}
                                            {listing.type !== 'service' && Number(listing.stock) < 1 && <span className="marketplace-cart-warning">Out of stock. Remove this listing to continue.</span>}
                                            {listing.type !== 'service' && Number(listing.stock) > 0 && Number(listing.stock) < quantity && <span className="marketplace-cart-warning">Only {listing.stock} available. Adjust the quantity to continue.</span>}
                                            <strong>₦{Number(listing.base_price).toLocaleString()}</strong>
                                            <div className="cart-controls">
                                                <label>Quantity <input type="number" min="1" max={listing.type === 'service' ? 99 : Math.max(1, listing.stock)} value={quantity} onChange={(event) => updateQuantity(listing.id, Number(event.target.value))} /></label>
                                                <button type="button" onClick={() => remove(listing.id)}>Remove</button>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                            <aside className="cart-summary">
                                <h2>Order summary</h2>
                                <p><span>Subtotal</span><strong>₦{total.toLocaleString()}</strong></p>
                                <small>Delivery and tax are calculated at checkout.</small>
                                <button className="button" type="button" disabled={!stockAvailable} onClick={continueToCheckout}>Proceed to checkout</button>
                            </aside>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
