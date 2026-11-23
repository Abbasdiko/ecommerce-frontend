import { Head, Link, useForm, usePage } from '@inertiajs/react';

const legalContent = {
    privacy: {
        title: 'Privacy policy',
        intro: 'This policy explains how the marketplace uses the information you provide when you browse, shop, or contact us.',
        sections: [
            ['Information we collect', 'We use account and contact details, order and payment status, delivery information, and the preferences you choose to save. Payment details are handled by the selected payment provider.'],
            ['How we use information', 'We use information to operate accounts, process orders, provide customer support, prevent fraud, and improve the marketplace. We share only the details needed with the vendor fulfilling an order and service providers supporting the marketplace.'],
            ['Your choices', 'You can review and update account details from your account page. Contact support to ask questions about your information or request assistance with an account-related privacy request.'],
            ['Security and retention', 'We use access controls and other safeguards appropriate to the information we handle, and retain records as needed to provide services and meet operational or legal obligations.'],
            ['Contact', 'For privacy questions, use the contact page and select Privacy enquiry as the subject.'],
        ],
    },
    terms: {
        title: 'Terms and conditions',
        intro: 'These terms apply when you use the marketplace to browse listings, buy products, request services, or communicate with vendors.',
        sections: [
            ['Using the marketplace', 'Provide accurate account and order details, keep your sign-in credentials private, and use the marketplace lawfully. We may restrict access when needed to protect users or the platform.'],
            ['Listings and orders', 'Vendors are responsible for the accuracy and fulfilment of their listings. An order is subject to the checkout details, payment status, and the applicable cancellation or return process shown for that order.'],
            ['Payments and fulfilment', 'Payments are processed using the method selected at checkout. Delivery estimates and service availability depend on the vendor and the order details. Contact support if an order needs attention.'],
            ['Marketplace role', 'The marketplace provides tools for customers and independent vendors to find and fulfil transactions. Unless explicitly stated, vendors are not employees of the marketplace.'],
            ['Changes and contact', 'We may update these terms as marketplace features change. Continued use after an update means you accept the current terms. Contact us with questions before using a service or placing an order.'],
        ],
    },
};

