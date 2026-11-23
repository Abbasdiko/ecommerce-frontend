import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Vendors({ vendors, search }) {
    const [term, setTerm] = useState(search);

    function submit(event) {
        event.preventDefault();
        router.get('/vendors', term.trim() ? { search: term.trim() } : {}, { preserveState: true, replace: true });
    }

    return (
        <main className="marketplace-public-page marketplace-container">
            <Head title="Shop by vendor" />
            <header className="marketplace-public-heading marketplace-vendor-heading">
                <div><p><Link href="/">Home</Link> · Marketplace</p><h1>Shop by vendor</h1><p>Discover independent businesses and explore their products and services.</p></div>
                <form onSubmit={submit}><label className="visually-hidden" htmlFor="vendor-search">Search vendors</label><input id="vendor-search" value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Search vendors" /><button className="marketplace-public-button">Search</button></form>
            </header>
            {vendors.data.length
                ? <div className="marketplace-vendor-list">
                    {vendors.data.map((vendor) => <article className="marketplace-public-card marketplace-vendor-list-card" key={vendor.id}>
                        <div className="marketplace-vendor-list-avatar">{vendor.name.slice(0, 1).toUpperCase()}</div>
                        <div className="marketplace-vendor-list-copy">
                            <h2>{vendor.name}{vendor.verified && <span title="Verified vendor">✓ Verified</span>}</h2>
                            <small>{vendor.location} · {vendor.product_count} listings</small>
                            <p>{vendor.description || 'Independent marketplace vendor offering quality products and services.'}</p>
                        </div>
                        <Link className="marketplace-inline-link" href={`/shop?vendor=${vendor.slug}`}>View listings →</Link>
                    </article>)}
                </div>
                : <div className="marketplace-public-card"><p>No vendors match your search.</p></div>}
            {vendors.data.length > 0 && <nav className="marketplace-shop-pagination" aria-label="Vendor pages">
                {vendors.links.map((link, index) => link.url
                    ? <Link key={index} href={link.url} className={link.active ? 'is-active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                    : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
            </nav>}
        </main>
    );
}
