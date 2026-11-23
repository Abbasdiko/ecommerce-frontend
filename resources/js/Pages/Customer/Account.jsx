import { Head, Link, useForm } from '@inertiajs/react';

export default function Account({ auth, orders, wallet, walletTransactions, depositMethods, pendingDeposit, flash }) {
    const user = auth.user;
    const depositForm = useForm({ amount: '5000', payment_method: depositMethods[0]?.id ?? '' });
    const proofForm = useForm({ proof: null });

    function fundWallet(event) {
        event.preventDefault();
        depositForm.post('/account/wallet/deposits');
    }

    function uploadProof(event) {
        event.preventDefault();
        proofForm.post(`/payments/${pendingDeposit.reference}/proof`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="My account" />
            <main className="storefront">
                <section className="product-section">
                    <div className="section-heading"><div><p className="eyebrow">Customer account</p><h1>Welcome, {user.first_name}</h1></div><form method="post" action="/logout"><input type="hidden" name="_token" value={typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : ''} /><button className="text-button">Sign out</button></form></div>
                    <p className="muted">{user.email}</p>
                    {flash?.success && <p className="success-message">{flash.success}</p>}
                    {flash?.error && <p className="form-error">{flash.error}</p>}
                    <div className="account-grid">
                        <article className="account-card">
                            <p className="eyebrow">Available wallet balance</p>
                            <strong className="balance">₦{Number(wallet.available_balance).toLocaleString()}</strong>
                            <p className="muted">₦{Number(wallet.pending_balance).toLocaleString()} pending · Use available funds at checkout.</p>
                        </article>
                        <Link className="account-card account-link" href="/shop"><span className="eyebrow">Marketplace</span><strong>Continue shopping</strong></Link>
                    </div>
                    <section className="checkout-card wallet-fund-card">
                        <h2>Fund your wallet</h2>
                        {depositMethods.length === 0 ? <p className="muted">No wallet funding methods are currently available.</p> : (
                            <form className="wallet-deposit-form" onSubmit={fundWallet}>
                                <label>Amount (NGN)<input type="number" min="100" max="10000000" step="0.01" value={depositForm.data.amount} onChange={(event) => depositForm.setData('amount', event.target.value)} required /></label>
                                <label>Payment method<select value={depositForm.data.payment_method} onChange={(event) => depositForm.setData('payment_method', event.target.value)} required>
                                    {depositMethods.map((method) => <option value={method.id} key={method.id}>{method.label}</option>)}
                                </select></label>
                                <button className="button" disabled={depositForm.processing}>Continue</button>
                            </form>
                        )}
                        {depositForm.errors.amount && <p className="form-error">{depositForm.errors.amount}</p>}
                        {pendingDeposit && (
                            <div className="transfer-instructions">
                                <h3>Complete wallet funding</h3>
                                <p>Transfer ₦{Number(pendingDeposit.amount).toLocaleString()} using reference <strong>{pendingDeposit.instructions?.narration ?? pendingDeposit.reference}</strong>.</p>
                                {pendingDeposit.instructions?.accounts?.map((account, index) => <p key={index}>{account.bank_name} · {account.account_name} · {account.account_number}</p>)}
                                <p>{pendingDeposit.instructions?.note}</p>
                                {pendingDeposit.proof_uploaded ? <p className="success-message">Proof uploaded; awaiting administrator confirmation.</p> : (
                                    <form onSubmit={uploadProof}>
                                        <label>Transfer proof (JPG, PNG, or PDF)<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(event) => proofForm.setData('proof', event.target.files?.[0] ?? null)} required /></label>
                                        <button className="button" disabled={proofForm.processing}>Upload proof</button>
                                    </form>
                                )}
                            </div>
                        )}
                    </section>
                    <div className="section-heading account-orders-heading"><h2>Recent wallet activity</h2></div>
                    {walletTransactions.length === 0 ? <p className="muted">No wallet transactions yet.</p> : (
                        <div className="order-list">
                            {walletTransactions.map((transaction) => (
                                <article className="wallet-transaction" key={transaction.id}>
                                    <span><strong>{transaction.description || transaction.type.replaceAll('_', ' ')}</strong><small>{new Date(transaction.created_at).toLocaleString()}</small></span>
                                    <strong className={transaction.direction === 'credit' ? 'wallet-credit' : 'wallet-debit'}>
                                        {transaction.direction === 'credit' ? '+' : '−'}₦{Number(transaction.amount).toLocaleString()}
                                    </strong>
                                    <span className="muted">{transaction.status.replaceAll('_', ' ')}</span>
                                </article>
                            ))}
                        </div>
                    )}
                    <div className="section-heading account-orders-heading"><h2>Recent orders</h2><Link href="/shop">Shop listings</Link></div>
                    {orders.length === 0 ? <p className="muted">No orders yet. <Link href="/shop">Start shopping.</Link></p> : (
                        <div className="order-list">
                            {orders.map((order) => (
                                <article className="order-row" key={order.id}>
                                    <Link href={`/account/orders/${order.id}`}><strong>{order.order_number}</strong></Link>
                                    <span>₦{Number(order.total).toLocaleString()}</span>
                                    <span>{order.payment_status.replaceAll('_', ' ')}</span>
                                    <span>{order.order_status.replaceAll('_', ' ')}</span>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