function AgentApplication({ services, billingMode, authUser }) {
    const form = useForm({
        first_name: authUser?.first_name || '',
        last_name: authUser?.last_name || '',
        email: authUser?.email || '',
        password: '',
        password_confirmation: '',
        business_name: '',
        phone: authUser?.phone || '',
        address: '',
        state: '',
        description: '',
        service_selections: [],
    });
    const requiresSubscription = ['subscription', 'subscription_commission'].includes(billingMode);

    function selectService(service, checked) {
        const selected = form.data.service_selections.filter((entry) => Number(entry.service_id) !== Number(service.id));
        if (checked) selected.push({ service_id: service.id, package_id: service.packages?.[0]?.id || '' });
        form.setData('service_selections', selected);
    }

    function selectPackage(serviceId, packageId) {
        form.setData('service_selections', form.data.service_selections.map((entry) => (
            Number(entry.service_id) === Number(serviceId) ? { ...entry, package_id: packageId } : entry
        )));
    }

    function submit(event) {
        event.preventDefault();
        form.post('/become-vendor');
    }

    return (
        <>
            <section className="marketplace-agent-benefit-grid" aria-label="Agent benefits">
                {[
                    ['Reach more customers', 'Put your products and services in front of shoppers across the marketplace.'],
                    ['Manage in one place', 'Use a single dashboard to manage listings, orders, and customer requests.'],
                    ['Choose your services', 'Select the service categories that best fit your business.'],
                    ['Get guided support', 'Our team will help you through account verification and getting started.'],
                ].map(([title, body], index) => (
                    <article className="marketplace-public-card" key={title}>
                        <span className="marketplace-agent-benefit-number">0{index + 1}</span>
                        <h2>{title}</h2>
                        <p>{body}</p>
                    </article>
                ))}
            </section>
            <form className="marketplace-agent-application" onSubmit={submit}>
                <section className="marketplace-public-card marketplace-agent-service-plans">
                    <div className="marketplace-agent-section-heading">
                        <div><h2>Choose the services you want to sell</h2><p>Select one or more services. {requiresSubscription ? 'Choose a plan for each service to see your invoice total.' : 'Your selected services are covered by free listing.'}</p></div>
                        <span>{requiresSubscription ? 'Plans billed per service' : 'Free listing'}</span>
                    </div>
                    {services.length ? (
                        <div className="marketplace-agent-service-grid">
                            {services.map((service) => {
                                const selection = form.data.service_selections.find((entry) => Number(entry.service_id) === Number(service.id));
                                const plansAvailable = (service.packages || []).length > 0;
                                return (
                                    <article className={`marketplace-agent-service-card${selection ? ' is-selected' : ''}`} key={service.id}>
                                        <label className="marketplace-agent-service-choice">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(selection)}
                                                disabled={requiresSubscription && !plansAvailable}
                                                onChange={(event) => selectService(service, event.target.checked)}
                                            />
                                            <span><strong>{service.name}</strong><small>{service.description || 'List products and services in this category.'}</small></span>
                                        </label>
                                        {requiresSubscription && selection && plansAvailable && (
                                            <label className="marketplace-agent-plan-choice">Select a plan
                                                <select value={selection.package_id} onChange={(event) => selectPackage(service.id, event.target.value)} required>
                                                    {service.packages.map((plan) => (
                                                        <option value={plan.id} key={plan.id}>{plan.name} · ₦{Number(plan.price).toLocaleString()} / {plan.duration_days} days</option>
                                                    ))}
                                                </select>
                                            </label>
                                        )}
                                        {requiresSubscription && !plansAvailable && <small className="marketplace-agent-unavailable">Plans are not currently available for this service.</small>}
                                        {!requiresSubscription && selection && <small className="marketplace-agent-free-label">Free listing</small>}
                                    </article>
                                );
                            })}
                        </div>
                    ) : <p>No services are currently available for registration.</p>}
                    {form.errors.service_selections && <p className="marketplace-form-error">{form.errors.service_selections}</p>}
                    {Object.entries(form.errors).filter(([key]) => key.startsWith('service_selections.')).map(([key, error]) => <p className="marketplace-form-error" key={key}>{error}</p>)}
                </section>
                <section className="marketplace-public-card marketplace-agent-application-details">
                    <h2>Create your agent account</h2>
                    <p>Your account is created immediately. Unless auto-approval is enabled, your profile remains pending verification.</p>
                    {!authUser && (
                        <div className="marketplace-form-row">
                            <label>First name<input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} required autoComplete="given-name" /></label>
                            <label>Last name<input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} required autoComplete="family-name" /></label>
                        </div>
                    )}
                    <div className="marketplace-form-row">
                        <label>{authUser ? 'Account email' : 'Email address'}<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required={!authUser} readOnly={Boolean(authUser)} /></label>
                        <label>Business phone<input type="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} /></label>
                    </div>
                    {!authUser && (
                        <div className="marketplace-form-row">
                            <label>Password<input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} minLength="8" required autoComplete="new-password" /></label>
                            <label>Confirm password<input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} minLength="8" required autoComplete="new-password" /></label>
                        </div>
                    )}
                    <label>Business name<input value={form.data.business_name} onChange={(event) => form.setData('business_name', event.target.value)} required /></label>
                    <div className="marketplace-form-row">
                        <label>Business address<input value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} /></label>
                        <label>State<input value={form.data.state} onChange={(event) => form.setData('state', event.target.value)} /></label>
                    </div>
                    <label>About your business<textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="4" maxLength="2000" /></label>
                    {Object.entries(form.errors).filter(([key]) => !key.startsWith('service_selections.')).map(([key, error]) => <p className="marketplace-form-error" key={key}>{error}</p>)}
                    <button className="marketplace-public-button" disabled={form.processing || services.length === 0 || form.data.service_selections.length === 0}>
                        {form.processing ? 'Creating account…' : 'Create agent account'}
                    </button>
                </section>
            </form>
        </>
    );
}

