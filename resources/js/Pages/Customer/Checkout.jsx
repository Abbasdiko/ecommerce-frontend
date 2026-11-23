import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const paymentPresentation = {
    paystack: {
        image: '/images/payments/paystack.svg',
        description: 'Pay securely with your card or supported bank options.',
    },
    flutterwave: {
        image: '/images/payments/flutterwave.svg',
        description: 'Choose from the payment options provided by Flutterwave.',
    },
    remita: {
        image: '/images/payments/remita.svg',
        description: 'Pay online through Remita’s secure payment service.',
    },
    bank_transfer: {
        image: '/images/payments/bank-transfer.svg',
        description: 'Transfer to the platform account and submit your payment proof.',
    },
    pay_on_delivery: {
        image: '/images/payments/pay-on-delivery.svg',
        description: 'Pay the delivery representative when your order arrives.',
    },
    wallet: {
        image: '/images/payments/wallet.svg',
        description: 'Use your available wallet balance. Pending funds are not available for checkout.',
    },
};

const money = (amount) => `₦${Number(amount || 0).toLocaleString()}`;

export default function Checkout({
    items = [],
    quote,
    addresses = [],
    methods = [],
    walletBalance = 0,
    walletPendingBalance = 0,
    bankAccounts = [],
    paymentMode = 'live',
}) {
    const [addingAddress, setAddingAddress] = useState(addresses.length === 0);
    const [couponCode, setCouponCode] = useState(quote?.coupon?.code || '');
    const initialAddress = addresses.find((address) => address.is_default) ?? addresses[0];
    const form = useForm({
        items,
        shipping_address_id: initialAddress?.id ?? '',
        payment_method: methods[0]?.id ?? 'wallet',
        coupon_code: quote?.coupon?.valid ? quote.coupon.code : '',
        notes: '',
    });
    const addressForm = useForm({
        contact_name: '',
        contact_phone: '',
        line1: '',
        city: '',
        state: '',
        items: JSON.stringify(items),
    });
    const methodsWithWallet = useMemo(() => [
        ...methods.map((method) => ({
            ...method,
            ...(paymentPresentation[method.id] || {}),
        })),
        {
            id: 'wallet',
            label: `Wallet balance (${money(walletBalance)})`,
            ...paymentPresentation.wallet,
        },
    ], [methods, walletBalance]);
    const selectedMethod = methodsWithWallet.find((method) => method.id === form.data.payment_method);
    const insufficientWallet = form.data.payment_method === 'wallet'
        && Number(walletBalance) < Number(quote?.total ?? 0);

    useEffect(() => {
        setCouponCode(quote?.coupon?.code || '');
        form.setData('coupon_code', quote?.coupon?.valid ? quote.coupon.code : '');
    }, [quote?.coupon?.code, quote?.coupon?.valid]);

    useEffect(() => {
        if (methodsWithWallet.length && !methodsWithWallet.some((method) => method.id === form.data.payment_method)) {
            form.setData('payment_method', methodsWithWallet[0].id);
        }
    }, [methodsWithWallet]);

    function submit(event) {
        event.preventDefault();
        form.post('/checkout');
    }

    function addAddress() {
        addressForm.post('/checkout/address');
    }

    function applyCoupon() {
        router.get('/checkout', {
            items: JSON.stringify(items),
            ...(couponCode.trim() ? { coupon_code: couponCode.trim() } : {}),
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Checkout" />
            <main className="marketplace-checkout">
                <header className="marketplace-checkout-heading">
                    <nav aria-label="Breadcrumb">
                        <Link href="/">Home</Link><span>›</span><Link href="/cart">Cart</Link><span>›</span><strong>Checkout</strong>
                    </nav>
                    <h1>Checkout</h1>
                </header>

                {!quote ? (
                    <div className="marketplace-checkout-empty">
                        <p>Your cart is empty.</p>
                        <Link className="button" href="/shop">Browse listings</Link>
                    </div>
                ) : (
                    <form className="marketplace-checkout-layout" onSubmit={submit}>
                        <div className="marketplace-checkout-main">
                            <section className="marketplace-checkout-section">
                                <h2>Delivery address</h2>
                                <div className="marketplace-checkout-card">
                                    {addingAddress ? (
                                        <div className="marketplace-checkout-address-form">
                                            {addresses.length > 0 && (
                                                <button className="marketplace-checkout-text-button" type="button" onClick={() => setAddingAddress(false)}>
                                                    Choose a saved address
                                                </button>
                                            )}
                                            <AddressFields form={addressForm} />
                                            {Object.entries(addressForm.errors).map(([field, message]) => (
                                                <p className="form-error" key={field}>{message}</p>
                                            ))}
                                            <button
                                                type="button"
                                                className="marketplace-checkout-add-button"
                                                disabled={addressForm.processing}
                                                onClick={(event) => {
                                                    const section = event.currentTarget.closest('.marketplace-checkout-card');
                                                    const invalidInput = section?.querySelector('input:invalid');
                                                    if (invalidInput) invalidInput.focus();
                                                    else addAddress();
                                                }}
                                            >{addressForm.processing ? 'Saving address…' : 'Save and use this address'}</button>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="marketplace-checkout-address-list">
                                                {addresses.map((address) => (
                                                    <label
                                                        className={`marketplace-checkout-address${Number(form.data.shipping_address_id) === address.id ? ' is-selected' : ''}`}
                                                        key={address.id}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="shipping_address_id"
                                                            value={address.id}
                                                            checked={Number(form.data.shipping_address_id) === address.id}
                                                            onChange={() => form.setData('shipping_address_id', address.id)}
                                                        />
                                                        <span>
                                                            <strong>{address.contact_name}</strong>
                                                            {address.contact_phone && <span> · {address.contact_phone}</span>}
                                                            <small>{[address.line1, address.line2, address.city, address.state].filter(Boolean).join(', ')}</small>
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                            <button className="marketplace-checkout-text-button" type="button" onClick={() => setAddingAddress(true)}>
                                                <span aria-hidden="true">▸</span> Add a new address
                                            </button>
                                        </>
                                    )}
                                    {form.errors.shipping_address_id && <p className="form-error">{form.errors.shipping_address_id}</p>}
                                </div>
                            </section>

                            <section className="marketplace-checkout-section">
                                <h2>Payment method</h2>
                                <div className="marketplace-checkout-card">
                                    <div className="marketplace-payment-grid">
                                        {methodsWithWallet.map((method) => (
                                            <label
                                                className={`marketplace-payment-option${form.data.payment_method === method.id ? ' is-selected' : ''}`}
                                                key={method.id}
                                            >
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    value={method.id}
                                                    checked={form.data.payment_method === method.id}
                                                    onChange={() => form.setData('payment_method', method.id)}
                                                />
                                                <img src={method.image} alt="" />
                                                <span className="marketplace-payment-option-copy">
                                                    <strong>{method.id === 'wallet' ? 'Wallet balance' : method.label}</strong>
                                                    {method.id === 'wallet' && <small>{money(walletBalance)} available</small>}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    {selectedMethod && <p className="marketplace-payment-description">{selectedMethod.description}</p>}
                                    {form.data.payment_method === 'wallet' && Number(walletPendingBalance) > 0 && (
                                        <p className="marketplace-wallet-pending">
                                            {money(walletPendingBalance)} pending · Pending funds become spendable when cleared.
                                        </p>
                                    )}
                                    {form.data.payment_method === 'wallet' && insufficientWallet && (
                                        <p className="marketplace-checkout-error">
                                            Not enough available wallet funds for this order. <Link href="/account">Add funds</Link> or select another method.
                                        </p>
                                    )}
                                    {form.data.payment_method === 'bank_transfer' && (
                                        <div className="marketplace-bank-accounts">
                                            {bankAccounts.length ? bankAccounts.map((account, index) => (
                                                <article key={`${account.bank_name}-${account.account_number}-${index}`}>
                                                    <strong>{account.bank_name}</strong>
                                                    <span>{account.account_name}</span>
                                                    <span>{account.account_number}</span>
                                                </article>
                                            )) : <p>Bank transfer instructions will be provided after placing your order.</p>}
                                        </div>
                                    )}
                                    {paymentMode === 'simulated' && ['paystack', 'flutterwave', 'remita'].includes(form.data.payment_method) && (
                                        <p className="marketplace-payment-description">Payments are currently in test mode. You’ll be returned here after confirming the payment.</p>
                                    )}
                                </div>
                            </section>

                            <section className="marketplace-checkout-section">
                                <h2>Order note <span>Optional</span></h2>
                                <div className="marketplace-checkout-card">
                                    <label className="marketplace-checkout-note">
                                        <span>Anything the seller or delivery team should know?</span>
                                        <textarea
                                            rows="3"
                                            maxLength="1000"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            placeholder="Add delivery instructions or other details"
                                        />
                                    </label>
                                </div>
                            </section>
                        </div>

                        <aside className="marketplace-checkout-summary">
                            <h2>Order summary</h2>
                            <div className="marketplace-checkout-lines">
                                {quote.items.map((item) => (
                                    <div className="marketplace-checkout-line-item" key={item.listing_id}>
                                        <span>{item.quantity} × {item.name}{Object.entries(item.selected_options || {}).map(([option, value]) => ` · ${option}: ${value}`).join('')}</span>
                                        <strong>{money(item.subtotal)}</strong>
                                    </div>
                                ))}
                            </div>
                            <div className="marketplace-checkout-coupon">
                                <label className="visually-hidden" htmlFor="checkout-coupon">Coupon code</label>
                                <input
                                    id="checkout-coupon"
                                    value={couponCode}
                                    onChange={(event) => setCouponCode(event.target.value)}
                                    placeholder="Coupon code"
                                    maxLength="40"
                                />
                                <button type="button" disabled={!couponCode.trim()} onClick={applyCoupon}>Apply</button>
                                {quote.coupon?.valid && <button type="button" className="marketplace-checkout-coupon-remove" onClick={() => {
                                    setCouponCode('');
                                    router.get('/checkout', { items: JSON.stringify(items) }, {
                                        preserveScroll: true,
                                        preserveState: true,
                                        replace: true,
                                    });
                                }}>Remove</button>}
                            </div>
                            {quote.coupon?.valid && <p className="marketplace-coupon-success">Coupon {quote.coupon.code} applied.</p>}
                            {quote.coupon?.reason && <p className="marketplace-checkout-error">{quote.coupon.reason}</p>}
                            <div className="marketplace-checkout-totals">
                                <p><span>Subtotal</span><strong>{money(quote.subtotal)}</strong></p>
                                {Number(quote.discount) > 0 && <p><span>Discount</span><strong>−{money(quote.discount)}</strong></p>}
                                <p><span>Delivery</span><strong>{money(quote.delivery_fee)}</strong></p>
                                {Number(quote.tax) > 0 && <p><span>Tax</span><strong>{money(quote.tax)}</strong></p>}
                                <p className="marketplace-checkout-total"><span>Total</span><strong>{money(quote.total)}</strong></p>
                            </div>
                            <button
                                className="marketplace-checkout-place-order"
                                disabled={form.processing || insufficientWallet || addingAddress || !form.data.shipping_address_id || !form.data.payment_method}
                            >{form.processing ? 'Placing order…' : 'Place order'}</button>
                            {Object.entries(form.errors).filter(([field]) => !['shipping_address_id', 'payment_method'].includes(field)).map(([field, message]) => (
                                <p className="marketplace-checkout-error" key={field}>{message}</p>
                            ))}
                            {form.errors.payment_method && <p className="marketplace-checkout-error">{form.errors.payment_method}</p>}
                            <p className="marketplace-checkout-protection"><span aria-hidden="true">♧</span> Your payment details are protected.</p>
                        </aside>
                    </form>
                )}
            </main>
        </>
    );
}

function AddressFields({ form }) {
    return (
        <div className="marketplace-checkout-address-fields">
            {[
                ['contact_name', 'Full name'],
                ['contact_phone', 'Phone number'],
                ['line1', 'Street address'],
                ['city', 'City'],
                ['state', 'State'],
            ].map(([name, label]) => (
                <label key={name}>{label}<input required value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} /></label>
            ))}
        </div>
    );
}
