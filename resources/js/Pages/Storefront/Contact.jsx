import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Contact() {
    const form = useForm({ name: '', email: '', phone: '', subject: '', message: '' });
    const { props } = usePage();
    const supportEmail = props.platform?.support_email;
    const phone = props.platform?.phone;
    const address = props.platform?.address;
    const hours = props.platform?.hours;
    const mapQuery = address ? encodeURIComponent(address) : '';

    function submit(event) {
        event.preventDefault();
        form.post('/contact', { onSuccess: () => form.reset() });
    }

    return (
        <main className="marketplace-public-page marketplace-container">
            <Head title="Contact us" />
            <header className="marketplace-public-heading">
                <p><Link href="/">Home</Link> · Support</p>
                <h1>How can we help?</h1>
                <p>Send our team a message and we’ll get back to you within one business day.</p>
            </header>
            <div className="marketplace-contact-layout">
                <aside className="marketplace-public-card marketplace-contact-details">
                    <h2>Get in touch</h2>
                    <p>For order questions, include your order number so we can help faster.</p>
                    {supportEmail && <div className="marketplace-contact-detail">
                        <span aria-hidden="true">✉</span>
                        <div><small>Email us</small><a href={`mailto:${supportEmail}`}>{supportEmail}</a></div>
                    </div>}
                    {phone && <div className="marketplace-contact-detail">
                        <span aria-hidden="true">☎</span>
                        <div><small>Call us</small><a href={`tel:${String(phone).replace(/[^\d+]/g, '')}`}>{phone}</a></div>
                    </div>}
                    {hours && <div className="marketplace-contact-detail">
                        <span aria-hidden="true">◷</span>
                        <div><small>Working days & hours</small><strong>{hours}</strong></div>
                    </div>}
                    {address && <div className="marketplace-contact-detail">
                        <span aria-hidden="true">⌖</span>
                        <div><small>Visit us</small><address>{address}</address></div>
                    </div>}
                    <Link className="marketplace-contact-track-link" href="/track-order">Track an order →</Link>
                </aside>
                <section className="marketplace-public-card">
                    {props.flash?.success && <p className="marketplace-form-success" role="status">{props.flash.success}</p>}
                    <form className="marketplace-public-form" onSubmit={submit}>
                        <div className="marketplace-form-row">
                            <label>Your name<input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /></label>
                            <label>Email address<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required /></label>
                        </div>
                        <div className="marketplace-form-row">
                            <label>Phone (optional)<input type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                            <label>Subject<input value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} required maxLength="160" /></label>
                        </div>
                        <label>Message<textarea value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} rows="6" required maxLength="4000" /></label>
                        {Object.values(form.errors).map((error) => <p className="marketplace-form-error" key={error}>{error}</p>)}
                        <button className="marketplace-public-button" disabled={form.processing}>{form.processing ? 'Sending…' : 'Send message'}</button>
                    </form>
                </section>
            </div>
            <section className="marketplace-contact-locations">
                <div className="marketplace-public-card marketplace-contact-map-card">
                    <div><h2>Find us</h2>{address && <p>{address}</p>}</div>
                    {mapQuery
                        ? <iframe
                            title="Map showing our office location"
                            src={`https://maps.google.com/maps?q=${mapQuery}&output=embed`}
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            allowFullScreen
                        />
                        : <p className="marketplace-contact-map-empty">Our office location is not available at the moment.</p>}
                    {mapQuery && <a className="marketplace-inline-link" href={`https://www.google.com/maps/search/?api=1&query=${mapQuery}`} target="_blank" rel="noreferrer">Open in Google Maps ↗</a>}
                </div>
                <section className="marketplace-public-card marketplace-contact-faq">
                    <h2>Frequently asked questions</h2>
                    <details>
                        <summary>How can I track my order?</summary>
                        <p>Visit <Link href="/track-order">Track Order</Link> and enter your order number and the email address used at checkout.</p>
                    </details>
                    <details>
                        <summary>How do I request a return or refund?</summary>
                        <p>Sign in, open the relevant order from your account, and submit a return or cancellation request when the order is eligible. You can also <Link href="/contact">contact support</Link> for help.</p>
                    </details>
                    <details>
                        <summary>How do I book a service?</summary>
                        <p>Browse available providers on the <Link href="/services">services page</Link>, select a listing, and follow its request or booking instructions.</p>
                    </details>
                    <details>
                        <summary>How do I become a vendor?</summary>
                        <p>Start from our <Link href="/become-vendor">Become an Agent</Link> page to learn about getting set up and contact our team.</p>
                    </details>
                    <details>
                        <summary>When is customer support available?</summary>
                        <p>{hours ? `Our team is available ${hours}.` : 'Send us a message and we will reply within one business day.'}</p>
                    </details>
                </section>
            </section>
        </main>
    );
}
