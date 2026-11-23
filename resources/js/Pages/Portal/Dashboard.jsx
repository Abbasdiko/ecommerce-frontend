import { Head, Link } from '@inertiajs/react';

const labels = {
    total_customers: 'Customers',
    total_agents: 'Vendors',
    active_agents: 'Active vendors',
    pending_agents: 'Pending vendors',
    active_subscriptions: 'Active subscriptions',
    total_listings: 'Listings',
    pending_listings: 'Pending listings',
    todays_orders: "Today's orders",
    todays_revenue: "Today's revenue",
    platform_commission: 'Company commission',
    pending_bank_transfers: 'Bank transfers to verify',
    pending_withdrawals: 'Withdrawals to review',
    pending_refunds: 'Returns and refunds',
    open_disputes: 'Open disputes',
    open_tickets: 'Support tickets',
    total_sales: 'Sales',
    today_sales: "Today's sales",
    pending_orders: 'Pending orders',
    completed_orders: 'Completed orders',
    active_listings: 'Active listings',
    pending_earnings: 'Pending earnings',
    available_balance: 'Available wallet',
    total_commission: 'Company commission',
    total_orders: 'Orders',
    pending_orders_customer: 'Open orders',
    total_spent: 'Total spent',
    wallet_available: 'Wallet balance',
};

const moneyKeys = new Set([
    'todays_revenue', 'platform_commission', 'total_sales', 'today_sales',
    'pending_earnings', 'available_balance', 'total_spent', 'wallet_available',
]);

export default function Dashboard({ auth, summary }) {
    const user = auth.user;
    const widgets = summary.widgets ?? {};
    const title = summary.scope === 'platform'
        ? 'Platform overview'
        : summary.scope === 'agent'
            ? summary.agent?.business_name ?? 'Vendor dashboard'
            : 'Customer dashboard';

    return (
        <>
            <Head title={title} />
            <main className="storefront portal">
                <header className="site-header">
                    <Link href="/" className="brand">MegaMarket</Link>
                    <nav>
                        <Link href="/dashboard">Overview</Link>
                        <Link href="/dashboard/orders">Orders</Link>
                        {summary.scope === 'customer' && <Link href="/account">Wallet and account</Link>}
                        <Link href="/shop">Marketplace</Link>
                        <form method="post" action="/logout"><input type="hidden" name="_token" value={typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : ''} /><button className="text-button">Sign out</button></form>
                    </nav>
                </header>
                <section className="product-section">
                    <p className="eyebrow">{summary.scope === 'platform' ? 'Administration' : summary.scope === 'agent' ? 'Vendor workspace' : 'Your account'}</p>
                    <h1>{title}</h1>
                    <p className="muted">Welcome, {user.first_name}.</p>
                    {summary.onboarding_required && <p className="notice-message">Complete vendor onboarding to access your store workspace.</p>}
                    {summary.kyc_required && <p className="notice-message">Complete identity verification to unlock all vendor features.</p>}
                    <div className="metric-grid">
                        {Object.entries(widgets).map(([key, value]) => (
                            <article className="metric-card" key={key}>
                                <span>{labels[key] ?? key.replaceAll('_', ' ')}</span>
                                <strong>{moneyKeys.has(key) ? `₦${Number(value).toLocaleString()}` : Number(value).toLocaleString()}</strong>
                            </article>
                        ))}
                    </div>
                    {summary.scope === 'platform' && (
                        <div className="account-grid portal-queues">
                            <Queue title="Vendor approvals" items={summary.queues?.agent_approvals ?? []} nameKey="business_name" />
                            <Queue title="Listing approvals" items={summary.queues?.listing_approvals ?? []} nameKey="name" />
                        </div>
                    )}
                    <div className="portal-shortcuts">
                        <Link className="button" href="/dashboard/orders">{summary.scope === 'customer' ? 'View my orders' : 'Review orders'}</Link>
                        {summary.scope === 'customer' && <Link className="secondary-button" href="/checkout">Start shopping</Link>}
                        {summary.scope === 'agent' && <Link className="secondary-button" href="/shop">View marketplace</Link>}
                    </div>
                </section>
            </main>
        </>
    );
}

function Queue({ title, items, nameKey }) {
    return (
        <section className="account-card">
            <h2>{title}</h2>
            {items.length === 0 ? <p className="muted">Nothing waiting for review.</p> : items.map((item) => (
                <p className="queue-row" key={item.id}>{item[nameKey]} <span>{new Date(item.created_at).toLocaleDateString()}</span></p>
            ))}
        </section>
    );
}
