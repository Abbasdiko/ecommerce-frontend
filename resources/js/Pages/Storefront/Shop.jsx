import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProductCard } from './Home';

export default function Shop({ listings, filters, brands, maxPrice, vendors, pageMode = 'shop', serviceCategories = [] }) {
    const isServicesPage = pageMode === 'services';
    const isDealsPage = pageMode === 'deals';
    const [values, setValues] = useState({
        search: filters.search ?? '',
        category: filters.category ?? '',
        vendor: filters.vendor ?? '',
        service: filters.service ?? '',
        type: filters.type ?? '',
        brand: filters.brand ?? '',
        max_price: filters.max_price === '' ? String(maxPrice) : String(filters.max_price),
        rating: filters.rating ?? '',
        in_stock: Boolean(filters.in_stock),
        sort: filters.sort ?? 'relevance',
    });

    function updateFilter(key, value, submit = true) {
        const next = { ...values, [key]: value };
        setValues(next);
        if (submit) applyFilters(next);
    }

    function applyFilters(next = values) {
        const query = Object.fromEntries(
            Object.entries(next).filter(([key, value]) => {
                if (key === 'max_price') return Number(value) < maxPrice;
                if (key === 'in_stock') return value;
                if (key === 'sort') return value !== 'relevance';
                return value !== '';
            }),
        );
        router.get(isServicesPage ? '/services' : isDealsPage ? '/deals' : '/shop', query, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title={isServicesPage ? 'Services marketplace' : isDealsPage ? 'Deals' : 'Shop everything'} />
            <main className="marketplace-shop-page marketplace-container">
                <header className="marketplace-shop-intro">
                    <p><Link href="/">Home</Link><span>›</span> {isServicesPage ? 'Services' : isDealsPage ? 'Deals' : 'Shop'}</p>
                    <h1>{isServicesPage ? 'Services marketplace' : isDealsPage ? 'Deals and special offers' : 'Shop everything'}</h1>
                    <small>{isServicesPage
                        ? 'Book vetted agents for home, business, health, events and professional needs.'
                        : isDealsPage
                            ? 'Discover current savings from verified marketplace vendors.'
                            : 'Products and services from verified agents across the marketplace.'}</small>
                </header>

                {isServicesPage && <section className="marketplace-shop-service-categories">
                    <h2>Service categories</h2>
                    <div className="marketplace-shop-service-grid">
                        {serviceCategories.map((service) => (
                            <Link className="marketplace-shop-service-card" key={service.id} href={`/services?service=${service.slug}`}>
                                <span aria-hidden="true">{service.icon === 'Car' ? '⌁' : service.icon === 'Building2' ? '⌂' : service.icon === 'Sparkles' ? '✣' : service.icon === 'Utensils' ? '♨' : '⚒'}</span>
                                <strong>{service.name}</strong>
                                <small>{service.description}</small>
                                <em>{service.listings_count} listings</em>
                            </Link>
                        ))}
                    </div>
                </section>}

                <div className="marketplace-shop-layout">
                    <aside className="marketplace-shop-filters" aria-label="Listing filters">
                        <div className="marketplace-shop-filter-title">
                            <h2>Filters</h2>
                                <Link href={isServicesPage ? '/services' : isDealsPage ? '/deals' : '/shop'}>Clear all</Link>
                        </div>
                        <label className="marketplace-shop-filter">
                            <span>Listing type</span>
                            <select value={values.type} onChange={(event) => updateFilter('type', event.target.value)}>
                                {isServicesPage
                                        ? <>
                                            <option value="">All service listings</option>
                                            <option value="product">Products</option>
                                            <option value="service">Services</option>
                                        </>
                                        : <>
                                            <option value="">All listings</option>
                                            <option value="product">Products</option>
                                        <option value="service">Services</option>
                                    </>}
                            </select>
                        </label>
                        <label className="marketplace-shop-filter">
                            <span>Vendor</span>
                            <select value={values.vendor} onChange={(event) => updateFilter('vendor', event.target.value)}>
                                <option value="">All vendors</option>
                                {vendors.map((vendor) => <option key={vendor.id} value={vendor.slug}>{vendor.name}</option>)}
                            </select>
                        </label>
                        {!isServicesPage && <label className="marketplace-shop-filter">
                            <span>Brand</span>
                            <select value={values.brand} onChange={(event) => updateFilter('brand', event.target.value)}>
                                <option value="">All brands</option>
                                {brands.map((brand) => <option key={brand} value={brand}>{brand}</option>)}
                            </select>
                        </label>}
                        <div className="marketplace-shop-filter">
                            <label htmlFor="shop-max-price">Max price: <strong>₦{Number(values.max_price).toLocaleString()}</strong></label>
                            <input
                                id="shop-max-price"
                                type="range"
                                min="0"
                                max={maxPrice}
                                step={Math.max(1000, Math.ceil(maxPrice / 100))}
                                value={Math.min(Number(values.max_price), maxPrice)}
                                onChange={(event) => updateFilter('max_price', event.target.value, false)}
                                onMouseUp={() => applyFilters()}
                                onTouchEnd={() => applyFilters()}
                                onKeyUp={() => applyFilters()}
                            />
                        </div>
                        <fieldset className="marketplace-shop-rating">
                            <legend>Minimum rating</legend>
                            <div>
                                {[
                                    ['', 'Any'],
                                    ['3', '3★'],
                                    ['4', '4★'],
                                    ['4.5', '4.5★'],
                                ].map(([rating, label]) => (
                                    <button
                                        className={values.rating === rating ? 'is-active' : ''}
                                        type="button"
                                        key={rating || 'any'}
                                        aria-pressed={values.rating === rating}
                                        onClick={() => updateFilter('rating', rating)}
                                    >{label}</button>
                                ))}
                            </div>
                        </fieldset>
                        <label className="marketplace-shop-stock">
                            <input type="checkbox" checked={values.in_stock} onChange={(event) => updateFilter('in_stock', event.target.checked)} />
                            <span>In stock / available only</span>
                        </label>
                    </aside>

                    <section className="marketplace-shop-results" aria-label="Shop listings">
                        <div className="marketplace-shop-toolbar">
                            <p><strong>{isServicesPage ? 'All services' : isDealsPage ? 'Current deals' : 'All listings'}</strong><span>·</span>{listings.total} results</p>
                            <label>Sort
                                <select value={values.sort} onChange={(event) => updateFilter('sort', event.target.value)}>
                                    <option value="relevance">Relevance</option>
                                    <option value="popular">Popularity</option>
                                    <option value="rating">Top rated</option>
                                    <option value="price_low">Price: low to high</option>
                                    <option value="price_high">Price: high to low</option>
                                </select>
                            </label>
                        </div>
                        {listings.data.length > 0
                            ? <div className="marketplace-shop-grid">
                                {listings.data.map((listing) => <ProductCard key={listing.id} listing={listing} presentation="shop" />)}
                            </div>
                            : <p className="marketplace-shop-empty">No listings match these filters.</p>}
                        {listings.data.length > 0 && <nav className="marketplace-shop-pagination" aria-label="Pagination">
                            {listings.links.map((link, index) => link.url
                                ? <Link key={index} href={link.url} className={link.active ? 'is-active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                                : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                        </nav>}
                    </section>
                </div>
            </main>
        </>
    );
}
