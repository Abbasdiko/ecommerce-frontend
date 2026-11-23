import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useMarketplaceLocale } from '../../Layouts/MarketplaceLayout';

const CART_KEY = 'mm.cart';
const VIEWED_KEY = 'mm.viewed';
const BENEFITS = [
    { icon: '◉', title: 'customerSupport', subtitle: 'supportSubtitle' },
    { icon: '☎', title: 'callUs', subtitle: 'callSubtitle' },
    { icon: '➤', title: 'fastDelivery', subtitle: 'supportSubtitle' },
    { icon: '✓', title: 'secureShopping', subtitle: 'supportSubtitle' },
    { icon: '▣', title: 'securePayments', subtitle: 'supportSubtitle' },
    { icon: '↺', title: 'easyReturns', subtitle: 'supportSubtitle' },
];
const TABS = [
    ['new_arrivals', 'newArrivals'],
    ['flash_deals', 'flashDeals'],
    ['todays_deals', 'todayDeals'],
    ['featured', 'featured'],
];

function readStorage(key, fallback) {
    try {
        return JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
    } catch {
        return fallback;
    }
}

function addToCart(listing) {
    const cart = readStorage(CART_KEY, []);
    const existing = cart.find((item) => String(item.id) === String(listing.id));
    if (existing) existing.quantity = Math.min(99, Number(existing.quantity || 1) + 1);
    else cart.push({ id: listing.id, quantity: 1 });
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    window.dispatchEvent(new Event('mm-cart-updated'));
}

function markViewed(listing) {
    const current = readStorage(VIEWED_KEY, []);
    const next = [String(listing.id), ...current.filter((id) => String(id) !== String(listing.id))].slice(0, 10);
    localStorage.setItem(VIEWED_KEY, JSON.stringify(next));
}

export default function Home({
    hero,
    hotDeals,
    bestSellers,
    departments,
    departmentListings,
    vendors,
    services,
    serviceListings,
    sponsors,
    blogs,
}) {
    const { text } = useMarketplaceLocale();
    const [recentlyViewed, setRecentlyViewed] = useState([]);
    const [recommended, setRecommended] = useState([]);
    const [recentError, setRecentError] = useState('');

    useEffect(() => {
        let cancelled = false;
        const ids = readStorage(VIEWED_KEY, []).map(String);
        const products = [...hero, ...bestSellers, ...departmentListings.new_arrivals, ...serviceListings];
        const known = ids.map((id) => products.find((item) => String(item.id) === id)).filter(Boolean);
        setRecentlyViewed(known);
        setRecommended(bestSellers.filter((item) => !ids.includes(String(item.id))).slice(0, 10));
        const missingIds = ids.filter((id) => !known.some((item) => String(item.id) === id));
        if (missingIds.length) {
            setRecentError('');
            fetch(`/storefront/listings?ids=${encodeURIComponent(missingIds.join(','))}`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => {
                    if (!response.ok) throw new Error('Unable to load recently viewed products.');
                    return response.json();
                })
                .then((items) => {
                    if (cancelled) return;
                    setRecentlyViewed((current) => {
                        const byId = new Map([...current, ...items].map((item) => [String(item.id), item]));
                        return ids.map((id) => byId.get(id)).filter(Boolean);
                    });
                })
                .catch(() => {
                    if (!cancelled) setRecentError('Recently viewed products could not be loaded.');
                });
        }
        return () => { cancelled = true; };
    }, [hero, bestSellers, departmentListings, serviceListings]);

    return (
        <>
            <Head title="Shop Products & Book Services" description="Shop verified products and book trusted local services on one marketplace." />
            <main className="marketplace-home marketplace-container">
                <HeroSlider slides={hero} />
                <Benefits />
                <div className="home-two-rails">
                    <ProductRail title={text('hotDeals')} subtitle="Limited-time offers picked for you" listings={hotDeals} tone="deal" />
                    <ProductRail title="Best Selling Products" subtitle="Our most popular products this week" listings={bestSellers} />
                </div>
                <DepartmentTabs departments={departments} data={departmentListings} />
                <WeekendOffer />
                <ProductRail title="Best Selling Products" subtitle="Top picks shoppers are buying most" listings={bestSellers} />
                <OfficialStoresSection vendors={vendors} />
                <VendorSection vendors={vendors} />
                <ServicePromo />
                <ProductRail
                    title="Recommended for you"
                    subtitle="Popular right now — personalised once you start browsing"
                    listings={recommended}
                    link="/shop"
                    presentation="recommendations"
                />
                <ServicesSection services={services} listings={serviceListings} />
                <Sponsors sponsors={sponsors} />
                <BlogSection posts={blogs} />
                {recentError && <p className="marketplace-home-error" role="alert">{recentError}</p>}
                {recentlyViewed.length > 0 && <ProductRail title={text('recentlyViewed')} subtitle="Pick up where you left off" listings={recentlyViewed} />}
                <Newsletter />
            </main>
        </>
    );
}

