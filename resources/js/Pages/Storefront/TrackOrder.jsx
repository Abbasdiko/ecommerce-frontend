import { Head, Link, useForm } from '@inertiajs/react';

function readableStatus(value = '') {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function TrackOrder({ trackedOrder }) {
    const form = useForm({ order_number: '', email: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/track-order');
    }

    return (
        <main className="marketplace-public-page marketplace-container marketplace-track-page">
            <Head title="Track your order" />
            <header className="marketplace-public-heading">
                <p><Link href="/">Home</Link> · Orders</p>
                <h1>Track your order</h1>
                <p>Enter your order number and the email address used at checkout to see the latest status.</p>
            </header>
            <div className="marketplace-track-layout">
                <form className="marketplace-public-card marketplace-public-form" onSubmit={submit}>
                    <label>Order number<input value={form.data.order_number} onChange={(event) => form.setData('order_number', event.target.value)} placeholder="e.g. ORD-261110-ABCDE" required /></label>
                    <label>Email address<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></label>
                    {Object.values(form.errors).map((error) => <p className="marketplace-form-error" key={error}>{error}</p>)}
                    <button className="marketplace-public-button" disabled={form.processing}>{form.processing ? 'Looking up…' : 'Track order'}</button>
                </form>
                {trackedOrder && <section className="marketplace-public-card marketplace-tracking-result" aria-live="polite">
                    <span className="marketplace-tracking-label">Order {trackedOrder.order_number}</span>
                    <h2>{readableStatus(trackedOrder.order_status)}</h2>
                    <p>Payment: {readableStatus(trackedOrder.payment_status)}</p>
                    {trackedOrder.placed_at && <p>Placed {new Date(trackedOrder.placed_at).toLocaleString()}</p>}
                    {trackedOrder.history.length > 0 && <ol>{trackedOrder.history.map((event, index) => <li key={`${event.status}-${event.created_at}-${index}`}>
                        <strong>{readableStatus(event.status)}</strong>
                        {event.created_at && <small>{new Date(event.created_at).toLocaleString()}</small>}
                    </li>)}</ol>}
                </section>}
            </div>
        </main>
    );
}