function AboutUs({ content, platform }) {
    const stats = content?.stats || [];
    const values = content?.values || [];
    const buyerSteps = content?.buyer_steps || [];
    const agentSteps = content?.agent_steps || [];
    const milestones = content?.milestones || [];
    const team = content?.team_members || [];
    const faqs = content?.faqs || [];

    return (
        <main className="marketplace-public-page marketplace-container marketplace-about-page">
            <Head title="About us" />
            <header className="marketplace-public-heading marketplace-about-breadcrumb">
                <p><Link href="/">Home</Link> · About us</p>
                <h1>About {platform?.name || 'our marketplace'}</h1>
                <p>{platform?.tagline || 'A more connected marketplace for everyday needs.'}</p>
            </header>

            <section className="marketplace-about-hero">
                <div className="marketplace-about-hero-copy">
                    <span className="marketplace-about-eyebrow">{content?.eyebrow}</span>
                    <h2>{content?.hero_title}</h2>
                    <p>{content?.hero_body}</p>
                    <div className="marketplace-about-actions">
                        <Link className="marketplace-public-button" href="/shop">Explore the marketplace</Link>
                        <Link className="marketplace-about-secondary-button" href="/become-vendor">Become an agent <span aria-hidden="true">↗</span></Link>
                    </div>
                </div>
                <div className="marketplace-about-stat-grid">
                    {stats.map((stat, index) => (
                        <article key={`${stat.label}-${index}`}>
                            <strong>{stat.value}</strong>
                            <span>{stat.label}</span>
                        </article>
                    ))}
                </div>
                <span className="marketplace-about-orb marketplace-about-orb-one" aria-hidden="true" />
                <span className="marketplace-about-orb marketplace-about-orb-two" aria-hidden="true" />
            </section>

            <section className="marketplace-about-story marketplace-public-card">
                <div className="marketplace-about-story-visual">
                    {content?.story_photo ? <img src={content.story_photo} alt={content.story_name || 'Marketplace founder'} /> : (
                        <div className="marketplace-about-story-placeholder" aria-hidden="true">
                            <span>{(platform?.name || 'M').slice(0, 1)}</span>
                            <small>Built on trust. Made for everyone.</small>
                        </div>
                    )}
                    {(content?.story_name || content?.story_role) && (
                        <div className="marketplace-about-story-caption">
                            <strong>{content.story_name}</strong>
                            <span>{content.story_role}</span>
                            {content.story_link && <a href={content.story_link} target="_blank" rel="noreferrer">Connect with our founder ↗</a>}
                        </div>
                    )}
                </div>
                <div className="marketplace-about-story-copy">
                    <span className="marketplace-about-section-kicker">The people and purpose behind the platform</span>
                    <h2>{content?.story_heading}</h2>
                    <p>{content?.story_bio}</p>
                    {content?.story_quote && <blockquote>{content.story_quote}</blockquote>}
                </div>
            </section>

            <section className="marketplace-about-section">
                <div className="marketplace-about-section-heading">
                    <span className="marketplace-about-section-kicker">The principles that guide us</span>
                    <h2>What we stand for</h2>
                </div>
                <div className="marketplace-about-values">
                    {values.map((item, index) => (
                        <article className="marketplace-public-card" key={`${item.title}-${index}`}>
                            <span className="marketplace-about-value-index">0{index + 1}</span>
                            <h3>{item.title}</h3>
                            <p>{item.description}</p>
                        </article>
                    ))}
                </div>
            </section>

            <section className="marketplace-about-paths">
                {[
                    { title: content?.buyers_title, steps: buyerSteps, label: 'A simpler way to shop', className: 'buyers' },
                    { title: content?.agents_title, steps: agentSteps, label: 'Room to grow your business', className: 'agents' },
                ].map((path) => (
                    <article className={`marketplace-public-card marketplace-about-path is-${path.className}`} key={path.className}>
                        <span className="marketplace-about-section-kicker">{path.label}</span>
                        <h2>{path.title}</h2>
                        <ol>{path.steps.map((step, index) => <li key={`${index}-${step}`}><span>{String(index + 1).padStart(2, '0')}</span><p>{step}</p></li>)}</ol>
                    </article>
                ))}
            </section>

            {milestones.length > 0 && (
                <section className="marketplace-about-journey marketplace-public-card">
                    <div className="marketplace-about-section-heading">
                        <span className="marketplace-about-section-kicker">Progress, shaped by our community</span>
                        <h2>Our journey</h2>
                    </div>
                    <ol>
                        {milestones.map((item, index) => (
                            <li key={`${item.year}-${index}`}>
                                <span className="marketplace-about-journey-dot" />
                                <div><small>{item.year}</small><h3>{item.title}</h3><p>{item.description}</p></div>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {team.length > 0 && (
                <section className="marketplace-about-section">
                    <div className="marketplace-about-section-heading">
                        <span className="marketplace-about-section-kicker">The people building what comes next</span>
                        <h2>Meet our team</h2>
                    </div>
                    <div className="marketplace-about-team">
                        {team.map((member, index) => (
                            <article className="marketplace-public-card" key={`${member.name}-${index}`}>
                                {member.photo ? <img src={member.photo} alt={member.name} loading="lazy" /> : <div className="marketplace-about-team-avatar">{member.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase()}</div>}
                                <h3>{member.name}</h3>
                                <span>{member.role}</span>
                                {member.bio && <p>{member.bio}</p>}
                                {member.link && <a href={member.link} target="_blank" rel="noreferrer">View profile ↗</a>}
                            </article>
                        ))}
                    </div>
                </section>
            )}

            {faqs.length > 0 && (
                <section className="marketplace-about-faq marketplace-public-card">
                    <div className="marketplace-about-section-heading">
                        <span className="marketplace-about-section-kicker">Good to know</span>
                        <h2>Frequently asked questions</h2>
                    </div>
                    {faqs.map((faq, index) => (
                        <details key={`${faq.question}-${index}`}>
                            <summary>{faq.question}<span aria-hidden="true">+</span></summary>
                            <p>{faq.answer}</p>
                        </details>
                    ))}
                </section>
            )}

            <section className="marketplace-about-company">
                <div><span className="marketplace-about-section-kicker">A little more about us</span><h2>{platform?.name || 'The company'}</h2></div>
                <div><strong>Visit</strong><p>{platform?.address || 'Our team serves customers and businesses across the region.'}</p>{platform?.hours && <small>{platform.hours}</small>}</div>
                <div><strong>Get in touch</strong>{platform?.phone && <a href={`tel:${platform.phone.replace(/\s/g, '')}`}>{platform.phone}</a>}{platform?.support_email && <a href={`mailto:${platform.support_email}`}>{platform.support_email}</a>}<Link href="/contact">Contact our team ↗</Link></div>
            </section>
        </main>
    );
}

export default function PublicPage({ page }) {
    const { props } = usePage();
    const content = legalContent[page];

    if (content) {
        return (
            <main className="marketplace-public-page marketplace-container">
                <Head title={content.title} />
                <header className="marketplace-public-heading"><p><Link href="/">Home</Link> · Information</p><h1>{content.title}</h1><p>{content.intro}</p></header>
                <article className="marketplace-public-card marketplace-legal-content">
                    {content.sections.map(([heading, body]) => <section key={heading}><h2>{heading}</h2><p>{body}</p></section>)}
                    <small>Last updated: November 2026</small>
                </article>
            </main>
        );
    }

    if (page === 'become-agent') {
        return (
            <main className="marketplace-public-page marketplace-container">
                <Head title="Become an agent" />
                <header className="marketplace-public-heading"><p><Link href="/">Home</Link> · Marketplace</p><h1>Grow your business with us</h1><p>Reach more customers, manage your listings, and receive orders through one marketplace.</p></header>
                <AgentApplication services={props.agentServices || []} billingMode={props.agentBillingMode} authUser={props.auth?.user} />
            </main>
        );
    }

    return <AboutUs content={props.aboutContent} platform={props.platform} />;
}