function HeroSlider({ slides }) {
    const { text, formatPrice } = useMarketplaceLocale();
    const [index, setIndex] = useState(0);
    const slide = slides[index];

    useEffect(() => {
        if (slides.length < 2) return undefined;
        const timer = window.setInterval(() => setIndex((current) => (current + 1) % slides.length), 6000);
        return () => window.clearInterval(timer);
    }, [slides.length]);

    if (!slide) return null;
    const isService = slide.type === 'service';
    const detailUrl = `/products/${slide.slug}`;

    return (
        <section className="marketplace-hero" aria-label="Featured products and services">
            <div className="marketplace-hero-copy">
                <span className="marketplace-hero-kicker">✦ &nbsp; JUST ADDED · {isService ? 'SERVICE' : 'PRODUCT'}</span>
                <h1>{slide.name}</h1>
                <p>{slide.shortDescription}</p>
                <div className="marketplace-hero-vendor">{slide.vendorName} · {slide.rating.toFixed(1)} ★ ({slide.reviews} reviews)</div>
                <div className="marketplace-hero-price">{isService && <small>Starting from </small>}{formatPrice(slide.base_price)}</div>
                <div className="marketplace-hero-actions">
                    <Link href={detailUrl} className="marketplace-button marketplace-button-light">{isService ? text('viewService') : text('shopNow')}</Link>
                    {!isService && <button className="marketplace-button marketplace-button-outline" type="button" onClick={() => addToCart(slide)}>＋ {text('addToCart')}</button>}
                </div>
            </div>
            <Link href={detailUrl} className="marketplace-hero-image"><img src={slide.image} alt={slide.name} /></Link>
            {slides.length > 1 && <>
                <button className="marketplace-carousel-arrow hero-arrow hero-prev" aria-label="Previous slide" onClick={() => setIndex((index + slides.length - 1) % slides.length)}>‹</button>
                <button className="marketplace-carousel-arrow hero-arrow hero-next" aria-label="Next slide" onClick={() => setIndex((index + 1) % slides.length)}>›</button>
                <div className="marketplace-hero-dots">{slides.map((item, dot) => <button key={item.id} aria-label={`Show slide ${dot + 1}`} aria-current={index === dot} onClick={() => setIndex(dot)} />)}</div>
            </>}
        </section>
    );
}

function Benefits() {
    const { text } = useMarketplaceLocale();
    return (
        <section className="marketplace-benefits" aria-label="Marketplace benefits">
            {BENEFITS.map((benefit) => (
                <article key={benefit.title}>
                    <span className="marketplace-benefit-icon">{benefit.icon}</span>
                    <span><strong>{text(benefit.title)}</strong><small>{text(benefit.subtitle)}</small></span>
                </article>
            ))}
        </section>
    );
}

function ProductRail({ title, subtitle, listings, tone, link, presentation }) {
    const rail = useRef(null);
    if (!listings?.length) return null;
    const scroll = (direction) => rail.current?.scrollBy({ left: direction * rail.current.clientWidth * 0.85, behavior: 'smooth' });

    return (
        <section className={`marketplace-section marketplace-product-rail${tone === 'deal' ? ' marketplace-deal-rail' : ''}${presentation ? ` is-${presentation}` : ''}`}>
            <SectionHeading title={title} subtitle={subtitle} onPrevious={() => scroll(-1)} onNext={() => scroll(1)} link={link} />
            <div className="marketplace-product-track" ref={rail}>
                {listings.map((listing) => <ProductCard key={listing.id} listing={listing} compact presentation={presentation} />)}
            </div>
        </section>
    );
}

