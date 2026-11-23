import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export default function OrderComplete({ order, instructions, receiptUrl }) {
    const form = useForm({ proof: null });
    const { flash } = usePage().props;

    useEffect(() => {
        localStorage.removeItem('mm.cart');
        window.dispatchEvent(new Event('mm-cart-updated'));
    }, []);

    function uploadProof(event) {
        event.preventDefault();
        form.post(`/payments/${instructions.reference}/proof`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Order placed" />
            <main className="storefront">
                <section className="completion-card">
                    <p className="eyebrow">Order received</p>
                    <h1>{order.order_number}</h1>
                    {flash?.success && <p className="success-message">{flash.success}</p>}
                    {flash?.error && <p className="form-error">{flash.error}</p>}
                    <p>Order total: <strong>₦{Number(order.total).toLocaleString()}</strong></p>
                    <p>Payment status: <strong>{order.payment_status.replaceAll('_', ' ')}</strong></p>
                    {order.payment_method === 'pay_on_delivery' && <p>Pay the delivery agent when your order arrives.</p>}
                    {instructions && (
                        <div className="transfer-instructions">
                            <h2>Bank transfer instructions</h2>
                            <p>Transfer ₦{Number(instructions.amount).toLocaleString()} using reference <strong>{instructions.narration}</strong>.</p>
                            {instructions.accounts.map((account, index) => <p key={index}>{account.bank_name} · {account.account_name} · {account.account_number}</p>)}
                            <p>{instructions.note}</p>
                            <p>Your order remains pending until an administrator confirms the transfer.</p>
                            <form onSubmit={uploadProof}>
                                <label>Upload transfer proof<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(event) => form.setData('proof', event.target.files?.[0] ?? null)} required /></label>
                                <button className="button" disabled={form.processing}>Upload proof</button>
                            </form>
                        </div>
                    )}
                    <div className="completion-actions">
                        <a className="button" href={receiptUrl}>Download PDF receipt</a>
                        <Link className="secondary-button" href="/account/dashboard">View account</Link>
                        <Link className="secondary-button" href="/shop">Continue shopping</Link>
                    </div>
                </section>
            </main>
        </>
    );
}
