import { Head, Link } from '@inertiajs/react';

const money = (amount) => `₦${Number(amount).toLocaleString()}`;
const label = (value) => value?.replaceAll('_', ' ') || '—';

export default function Order({ order }) {
    const address = order.shipping_address;

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <main className="storefront">
                <section className="product-section">
                    <Link href="/account">← Back to account</Link>
                    <p className="eyebrow">Order details</p>
                    <h1>{order.order_number}</h1>
                    <p className="muted">Placed {order.placed_at ? new Date(order.placed_at).toLocaleString() : '—'}</p>
                    <a className="button" href={`/account/orders/${order.id}/receipt`}>Download PDF receipt</a>
                    <div className="account-grid">
                        <article className="account-card"><span className="eyebrow">Order status</span><strong>{label(order.order_status)}</strong></article>
                        <article className="account-card"><span className="eyebrow">Payment</span><strong>{label(order.payment_status)}</strong><p className="muted">{label(order.payment_method)}</p></article>
                        <article className="account-card"><span className="eyebrow">Total</span><strong>{money(order.total)}</strong></article>
                    </div>
                    <div className="section-heading account-orders-heading"><h2>Items</h2></div>
                    {order.agent_orders?.map((agentOrder) => (
                        <section className="checkout-card order-detail-group" key={agentOrder.id}>
                            <h3>{agentOrder.agent_name || 'Marketplace seller'} · {label(agentOrder.status)}</h3>
                            {agentOrder.items.map((item) => (
                                <article className="wallet-transaction" key={item.id}>
                                    <span><strong>{item.product_name}</strong><small>SKU: {item.sku || '—'} · Qty {item.quantity}{Object.entries(item.selected_options || {}).map(([option, value]) => ` · ${option}: ${value}`).join('')}</small></span>
                                    <strong>{money(item.total)}</strong>
                                </article>
                            ))}
                        </section>
                    ))}
                    <div className="account-grid order-address-grid">
                        <section className="checkout-card"><h2>Shipping address</h2>
                            {address ? <p>{address.contact_name}<br />{address.contact_phone}<br />{address.line1}{address.line2 ? `, ${address.line2}` : ''}<br />{[address.city, address.state, address.country].filter(Boolean).join(', ')}</p> : <p className="muted">No shipping address recorded.</p>}
                        </section>
                        <section className="checkout-card"><h2>Order timeline</h2>
                            {order.status_history?.length ? order.status_history.map((entry, index) => (
                                <p key={`${entry.status}-${index}`}><strong>{label(entry.status)}</strong><br /><small>{entry.at ? new Date(entry.at).toLocaleString() : ''}</small>{entry.note && <><br />{entry.note}</>}</p>
                            )) : <p className="muted">No status updates yet.</p>}
                        </section>
                    </div>
                </section>
            </main>
        </>
    );
}