function ServicePromo() {
    return (
        <section className="marketplace-service-promo">
            <span>MULTI-SERVICE MARKETPLACE</span>
            <h2>Book a service in minutes</h2>
            <p>Cleaners, technicians, caterers and consultants — all vetted, all escrow-protected.</p>
            <Link href="/services">Explore Services <span aria-hidden="true">→</span></Link>
        </section>
    );
}

function WeekendOffer() {
    return (
        <section className="marketplace-weekend-offer">
            <span>WEEKEND ONLY</span>
            <h2>Special Weekend Offer</h2>
            <p>Save up to 50% across electronics, fashion and home essentials.</p>
            <Link href="/deals">Shop Deals <span aria-hidden="true">→</span></Link>
        </section>
    );
}

function OfficialStoresSection({ vendors = [] }) {
    const officialStores = vendors
        .filter((vendor) => vendor.verified)
        .slice(0, 12)
        .filter((vendor) => vendor.name);

    if (!officialStores.length) return null;

    return (
        <section className="marketplace-official-stores" aria-label="Official stores">
            <div className="marketplace-official-stores-header">
                <div className="marketplace-official-stores-backdrop" aria-hidden="true">
                    {(officialStores.some((store) => store.logo)
                        ? officialStores.filter((store) => store.logo)
                        : officialStores
                    ).slice(0, 8).map((store) => (
                        <img key={store.id} src={store.logo} alt="" onError={(event) => event.currentTarget.remove()} />
                    ))}
                </div>
                <span>Official Stores</span>
            </div>
            <div className="marketplace-official-stores-grid">
                {officialStores.map((store) => (
                    <Link key={store.id} href={`/shop?vendor=${store.slug}`} className="marketplace-official-store">
                        <span className="marketplace-official-store-logo">
                            {store.logo
                                ? <img src={store.logo} alt="" loading="lazy" onError={(event) => event.currentTarget.remove()} />
                                : <strong>{store.name.slice(0, 2).toUpperCase()}</strong>}
                        </span>
                        <span className="marketplace-official-store-name">{store.name}</span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function SectionHeading({ title, subtitle, onPrevious, onNext, link }) {
    const { text } = useMarketplaceLocale();
    return (
        <div className="marketplace-section-heading">
            <div><h2>{title}</h2>{subtitle && <p>{subtitle}</p>}</div>
            <div className="marketplace-section-tools">
                {link && <Link href={link}>{text('viewAll')}</Link>}
                {onPrevious && <button aria-label={`Previous ${title}`} onClick={onPrevious}>‹</button>}
                {onNext && <button aria-label={`Next ${title}`} onClick={onNext}>›</button>}
            </div>
        </div>
    );
}

export function ProductCard({ listing, compact = false, presentation }) {
    const { formatPrice, text } = useMarketplaceLocale();
    const showActions = presentation === 'recommendations' || presentation === 'shop';
    const [added, setAdded] = useState(false);
    const [saved, setSaved] = useState(() => readStorage('mm.wishlist', []).includes(String(listing.id)));
    const [compared, setCompared] = useState(() => readStorage('mm.compare', []).map(String).includes(String(listing.id)));
    const url = `/products/${listing.slug}`;
    const discount = listing.compare_at_price > listing.base_price
        ? Math.round((1 - listing.base_price / listing.compare_at_price) * 100)
        : 0;

    function toggleWishlist() {
        const wishlist = readStorage('mm.wishlist', []).map(String);
        const next = saved ? wishlist.filter((id) => id !== String(listing.id)) : [...wishlist, String(listing.id)];
        localStorage.setItem('mm.wishlist', JSON.stringify(next));
        setSaved(!saved);
    }

    function toggleCompare() {
        const comparison = readStorage('mm.compare', []).map(String);
        const next = compared
            ? comparison.filter((id) => id !== String(listing.id))
            : [...comparison, String(listing.id)];
        localStorage.setItem('mm.compare', JSON.stringify(next));
        setCompared(!compared);
    }

    return (
        <article className={`marketplace-product-card${compact ? ' is-compact' : ''}${presentation ? ` is-${presentation}` : ''}`}>
            <div className="marketplace-product-image-wrap">
                <Link href={url} onClick={() => markViewed(listing)}><img src={listing.image} alt={listing.name} loading="lazy" /></Link>
                {showActions
                    ? listing.badges?.length > 0 && <div className="marketplace-product-badges">{listing.badges.map((badge) => <span className="marketplace-product-badge" key={badge}>{badge}</span>)}</div>
                    : listing.badges?.[0] && <span className="marketplace-product-badge">{listing.badges[0]}</span>}
                <button className={`marketplace-wishlist${saved ? ' is-saved' : ''}`} aria-label={saved ? 'Remove from wishlist' : 'Add to wishlist'} onClick={toggleWishlist}>♡</button>
            </div>
            <div className="marketplace-product-info">
                {!compact && <Link className="marketplace-product-vendor" href={`/shop?vendor=${listing.vendorSlug}`}>{listing.vendorName}</Link>}
                <Link className="marketplace-product-name" href={url} onClick={() => markViewed(listing)}>{listing.name}</Link>
                {showActions
                    ? <span className="marketplace-product-rating is-detailed" aria-label={`${Number(listing.rating).toFixed(1)} out of 5 stars`}>
                        <span aria-hidden="true">{Array.from({ length: 5 }, (_, star) => <span className={star < Math.round(Number(listing.rating)) ? 'is-filled' : undefined} key={star}>{star < Math.round(Number(listing.rating)) ? '★' : '☆'}</span>)}</span>
                        <small>{Number(listing.rating).toFixed(1)}{presentation === 'service-featured' ? ` (${listing.reviews})` : ''}</small>
                    </span>
                    : <span className="marketplace-product-rating">★ {Number(listing.rating).toFixed(1)} <small>({listing.reviews})</small></span>}
                <div className="marketplace-product-price">
                    {listing.type === 'service' && <small>From </small>}{formatPrice(listing.base_price)}
                    {discount > 0 && <><del>{formatPrice(listing.compare_at_price)}</del><em>-{discount}%</em></>}
                </div>
                <small className="marketplace-stock">{listing.type === 'service' ? 'Booking available' : listing.stock > 0 ? `${listing.stock < 10 ? `Only ${listing.stock} left` : 'In stock'}` : 'Out of stock'}</small>
                <div className={showActions ? 'marketplace-recommended-actions' : undefined}>
                    {listing.type === 'service'
                        ? <Link className="marketplace-card-button" href={url}>Request Service</Link>
                        : <button className="marketplace-card-button" disabled={listing.stock <= 0} onClick={() => { addToCart(listing); setAdded(true); }}>
                            {showActions && <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 9H6" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>}
                            {added ? text('addedToCart') : text('addToCart')}
                        </button>}
                    {showActions && <>
                        <Link className="marketplace-card-icon-button" href={url} aria-label={`View ${listing.name}`} title="View item">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" /></svg>
                        </Link>
                        <button className={`marketplace-card-icon-button${compared ? ' is-selected' : ''}`} type="button" aria-label={compared ? 'Remove from compare' : 'Add to compare'} aria-pressed={compared} title={compared ? 'Remove from compare' : 'Add to compare'} onClick={toggleCompare}>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h13l-3-3m3 3-3 3M17 17H4l3 3m-3-3 3-3" /></svg>
                        </button>
                    </>}
                </div>
            </div>
        </article>
    );
}

function DepartmentTabs({ departments, data }) {
    const [active, setActive] = useState('new_arrivals');
    const { text } = useMarketplaceLocale();
    return (
        <section className="marketplace-section">
            <div className="marketplace-department-controls">
                <div className="marketplace-tabs" role="tablist" aria-label="Department products">
                    {TABS.map(([key, title]) => <button key={key} role="tab" aria-selected={active === key} onClick={() => setActive(key)}>{text(title)}</button>)}
                </div>
                <div className="marketplace-category-links">{departments.map((department) => <Link key={department.id} href={`/shop?category=${department.slug}`}>{department.name}</Link>)}</div>
            </div>
            <ProductGrid listings={data[active] || []} />
        </section>
    );
}

function ProductGrid({ listings, presentation }) {
    return <div className="marketplace-product-grid">{listings.slice(0, 8).map((listing) => <ProductCard key={listing.id} listing={listing} presentation={presentation} />)}</div>;
}

function VendorSection({ vendors }) {
    const { text } = useMarketplaceLocale();
    return (
        <section className="marketplace-section">
            <SectionHeading title={text('vendors')} subtitle="Discover products from verified independent sellers" link="/shop" />
            <div className="marketplace-vendor-grid">
                {vendors.map((vendor) => (
                    <Link className="marketplace-vendor-card" key={vendor.id} href={`/shop?vendor=${vendor.slug}`}>
                        <span className="marketplace-vendor-avatar">{vendor.name.slice(0, 1).toUpperCase()}</span>
                        <span><strong>{vendor.name}{vendor.verified && <i title="Verified vendor">✓</i>}</strong><small>{vendor.location} · {vendor.product_count} listings</small></span>
                        <b>›</b>
                    </Link>
                ))}
            </div>
            {vendors.slice(0, 2).map((vendor) => <div className="marketplace-vendor-rail" key={vendor.id}><ProductRail title={vendor.name} subtitle={`${vendor.location} · Verified marketplace vendor`} listings={vendor.listings} /></div>)}
        </section>
    );
}

function ServicesSection({ services, listings }) {
    return (
        <section className="marketplace-section marketplace-service-exploration">
            <SectionHeading title="Explore our services" subtitle="Book properties, repairs, catering, beauty and professional services" link="/services" />
            <div className="marketplace-service-grid">
                {services.map((service) => <Link key={service.id} href={`/services?service=${service.slug}`} className="marketplace-service-card">
                    <span>{service.icon === 'Car' ? '🚘' : service.icon === 'Building2' ? '⌂' : service.icon === 'Sparkles' ? '✦' : '⚒'}</span>
                    <strong>{service.name}</strong><small>{service.tagline}</small><small>{service.listings_count} listings</small>
                </Link>)}
            </div>
            <h3 className="marketplace-subheading">Featured service listings</h3>
            <ProductGrid listings={listings.filter((item) => item.type === 'service').slice(0, 4)} presentation="service-featured" />
        </section>
    );
}

function Sponsors({ sponsors }) {
    const { text } = useMarketplaceLocale();
    return <section className="marketplace-section"><SectionHeading title={text('partners')} subtitle="Payments, logistics and financing partners powering the marketplace" />
        <div className="marketplace-sponsor-grid">{sponsors.map((sponsor) => <article key={sponsor.name}><strong>{sponsor.name}</strong><small>{sponsor.description}</small></article>)}</div>
    </section>;
}

function BlogSection({ posts }) {
    const { text } = useMarketplaceLocale();
    if (!posts.length) return null;
    return <section className="marketplace-section"><SectionHeading title={text('blogLatest')} subtitle="Buying guides, reviews and marketplace news" link="/blog" />
        <div className="marketplace-blog-grid">{posts.map((post) => <article className="marketplace-blog-card" key={post.id}>
            <Link href={`/blog/${post.slug}`}><img src={post.image} alt="" loading="lazy" /></Link>
            <div><small>{post.category} · {post.read_minutes} min read</small><Link href={`/blog/${post.slug}`}><h3>{post.title}</h3></Link><p>{post.excerpt}</p></div>
        </article>)}</div>
    </section>;
}

function Newsletter() {
    const { text } = useMarketplaceLocale();
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);

    async function subscribe(event) {
        event.preventDefault();
        setBusy(true);
        setMessage('');
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch('/newsletter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ email, source: 'storefront' }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Could not subscribe. Please try again.');
            setEmail('');
            setMessage('You are subscribed. Watch your inbox for deals.');
        } catch (error) {
            setMessage(error.message);
        } finally {
            setBusy(false);
        }
    }

    return <section className="marketplace-newsletter"><span>✉</span><h2>{text('newsletterTitle')}</h2><p>Be first to know about new products, special offers and vendor drops.</p>
        <form onSubmit={subscribe}><label className="visually-hidden" htmlFor="newsletter-email">Your email address</label><input id="newsletter-email" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} placeholder="Enter your email address" /><button disabled={busy}>{busy ? 'Subscribing…' : text('subscribe')}</button></form>
        {message && <p role="status">{message}</p>}
        <small>✓ Deals &nbsp; ✓ New products &nbsp; ✓ Offers &nbsp; ✓ Vendor drops</small>
    </section>;
}
