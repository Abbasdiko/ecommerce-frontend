import { Head, Link, useForm, usePage } from '@inertiajs/react';

const channelNames = {
    paystack: 'Paystack',
    flutterwave: 'Flutterwave',
    remita: 'Remita',
    bank_transfer: 'Bank transfer',
};

function price(amount, currency) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(amount || 0));
}

export default function AgentInvoice({ invoice, payment, paymentChannels, bankAccounts }) {
    const { props } = usePage();
    const paymentForm = useForm({ gateway: paymentChannels?.[0] || '' });
    const proofForm = useForm({ proof: null });
    const completed = ['paid', 'free'].includes(invoice.status);
    const paymentInProgress = payment && ['pending', 'processing', 'awaiting_verification'].includes(payment.status);

    function startPayment(event) {
        event.preventDefault();
        paymentForm.post(`/agent-invoices/${invoice.id}/pay`);
    }

    function uploadProof(event) {
        event.preventDefault();
        proofForm.post(`/agent-invoices/${invoice.id}/proof`, { forceFormData: true });
    }

    return (
        <main className="marketplace-public-page marketplace-container marketplace-agent-invoice-page">
            <Head title={`Invoice ${invoice.invoice_number}`} />
            <header className="marketplace-public-heading">
                <p><Link href="/become-vendor">Become an agent</Link> · Invoice</p>
                <h1>Agent registration invoice</h1>
                <p>Invoice {invoice.invoice_number} · Status: <strong>{invoice.status.replaceAll('_', ' ')}</strong></p>
            </header>
            {props.flash?.success && <p className="marketplace-form-success">{props.flash.success}</p>}
            {props.errors?.payment && <p className="marketplace-form-error">{props.errors.payment}</p>}
            <div className="marketplace-agent-invoice-layout">
                <section className="marketplace-public-card">
                    <h2>Selected services</h2>
                    <div className="marketplace-agent-invoice-items">
                        {invoice.items.map((item, index) => (
                            <div className="marketplace-agent-invoice-item" key={`${item.service}-${index}`}>
                                <span><strong>{item.service}</strong><small>{item.plan || 'Free listing'}</small></span>
                                <strong>{price(item.amount, invoice.currency)}</strong>
                            </div>
                        ))}
                    </div>
                    <div className="marketplace-agent-invoice-total"><span>Total due</span><strong>{price(invoice.total, invoice.currency)}</strong></div>
                    {completed && <p className="marketplace-form-success">{invoice.status === 'free' ? 'This invoice is marked as a free listing. No payment is required.' : 'Payment has been received.'}</p>}
                </section>
                <section className="marketplace-public-card marketplace-agent-invoice-payment">
                    <h2>{completed ? 'Registration status' : 'Payment'}</h2>
                    {completed ? (
                        <p>Your agent account is created. If verification is pending, we will email you when the review is complete.</p>
                    ) : paymentInProgress ? (
                        <>
                            <p>Payment reference: <strong>{payment.reference}</strong></p>
                            <p>Payment status: <strong>{payment.status.replaceAll('_', ' ')}</strong></p>
                            {payment.gateway === 'bank_transfer' && (
                                <>
                                    <h3>Bank transfer details</h3>
                                    {bankAccounts.map((account, index) => (
                                        <div className="marketplace-agent-bank-account" key={`${account.account_number}-${index}`}>
                                            <strong>{account.bank_name}</strong>
                                            <span>{account.account_name}</span>
                                            <span>Account number: {account.account_number}</span>
                                        </div>
                                    ))}
                                    <p>Use your invoice number or payment reference as the transfer description.</p>
                                    {payment.proof_uploaded ? <p className="marketplace-form-success">Receipt uploaded and awaiting verification.</p> : (
                                        <form className="marketplace-agent-proof-form" onSubmit={uploadProof}>
                                            <label>Upload transfer receipt<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(event) => proofForm.setData('proof', event.target.files?.[0] || null)} required /></label>
                                            {proofForm.errors.proof && <p className="marketplace-form-error">{proofForm.errors.proof}</p>}
                                            <button className="marketplace-public-button" disabled={proofForm.processing}>{proofForm.processing ? 'Uploading…' : 'Submit receipt'}</button>
                                        </form>
                                    )}
                                </>
                            )}
                            {payment.gateway !== 'bank_transfer' && <p>Complete payment in the opened payment provider window. This page will show the verified invoice status when you return.</p>}
                            {payment.instructions && (
                                <div className="marketplace-agent-payment-instructions">
                                    {typeof payment.instructions === 'string' ? payment.instructions : (
                                        <>
                                            {payment.instructions.rrr && <p>Remita RRR: <strong>{payment.instructions.rrr}</strong></p>}
                                            {payment.instructions.note && <p>{payment.instructions.note}</p>}
                                        </>
                                    )}
                                </div>
                            )}
                        </>
                    ) : paymentChannels.length ? (
                        <form className="marketplace-agent-payment-form" onSubmit={startPayment}>
                            <p>Your selected service plans are combined into one invoice. Choose an available channel to pay.</p>
                            <label>Payment channel
                                <select value={paymentForm.data.gateway} onChange={(event) => paymentForm.setData('gateway', event.target.value)} required>
                                    {paymentChannels.map((channel) => <option value={channel} key={channel}>{channelNames[channel] || channel}</option>)}
                                </select>
                            </label>
                            {paymentForm.errors.gateway && <p className="marketplace-form-error">{paymentForm.errors.gateway}</p>}
                            {paymentForm.errors.payment && <p className="marketplace-form-error">{paymentForm.errors.payment}</p>}
                            <button className="marketplace-public-button" disabled={paymentForm.processing}>{paymentForm.processing ? 'Preparing payment…' : `Pay ${price(invoice.total, invoice.currency)}`}</button>
                        </form>
                    ) : <p>No payment channels are currently available. Contact support for assistance.</p>}
                </section>
            </div>
        </main>
    );
}
