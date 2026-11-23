import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ProductCard } from './Home';

const CART_KEY = 'mm.cart';
const tabs = ['Description', 'Specifications', 'Reviews', 'Vendor'];

function price(value) {
    return `₦${Number(value || 0).toLocaleString()}`;
}

function Stars({ rating, interactive = false, onChange }) {
    return (
        <span className="marketplace-listing-stars" aria-label={`${rating} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((star) => interactive ? (
                <button
                    className={star <= rating ? 'is-filled' : ''}
                    type="button"
                    key={star}
                    aria-label={`${star} star${star === 1 ? '' : 's'}`}
                    onClick={() => onChange(star)}
                >★</button>
            ) : <span className={star <= Math.round(rating) ? 'is-filled' : ''} key={star}>★</span>)}
        </span>
    );
}

export default function Listing({ listing, related, vendor, reviews, reviewEligibility }) {
    const [added, setAdded] = useState(false);
    const [quantity, setQuantity] = useState(1);
    const [size, setSize] = useState(listing.sizes?.[0] || '');
    const [color, setColor] = useState(listing.colors?.[0] || '');
    const [activeImage, setActiveImage] = useState(listing.images?.[0] || listing.image);
    const [activeTab, setActiveTab] = useState('Description');
    const reviewForm = useForm({ rating: 5, title: '', body: '' });
    const images = [...new Set([...(listing.images || []), listing.image].filter(Boolean))];
    const isProduct = listing.type !== 'service';
    const saleActive = listing.compare_at_price && Number(listing.compare_at_price) > Number(listing.base_price);
    const availability = isProduct
        ? (Number(listing.stock) > 0 ? `${listing.stock} available` : 'Currently out of stock')
        : (listing.availability || 'Available on request');

    function addToCart() {
        let cart = [];
        try {
            const stored = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            cart = Array.isArray(stored) ? stored : [];
        } catch {
            localStorage.removeItem(CART_KEY);
        }
        const selectedOptions = {};
        if (size) selectedOptions.size = size;
        if (color) selectedOptions.color = color;
        const existing = cart.find((item) => String(item.id) === String(listing.id));
        if (existing) {
            existing.quantity = Math.min(Number(listing.stock) || 99, 99, Number(existing.quantity || 1) + quantity);
            existing.selected_options = selectedOptions;
        } else {
            cart.push({ id: listing.id, quantity, selected_options: selectedOptions });
        }
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        window.dispatchEvent(new Event('mm-cart-updated'));
        setAdded(true);
    }

    function submitReview(event) {
        event.preventDefault();
        reviewForm.post(`/products/${listing.slug}/reviews`, {
            preserveScroll: true,
            onSuccess: () => reviewForm.reset('title', 'body'),
        });
    }

    return (
        <>
            <Head title={listing.name} />
            <main className="marketplace-listing-page marketplace-container">
                <header className="marketplace-listing-intro">
                    <p><Link href="/">Home</Link> · <Link href="/shop">Shop</Link> · {listing.category}</p>
                    <h1>{listing.name}</h1>
                    <p>{listing.shortDescription || listing.description}</p>
                </header>

                <section className="marketplace-listing-main">
                    <div className="marketplace-listing-product-card">
                        <div className="marketplace-listing-gallery">
                            <div className="marketplace-listing-main-image">
                                <img src={activeImage} alt={listing.name} />
                            </div>
                            {images.length > 1 && (
                                <div className="marketplace-listing-thumbnails" aria-label="Product images">
                                    {images.map((image, index) => (
                                        <button className={image === activeImage ? 'is-active' : ''} key={image} type="button" onClick={() => setActiveImage(image)}>
                                            <img src={image} alt={`${listing.name} view ${index + 1}`} />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="marketplace-listing-summary">
                            <div className="marketplace-listing-badges">
                                {(listing.badges || []).map((badge) => <span key={badge}>{badge}</span>)}
                                {vendor.verified && <span className="is-verified">Verified agent</span>}
                            </div>
                            <p className="marketplace-listing-meta">{listing.sku && `SKU: ${listing.sku}`} {listing.brand && ` · Brand: ${listing.brand}`}</p>
                            <h2>{listing.name}</h2>
                            <div className="marketplace-listing-rating">
                                <Stars rating={listing.rating} />
                                <span>{Number(listing.rating).toFixed(1)} ({listing.reviews} reviews)</span>
                                {listing.completed_sales > 0 && <span>· {listing.completed_sales} sold</span>}
                            </div>
                            <div className="marketplace-listing-price-row">
                                <strong>{price(listing.base_price)}</strong>
                                {saleActive && <><del>{price(listing.compare_at_price)}</del><span>-{Math.round((1 - Number(listing.base_price) / Number(listing.compare_at_price)) * 100)}%</span></>}
                            </div>
                            {saleActive && listing.deal_ends_at && <p className="marketplace-listing-offer">Special offer · Limited-time price</p>}
                            <div className="marketplace-listing-delivery">
                                <span>⌖ {listing.location || vendor.location}</span>
                                <span>◷ Delivery timing confirmed after checkout</span>
                                <span className={isProduct && listing.stock < 1 ? 'is-unavailable' : 'is-available'}>● {availability}</span>
                            </div>
                            {isProduct && listing.sizes?.length > 0 && (
                                <fieldset className="marketplace-listing-options">
                                    <legend>Size <strong>{size}</strong></legend>
                                    <div>{listing.sizes.map((option) => <button className={size === option ? 'is-selected' : ''} type="button" key={option} onClick={() => setSize(option)}>{option}</button>)}</div>
                                </fieldset>
                            )}
                            {isProduct && listing.colors?.length > 0 && (
                                <fieldset className="marketplace-listing-options">
                                    <legend>Color <strong>{color}</strong></legend>
                                    <div>{listing.colors.map((option) => <button className={color === option ? 'is-selected' : ''} type="button" key={option} onClick={() => setColor(option)}>{option}</button>)}</div>
                                </fieldset>
                            )}
                            <div className="marketplace-listing-buy-row">
                                <div className="marketplace-listing-quantity">
                                    <button type="button" aria-label="Decrease quantity" onClick={() => setQuantity((value) => Math.max(1, value - 1))}>−</button>
                                    <span>{quantity}</span>
                                    <button type="button" aria-label="Increase quantity" disabled={isProduct && quantity >= listing.stock} onClick={() => setQuantity((value) => Math.min(isProduct ? listing.stock : 99, value + 1))}>+</button>
                                </div>
                                <button className="marketplace-public-button" type="button" disabled={isProduct && listing.stock < 1} onClick={addToCart}>
                                    {added ? 'Added to cart' : (isProduct ? 'Add to cart' : 'Request this service')}
                                </button>
                                {added && <Link className="marketplace-listing-cart-link" href="/cart">View cart</Link>}
                            </div>
                            <div className="marketplace-listing-assurances">
                                <span>✓ Secure checkout</span><span>↺ Support with returns</span><span>◈ Protected payment options</span>
                            </div>
                        </div>
                    </div>

                    <aside className="marketplace-listing-vendor-card">
                        <p className="marketplace-about-section-kicker">Sold by agent</p>
                        <Link className="marketplace-listing-vendor-heading" href={`/shop?vendor=${vendor.slug}`}>
                            <span className="marketplace-listing-vendor-avatar">{vendor.name.slice(0, 2).toUpperCase()}</span>
                            <span><strong>{vendor.name} {vendor.verified && <i aria-label="Verified">✓</i>}</strong><small>{vendor.location}</small></span>
                        </Link>
                        <div className="marketplace-listing-vendor-rating"><Stars rating={vendor.rating} /><small>{vendor.rating.toFixed(1)} ({vendor.reviews_count})</small></div>
                        <div className="marketplace-listing-vendor-stats">
                            <span><strong>{vendor.listings_count}</strong><small>Listings</small></span>
                            <span><strong>{Number(vendor.followers).toLocaleString()}</strong><small>Followers</small></span>
                        </div>
                        <p className="marketplace-listing-vendor-about">{vendor.about || 'Independent marketplace agent.'}</p>
                        <Link className="marketplace-listing-vendor-button" href={`/shop?vendor=${vendor.slug}`}>Visit agent listings</Link>
                    </aside>
                </section>

                <section className="marketplace-listing-information marketplace-public-card">
                    <nav className="marketplace-listing-tabs" aria-label="Listing details">
                        {tabs.map((tab) => <button className={activeTab === tab ? 'is-active' : ''} type="button" key={tab} onClick={() => setActiveTab(tab)}>{tab}{tab === 'Reviews' ? ` (${reviews.length})` : ''}</button>)}
                    </nav>
                    <div className="marketplace-listing-tab-content">
                        {activeTab === 'Description' && <><h2>About this {isProduct ? 'product' : 'service'}</h2><p>{listing.description || listing.shortDescription || 'Contact the agent for more details.'}</p><p className="marketplace-listing-category-line">Category: <Link href={`/shop?service=${listing.serviceCategory || ''}`}>{listing.category}</Link></p></>}
                        {activeTab === 'Specifications' && (
                            <div className="marketplace-listing-specs">
                                {[
                                    ['Listing type', isProduct ? 'Product' : 'Service'],
                                    ['Brand', listing.brand || '—'],
                                    ['SKU', listing.sku || '—'],
                                    ['Condition', listing.condition || '—'],
                                    ['Available sizes', listing.sizes?.join(', ') || 'Not specified'],
                                    ['Available colors', listing.colors?.join(', ') || 'Not specified'],
                                    ['Availability', availability],
                                    ['Agent location', vendor.location],
                                ].map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}
                            </div>
                        )}
                        {activeTab === 'Vendor' && (
                            <div className="marketplace-listing-vendor-detail">
                                <div><h2>{vendor.name}</h2><p>{vendor.about || 'Independent marketplace agent.'}</p></div>
                                <div className="marketplace-listing-specs">
                                    <div><span>Verification</span><strong>{vendor.verified ? 'Verified agent' : 'Marketplace agent'}</strong></div>
                                    <div><span>Location</span><strong>{vendor.location}</strong></div>
                                    <div><span>Active listings</span><strong>{vendor.listings_count}</strong></div>
                                    <div><span>Customer rating</span><strong>{vendor.rating.toFixed(1)} / 5 ({vendor.reviews_count} reviews)</strong></div>
                                </div>
                                <Link className="marketplace-listing-vendor-button" href={`/shop?vendor=${vendor.slug}`}>Browse agent listings</Link>
                            </div>
                        )}
                        {activeTab === 'Reviews' && (
                            <div className="marketplace-listing-reviews">
                                <div className="marketplace-listing-review-summary">
                                    <strong>{Number(listing.rating).toFixed(1)}</strong><Stars rating={listing.rating} /><span>{listing.reviews} customer reviews</span>
                                </div>
                                {reviewEligibility.alreadyReviewed && <p className="marketplace-form-success">You have already reviewed this listing.</p>}
                                {!reviewEligibility.alreadyReviewed && reviewEligibility.canReview && (
                                    <form className="marketplace-listing-review-form" onSubmit={submitReview}>
                                        <h3>Share your experience</h3>
                                        <label>Your rating <Stars rating={reviewForm.data.rating} interactive onChange={(rating) => reviewForm.setData('rating', rating)} /></label>
                                        <label>Review title<input value={reviewForm.data.title} onChange={(event) => reviewForm.setData('title', event.target.value)} maxLength="120" /></label>
                                        <label>Your review<textarea value={reviewForm.data.body} onChange={(event) => reviewForm.setData('body', event.target.value)} rows="4" maxLength="2000" required /></label>
                                        {reviewForm.errors.review && <p className="marketplace-form-error">{reviewForm.errors.review}</p>}
                                        {reviewForm.errors.body && <p className="marketplace-form-error">{reviewForm.errors.body}</p>}
                                        <button className="marketplace-public-button" disabled={reviewForm.processing}>{reviewForm.processing ? 'Submitting…' : 'Submit verified review'}</button>
                                    </form>
                                )}
                                {!reviewEligibility.authenticated && <p className="marketplace-review-notice"><Link href="/login">Sign in</Link> and purchase this listing to leave a verified review.</p>}
                                {reviewEligibility.authenticated && !reviewEligibility.canReview && !reviewEligibility.alreadyReviewed && <p className="marketplace-review-notice">Verified reviews are available after your order has been delivered.</p>}
                                {reviews.length ? reviews.map((review) => (
                                    <article className="marketplace-listing-review" key={review.id}>
                                        <div><strong>{review.customer_name}</strong>{review.verified_purchase && <span>Verified purchase</span>}<Stars rating={review.rating} /></div>
                                        {review.title && <h3>{review.title}</h3>}
                                        <p>{review.body}</p>
                                        <small>{review.created_at ? new Date(review.created_at).toLocaleDateString() : ''}</small>
                                    </article>
                                )) : <p className="marketplace-listing-no-reviews">No reviews yet. Be the first verified buyer to share feedback.</p>}
                            </div>
                        )}
                    </div>
                </section>
                {related.length > 0 && (
                    <section className="product-section marketplace-listing-related">
                        <div className="section-heading"><div><p className="marketplace-about-section-kicker">Picked for you</p><h2>Related listings</h2></div></div>
                        <div className="product-grid">{related.map((item) => <ProductCard key={item.id} listing={item} />)}</div>
                    </section>
                )}
            </main>
        </>
    );
}
