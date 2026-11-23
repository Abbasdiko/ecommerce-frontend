import { Head, Link } from '@inertiajs/react';

export default function Orders({ orders, scope }) {
    const title = scope === 'customer' ? 'My orders' : scope === 'agent' ? 'Vendor orders' : 'All orders';

    return (
        <>
            <Head title={title} />
            <main className="storefront portal">
                <header className="site-header"><Link href="/" className="brand">MegaMarket</Link><nav><Link href="/dashboard">Dashboard</Link><Link href="/shop">Marketplace</Link></nav></header>
                <section className="product-section">
                    <p className="eyebrow">Order management</p>
                    <h1>{title}</h1>
                    {orders.data.length === 0 ? <p className="muted">No orders found.</p> : (
                        <div className="order-list">
                            {orders.data.map((order) => (
                                <article className="order-row" key={order.reference}>
                                    <strong>{order.reference}</strong>
                                    <span>{order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'}</span>
                                    <span>₦{Number(order.total).toLocaleString()}</span>
                                    <span>{order.payment_status.replaceAll('_', ' ')}</span>
                                    <span>{order.status.replaceAll('_', ' ')}</span>
                                </article>
                            ))}
                        </div>
                    )}
                    <nav className="pagination" aria-label="Pagination">
                        {orders.links.map((link, index) => link.url
                            ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} />
                            : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </nav>
                </section>
            </main>
        </>
    );
}
