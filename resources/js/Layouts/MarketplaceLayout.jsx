import { Link, router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';

const translations = {
    en: { home: 'Home', about: 'About Us', blog: 'Blog', vendor: 'Become an Agent', contact: 'Contact Us', track: 'Track Order', deals: 'Daily Deals', account: 'My Account', signUp: 'Sign Up', signIn: 'Sign In', search: 'Search products, services, vendors...', departments: 'Departments', services: 'Services', hotDeals: 'Hot deals', bestSellers: 'Best sellers', newArrivals: 'New arrivals', flashDeals: 'Flash deals', todayDeals: "Today's deals", featured: 'Featured', vendors: 'Shop by vendor', exploreServices: 'Services', partners: 'Our partners', blogLatest: 'From our blog', recentlyViewed: 'Recently viewed', mustHave: 'Must-have picks', shopNow: 'Shop now', addToCart: 'Add to cart', addedToCart: 'Added to cart', viewService: 'View service', viewAll: 'View all', newsletterTitle: 'Get deals in your inbox', subscribe: 'Subscribe', customerSupport: 'Customer Support', supportSubtitle: 'Real humans, real answers', callUs: 'Call or email us 24/7', callSubtitle: 'We are here whenever you need us', fastDelivery: 'Fast Delivery', secureShopping: 'Secure Shopping', securePayments: 'Secure Payments', easyReturns: 'Easy Returns' },
    fr: { home: 'Accueil', about: 'À propos', blog: 'Blog', vendor: 'Devenir agent', contact: 'Contactez-nous', track: 'Suivre ma commande', deals: 'Offres du jour', account: 'Mon compte', signUp: "S'inscrire", signIn: 'Se connecter', search: 'Rechercher produits, services, vendeurs...', departments: 'Rayons', services: 'Services', hotDeals: 'Offres du moment', bestSellers: 'Meilleures ventes', newArrivals: 'Nouveautés', flashDeals: 'Ventes flash', todayDeals: 'Offres du jour', featured: 'À la une', vendors: 'Acheter par vendeur', exploreServices: 'Services', partners: 'Nos partenaires', blogLatest: 'Notre blog', recentlyViewed: 'Vus récemment', mustHave: 'À découvrir', shopNow: 'Acheter', addToCart: 'Ajouter au panier', addedToCart: 'Ajouté au panier', viewService: 'Voir le service', viewAll: 'Tout voir', newsletterTitle: 'Recevez nos offres par e-mail', subscribe: "S'abonner", customerSupport: 'Service client', supportSubtitle: 'Des réponses humaines', callUs: 'Appelez-nous 24 h/24', callSubtitle: 'Nous sommes là pour vous', fastDelivery: 'Livraison rapide', secureShopping: 'Achats sécurisés', securePayments: 'Paiements sécurisés', easyReturns: 'Retours faciles' },
    ar: { home: 'الرئيسية', about: 'من نحن', blog: 'المدونة', vendor: 'كن وكيلاً', contact: 'اتصل بنا', track: 'تتبع الطلب', deals: 'عروض اليوم', account: 'حسابي', signUp: 'إنشاء حساب', signIn: 'تسجيل الدخول', search: 'ابحث عن منتجات وخدمات وبائعين...', departments: 'الأقسام', services: 'الخدمات', hotDeals: 'عروض ساخنة', bestSellers: 'الأكثر مبيعاً', newArrivals: 'وصل حديثاً', flashDeals: 'عروض سريعة', todayDeals: 'عروض اليوم', featured: 'مميز', vendors: 'تسوق حسب البائع', exploreServices: 'الخدمات', partners: 'شركاؤنا', blogLatest: 'من مدونتنا', recentlyViewed: 'شوهد مؤخراً', mustHave: 'اختيارات مميزة', shopNow: 'تسوق الآن', addToCart: 'أضف إلى السلة', addedToCart: 'تمت الإضافة إلى السلة', viewService: 'عرض الخدمة', viewAll: 'عرض الكل', newsletterTitle: 'احصل على العروض في بريدك', subscribe: 'اشترك', customerSupport: 'دعم العملاء', supportSubtitle: 'إجابات من أشخاص حقيقيين', callUs: 'اتصل بنا على مدار الساعة', callSubtitle: 'نحن هنا لمساعدتك', fastDelivery: 'توصيل سريع', secureShopping: 'تسوق آمن', securePayments: 'مدفوعات آمنة', easyReturns: 'إرجاع سهل' },
};
const localeContext = createContext(null);

export function useMarketplaceLocale() {
    const locale = useContext(localeContext);
    if (!locale) throw new Error('useMarketplaceLocale must be used inside MarketplaceLayout');
    return locale;
}

const readCartCount = () => {
    try {
        const cart = JSON.parse(localStorage.getItem('mm.cart') || '[]');
        return cart.reduce((total, item) => total + Math.max(0, Number(item.quantity) || 0), 0);
    } catch {
        return 0;
    }
};
const currencyOptions = [
    { code: 'NGN', symbol: '₦', rate: 1, decimals: 0 },
    { code: 'USD', symbol: '$', rate: 0.00065, decimals: 2 },
    { code: 'EUR', symbol: '€', rate: 0.0006, decimals: 2 },
    { code: 'GBP', symbol: '£', rate: 0.00051, decimals: 2 },
];
const languageOptions = [
    { code: 'en', label: '🇬🇧 English', locale: 'en-NG' },
    { code: 'fr', label: '🇫🇷 Français', locale: 'fr-FR' },
    { code: 'ar', label: '🇸🇦 العربية', locale: 'ar-EG' },
];

export default function MarketplaceLayout({ children }) {
    const { auth, platform, storefrontNavigation } = usePage().props;
    const [cartCount, setCartCount] = useState(0);
    const [cartOpen, setCartOpen] = useState(false);
    const cartButtonRef = useRef(null);
    const [menuOpen, setMenuOpen] = useState(false);
    const [departmentsOpen, setDepartmentsOpen] = useState(false);
    const [activeDepartment, setActiveDepartment] = useState(null);
    const [search, setSearch] = useState('');
    const [currency, setCurrency] = useState('NGN');
    const [language, setLanguage] = useState('en');
    const logo = platform?.brand?.logo;
    const accountUrl = auth?.user
        ? (auth.user.roles?.includes('agent') ? '/dashboard' : '/account/dashboard')
        : '/login';
    const closeCart = useCallback(() => {
        setCartOpen(false);
        cartButtonRef.current?.focus();
    }, []);

    useEffect(() => {
        const updateCart = () => setCartCount(readCartCount());
        setCurrency(localStorage.getItem('mm.currency') || platform?.currency || 'NGN');
        setLanguage(localStorage.getItem('mm.language') || platform?.default_language || 'en');
        updateCart();
        window.addEventListener('storage', updateCart);
        window.addEventListener('mm-cart-updated', updateCart);
        const closeDepartments = () => setDepartmentsOpen(false);
        window.addEventListener('scroll', closeDepartments, { passive: true });
        return () => {
            window.removeEventListener('storage', updateCart);
            window.removeEventListener('mm-cart-updated', updateCart);
            window.removeEventListener('scroll', closeDepartments);
        };
    }, [platform?.currency, platform?.default_language]);

    useEffect(() => {
        document.documentElement.lang = language;
        document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';
    }, [language]);

    function chooseCurrency(event) {
        const code = event.target.value;
        setCurrency(code);
        localStorage.setItem('mm.currency', code);
    }

    function chooseLanguage(event) {
        const code = event.target.value;
        setLanguage(code);
        localStorage.setItem('mm.language', code);
    }

    const activeCurrency = currencyOptions.find((option) => option.code === currency) || currencyOptions[0];
    const activeLanguage = languageOptions.find((option) => option.code === language) || languageOptions[0];
    const departments = storefrontNavigation?.departments || [];
    const services = storefrontNavigation?.services || [];
    const vendors = storefrontNavigation?.vendors || [];
    const selectedDepartment = activeDepartment === 'services'
        ? null
        : departments.find((department) => department.slug === activeDepartment) || departments[0];
    const locale = {
        language,
        currency,
        text: (key) => (translations[language] || translations.en)[key] || translations.en[key] || key,
        formatPrice: (amount) => `${activeCurrency.symbol}${new Intl.NumberFormat(activeLanguage.locale, {
            minimumFractionDigits: activeCurrency.decimals,
            maximumFractionDigits: activeCurrency.decimals,
        }).format(Number(amount || 0) * activeCurrency.rate)}`,
    };

    function submitSearch(event) {
        event.preventDefault();
        const term = search.trim();
        if (term) router.get('/shop', { search: term });
    }

    return (
        <div className="marketplace-app">
            <header className="marketplace-header">
                <div className="marketplace-topbar">
                    <div className="marketplace-container marketplace-topbar-inner">
                        <p><strong>{platform?.name || 'MegaMarket'}</strong><span>{platform?.tagline}</span></p>
                        <nav aria-label="Quick links">
                            <label className="marketplace-locale-picker"><span className="visually-hidden">Currency</span><select aria-label="Currency" value={currency} onChange={chooseCurrency}>{currencyOptions.map((option) => <option key={option.code} value={option.code}>{option.symbol} {option.code}</option>)}</select></label>
                            <label className="marketplace-locale-picker"><span className="visually-hidden">Language</span><select aria-label="Language" value={language} onChange={chooseLanguage}>{languageOptions.map((option) => <option key={option.code} value={option.code}>{option.label}</option>)}</select></label>
                            <Link href="/blog">{locale.text('blog')}</Link>
                            <Link href="/contact">{locale.text('contact')}</Link>
                            <Link href={accountUrl}>{auth?.user?.first_name || locale.text('account')}</Link>
                            {!auth?.user && <><Link href="/register">{locale.text('signUp')}</Link><Link href="/login">{locale.text('signIn')}</Link></>}
                        </nav>
                    </div>
                </div>
                <div className="marketplace-main-header">
                    <div className="marketplace-container marketplace-header-inner">
                        <button className="marketplace-menu-toggle" type="button" aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)}>
                            <MenuIcon /><span className="visually-hidden">Toggle navigation</span>
                        </button>
                        <Link className="marketplace-logo" href="/" aria-label={`${platform?.name || 'MegaMarket'} home`}>
                            {logo ? <img src={logo} alt={platform?.name || 'MegaMarket'} /> : <strong>{platform?.name || 'MegaMarket'}</strong>}
                        </Link>
                        <form className="marketplace-search" role="search" onSubmit={submitSearch}>
                            <label className="visually-hidden" htmlFor="marketplace-search">Search products and services</label>
                            <input id="marketplace-search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={locale.text('search')} />
                            <button type="submit"><SearchIcon /> Search</button>
                        </form>
                        <a className="marketplace-call" href={`tel:${(platform?.phone || '').replace(/\s/g, '')}`}>
                            <PhoneIcon />
                            <span><small>Call to order</small><strong>{platform?.phone}</strong></span>
                        </a>
                        <Link className="marketplace-header-action" href="/wishlist" aria-label="Wishlist"><HeartIcon /></Link>
                        <button
                            ref={cartButtonRef}
                            className="marketplace-header-action marketplace-cart-link"
                            type="button"
                            aria-label={`Open cart, ${cartCount} items`}
                            aria-expanded={cartOpen}
                            aria-controls="marketplace-cart-drawer"
                            onClick={() => setCartOpen(true)}
                        >
                            <CartIcon />{cartCount > 0 && <span className="marketplace-cart-count">{cartCount}</span>}
                        </button>
                    </div>
                </div>
                <nav
                    className={`marketplace-nav${menuOpen ? ' is-open' : ''}`}
                    aria-label="Main navigation"
                    onMouseLeave={() => setDepartmentsOpen(false)}
                    onBlur={(event) => {
                        if (!event.currentTarget.contains(event.relatedTarget)) setDepartmentsOpen(false);
                    }}
                >
                    <div className="marketplace-container marketplace-nav-inner">
                        <div
                            className="marketplace-department-menu"
                            onMouseEnter={() => setDepartmentsOpen(true)}
                            onFocus={() => setDepartmentsOpen(true)}
                        >
                            <button
                                type="button"
                                className="marketplace-departments"
                                aria-expanded={departmentsOpen}
                                aria-controls="marketplace-departments-dropdown"
                                onClick={() => setDepartmentsOpen((open) => !open)}
                            >
                                <span aria-hidden="true">▦</span>
                                <span>{locale.text('departments')} &amp; {locale.text('services')}</span>
                                <span className={`marketplace-department-chevron${departmentsOpen ? ' is-open' : ''}`} aria-hidden="true">⌄</span>
                            </button>
                        </div>
                        <Link href="/" onClick={() => setMenuOpen(false)}>{locale.text('home')}</Link>
                        <Link href="/shop" onClick={() => setMenuOpen(false)}>Shop</Link>
                        <Link href="/services" onClick={() => setMenuOpen(false)}>Services</Link>
                        <Link href="/about" onClick={() => setMenuOpen(false)}>{locale.text('about')}</Link>
                        <Link href="/blog" onClick={() => setMenuOpen(false)}>{locale.text('blog')}</Link>
                        <Link href="/become-vendor" onClick={() => setMenuOpen(false)}>{locale.text('vendor')}</Link>
                        <Link href="/contact" onClick={() => setMenuOpen(false)}>{locale.text('contact')}</Link>
                        <span className="marketplace-nav-spacer" />
                        <Link href="/track-order" onClick={() => setMenuOpen(false)}>♧ &nbsp; {locale.text('track')}</Link>
                        <Link className="marketplace-deals-link" href="/deals" onClick={() => setMenuOpen(false)}>⚡ &nbsp; {locale.text('deals')}</Link>
                    </div>
                    {departmentsOpen && <div
                        id="marketplace-departments-dropdown"
                        className="marketplace-mega-dropdown"
                        onMouseEnter={() => setDepartmentsOpen(true)}
                    >
                        <div className="marketplace-mega-department-list" role="list">
                            {departments.map((department) => (
                                <Link
                                    key={department.id}
                                    href={`/shop?category=${department.slug}`}
                                    className={selectedDepartment?.id === department.id ? 'is-active' : ''}
                                    onMouseEnter={() => setActiveDepartment(department.slug)}
                                    onFocus={() => setActiveDepartment(department.slug)}
                                    onClick={() => { setDepartmentsOpen(false); setMenuOpen(false); }}
                                >
                                    <DepartmentIcon icon={department.icon} />
                                    <span>{department.name}</span>
                                    <span className="marketplace-mega-chevron" aria-hidden="true">›</span>
                                </Link>
                            ))}
                            <button
                                type="button"
                                className={activeDepartment === 'services' ? 'is-active' : ''}
                                onMouseEnter={() => setActiveDepartment('services')}
                                onFocus={() => setActiveDepartment('services')}
                                onClick={() => setActiveDepartment('services')}
                            >
                                <DepartmentIcon icon="services" />
                                <span>{locale.text('services')}</span>
                                <span className="marketplace-mega-chevron" aria-hidden="true">›</span>
                            </button>
                        </div>
                        <div className="marketplace-mega-content">
                            {selectedDepartment ? <>
                                <section className="marketplace-mega-column">
                                    <h2>{selectedDepartment.name}</h2>
                                    <div className="marketplace-mega-links">
                                        {(selectedDepartment.children || []).map((category) => <Link
                                            key={category.id}
                                            href={`/shop?category=${category.slug}`}
                                            onClick={() => setDepartmentsOpen(false)}
                                        >{category.name}</Link>)}
                                        <Link href={`/shop?category=${selectedDepartment.slug}`} className="marketplace-mega-all">View all {selectedDepartment.name}</Link>
                                    </div>
                                </section>
                                <section className="marketplace-mega-column marketplace-mega-service-column">
                                    <h2>{locale.text('services')}</h2>
                                    <div className="marketplace-mega-links marketplace-mega-service-links">
                                        {services.slice(0, 8).map((service) => <Link
                                            key={service.id}
                                            href={`/services?service=${service.slug}`}
                                            onClick={() => setDepartmentsOpen(false)}
                                        >{service.name}</Link>)}
                                        {vendors.slice(0, Math.max(0, 8 - services.length)).map((vendor) => <Link
                                            key={vendor.id}
                                            href={`/services?vendor=${vendor.slug}`}
                                            onClick={() => setDepartmentsOpen(false)}
                                        >{vendor.name}</Link>)}
                                        <Link href="/services" className="marketplace-mega-all">View all services</Link>
                                    </div>
                                </section>
                            </> : <section className="marketplace-mega-column marketplace-mega-all-services">
                                <h2>{locale.text('services')}</h2>
                                <div className="marketplace-mega-links marketplace-mega-service-links">
                                    {services.map((service) => <Link
                                        key={service.id}
                                        href={`/services?service=${service.slug}`}
                                        onClick={() => setDepartmentsOpen(false)}
                                    >{service.name}</Link>)}
                                    {vendors.map((vendor) => <Link
                                        key={vendor.id}
                                        href={`/shop?vendor=${vendor.slug}`}
                                        onClick={() => setDepartmentsOpen(false)}
                                    >{vendor.name}</Link>)}
                                </div>
                            </section>}
                        </div>
                    </div>}
                </nav>
            </header>
            <localeContext.Provider value={locale}><div className="marketplace-page-content">{children}</div></localeContext.Provider>
            {cartOpen && <CartDrawer count={cartCount} onClose={closeCart} />}
            {cartCount > 0 && <div className="marketplace-mobile-sticky-cart"><span>🛒 &nbsp; {cartCount} items in your cart</span><Link href="/cart">View cart</Link></div>}
            <footer className="marketplace-footer">
                <div className="marketplace-container marketplace-footer-grid">
                    <section className="marketplace-footer-about">
                        <Link className="marketplace-footer-brand" href="/" aria-label={platform?.name || 'Home'}>
                            <img src={logo || platform?.brand?.icon || '/images/edp.png'} alt={platform?.name || 'Company logo'} />
                        </Link>
                        <p>{platform?.name || 'EDP Integrated Global Services Ltd'} is a multi-purpose, multi-agent marketplace for products and services — one checkout, thousands of verified agents.</p>
                        <address>
                            {platform?.address && <span><FooterContactIcon type="location" /><span>{platform.address}</span></span>}
                            {platform?.phone && <a href={`tel:${platform.phone.replace(/\s/g, '')}`}><FooterContactIcon type="phone" /><span>{platform.phone}</span></a>}
                            {platform?.support_email && <a href={`mailto:${platform.support_email}`}><FooterContactIcon type="email" /><span>{platform.support_email}</span></a>}
                        </address>
                    </section>
                    <FooterColumn title="Customer service" links={[[ 'Contact Us', '/contact' ], [ 'Track Order', '/track-order' ], [ 'Returns', '/contact' ], [ 'Refunds', '/contact' ], [ 'FAQs', '/about' ], [ 'Support', '/contact' ]]} />
                    <FooterColumn title="Marketplace" links={[[ 'Become an Agent', '/become-vendor' ], [ 'All Services', '/services' ], [ 'All Categories', '/shop' ], [ 'Deals', '/deals' ], [ 'Vendors', '/vendors' ], [ 'Compare', '/compare' ], [ 'Wishlist', '/wishlist' ]]} />
                    <FooterColumn title="Company" links={[[ 'About Us', '/about' ], [ 'Blog', '/blog' ], [ 'Privacy Policy', '/privacy-policy' ], [ 'Terms & Conditions', '/terms-and-conditions' ]]} />
                    <section className="marketplace-footer-social-payments">
                        <h2>Follow Us</h2>
                        <div className="marketplace-social-list" aria-label="Social networks">
                            {['facebook', 'instagram', 'twitter', 'linkedin', 'youtube'].map((network) => <span key={network} aria-label={network}>
                                <FooterSocialIcon network={network} />
                            </span>)}
                        </div>
                        <h2 className="marketplace-accept-heading">We Accept</h2>
                        <div className="marketplace-payment-list">
                            {(platform?.payment_methods || []).map((method) => <PaymentLogo key={method} method={method} />)}
                        </div>
                    </section>
                </div>
                <div className="marketplace-copyright">
                    <div className="marketplace-container">
                        <span>© {new Date().getFullYear()} {platform?.name || 'EDP Integrated Global Services Ltd'}. All rights reserved.</span>
                        <span>Multi-currency · Multi-language · RTL ready</span>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function CartDrawer({ count, onClose }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const closeButtonRef = useRef(null);

    useEffect(() => {
        let currentRequest = 0;
        let cancelled = false;
        const controller = new AbortController();

        async function loadCart() {
            const request = ++currentRequest;
            setLoading(true);
            setError('');

            let saved;
            try {
                saved = JSON.parse(localStorage.getItem('mm.cart') || '[]');
                if (!Array.isArray(saved)) throw new Error('Your saved cart data is not valid.');
            } catch (loadError) {
                if (!cancelled) {
                    setItems([]);
                    setError(loadError instanceof Error ? loadError.message : 'Your cart could not be loaded.');
                    setLoading(false);
                }
                return;
            }

            const validItems = saved.filter((item) => item && /^\d+$/.test(String(item.id)));
            if (!validItems.length) {
                if (!cancelled && request === currentRequest) {
                    setItems([]);
                    setLoading(false);
                }
                return;
            }

            try {
                const response = await fetch(`/storefront/listings?ids=${encodeURIComponent(validItems.map((item) => item.id).join(','))}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Your cart items could not be loaded. Please try again.');
                const listings = await response.json();
                const listingsById = new Map(listings.map((listing) => [String(listing.id), listing]));
                const resolved = validItems
                    .filter((item) => listingsById.has(String(item.id)))
                    .map((item) => ({
                        listing: listingsById.get(String(item.id)),
                        quantity: Math.min(99, Math.max(1, Number(item.quantity) || 1)),
                        selected_options: item.selected_options || {},
                    }));

                if (!cancelled && request === currentRequest) {
                    setItems(resolved);
                    setLoading(false);
                }
            } catch (loadError) {
                if (!cancelled && loadError.name !== 'AbortError') {
                    setError(loadError instanceof Error ? loadError.message : 'Your cart items could not be loaded.');
                    setLoading(false);
                }
            }
        }

        const onCartUpdated = () => { loadCart(); };
        loadCart();
        window.addEventListener('mm-cart-updated', onCartUpdated);
        window.addEventListener('storage', onCartUpdated);
        closeButtonRef.current?.focus();

        return () => {
            cancelled = true;
            controller.abort();
            window.removeEventListener('mm-cart-updated', onCartUpdated);
            window.removeEventListener('storage', onCartUpdated);
        };
    }, []);

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const onKeyDown = (event) => {
            if (event.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKeyDown);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [onClose]);

    const subtotal = items.reduce((total, item) => total + Number(item.listing.base_price) * item.quantity, 0);
    const checkoutItems = items.map(({ listing, quantity, selected_options }) => ({
        listing_id: Number(listing.id),
        quantity,
        selected_options: selected_options || {},
    }));
    const cartQuery = encodeURIComponent(JSON.stringify(checkoutItems));
    const viewCartUrl = `/cart?items=${cartQuery}`;
    const checkoutUrl = `/checkout?items=${cartQuery}`;

    function saveCart(nextItems) {
        setItems(nextItems);
        localStorage.setItem('mm.cart', JSON.stringify(nextItems.map(({ listing, quantity, selected_options }) => ({
            id: listing.id,
            quantity,
            selected_options: selected_options || {},
        }))));
        window.dispatchEvent(new Event('mm-cart-updated'));
    }

    function changeQuantity(id, amount) {
        saveCart(items.map((item) => {
            if (String(item.listing.id) !== String(id)) return item;
            const max = item.listing.type === 'service'
                ? 99
                : Math.max(1, Math.min(99, Number(item.listing.stock) || 1));
            return { ...item, quantity: Math.min(max, Math.max(1, item.quantity + amount)) };
        }));
    }

    return (
        <div className="marketplace-cart-drawer-layer">
            <button className="marketplace-cart-drawer-backdrop" type="button" aria-label="Close cart" onClick={onClose} />
            <aside
                className="marketplace-cart-drawer"
                id="marketplace-cart-drawer"
                role="dialog"
                aria-modal="true"
                aria-labelledby="marketplace-cart-title"
            >
                <header className="marketplace-cart-drawer-header">
                    <h2 id="marketplace-cart-title"><CartIcon /> Your Cart ({count})</h2>
                    <button ref={closeButtonRef} type="button" className="marketplace-cart-drawer-close" aria-label="Close cart" onClick={onClose}>×</button>
                </header>
                <div className="marketplace-cart-drawer-content" aria-live="polite">
                    {loading ? <p className="marketplace-cart-drawer-message">Loading your cart…</p> : error ? (
                        <p className="marketplace-cart-drawer-message is-error">{error}</p>
                    ) : items.length === 0 ? (
                        <p className="marketplace-cart-drawer-message">Your cart is empty.</p>
                    ) : items.map(({ listing, quantity, selected_options }) => (
                        <article className="marketplace-cart-drawer-item" key={listing.id}>
                            <Link href={`/products/${listing.slug}`} onClick={onClose}>
                                <img src={listing.image} alt="" />
                            </Link>
                            <div className="marketplace-cart-drawer-item-info">
                                <Link href={`/products/${listing.slug}`} onClick={onClose} className="marketplace-cart-drawer-item-name">{listing.name}</Link>
                                <span>{listing.vendorName}</span>
                                {selected_options?.size && <small>Size: {selected_options.size}</small>}
                                {selected_options?.color && <small>Color: {selected_options.color}</small>}
                                <strong>₦{Number(listing.base_price).toLocaleString()}</strong>
                                <div className="marketplace-cart-drawer-item-controls">
                                    <div className="marketplace-cart-drawer-quantity" aria-label={`Quantity ${quantity}`}>
                                        <button type="button" aria-label={`Decrease ${listing.name} quantity`} disabled={quantity <= 1} onClick={() => changeQuantity(listing.id, -1)}>−</button>
                                        <span>{quantity}</span>
                                        <button type="button" aria-label={`Increase ${listing.name} quantity`} disabled={listing.type !== 'service' && quantity >= Number(listing.stock)} onClick={() => changeQuantity(listing.id, 1)}>+</button>
                                    </div>
                                    <button type="button" className="marketplace-cart-drawer-remove" onClick={() => saveCart(items.filter((item) => String(item.listing.id) !== String(listing.id)))}>Remove</button>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
                <footer className="marketplace-cart-drawer-footer">
                    <div className="marketplace-cart-drawer-subtotal"><span>Subtotal</span><strong>₦{subtotal.toLocaleString()}</strong></div>
                    <div className="marketplace-cart-drawer-actions">
                        <Link className="marketplace-cart-drawer-view" href={viewCartUrl} onClick={onClose}>View Cart</Link>
                        <Link className="marketplace-cart-drawer-checkout" href={checkoutUrl} onClick={onClose}>Checkout</Link>
                    </div>
                </footer>
            </aside>
        </div>
    );
}

function FooterColumn({ title, links }) {
    return (
        <section className="marketplace-footer-column">
            <h2>{title}</h2>
            <ul>{links.map(([label, href]) => <li key={label}><Link href={href}>{label}</Link></li>)}</ul>
        </section>
    );
}

function FooterContactIcon({ type }) {
    const paths = {
        location: <><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></>,
        phone: <><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 2.8a2 2 0 0 1-.6 1.7L7.7 9.5a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 1.7-.6l2.8.5a2 2 0 0 1 1.7 2Z" /></>,
        email: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></>,
    };
    return <svg className="marketplace-footer-contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[type]}</svg>;
}

function FooterSocialIcon({ network }) {
    const icons = {
        facebook: <path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1Z" />,
        instagram: <><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><path d="M17.5 6.5h.01" /></>,
        twitter: <path d="M22 5.9a8 8 0 0 1-2.4.7 4.2 4.2 0 0 0 1.8-2.3 8.3 8.3 0 0 1-2.7 1 4.2 4.2 0 0 0-7.2 3.8 12 12 0 0 1-8.7-4.4 4.2 4.2 0 0 0 1.3 5.6 4.2 4.2 0 0 1-1.9-.5v.1a4.2 4.2 0 0 0 3.4 4.1 4.2 4.2 0 0 1-1.9.1 4.2 4.2 0 0 0 3.9 2.9A8.4 8.4 0 0 1 2 18.8a11.8 11.8 0 0 0 6.4 1.9c7.7 0 11.9-6.4 11.9-11.9v-.5A8.5 8.5 0 0 0 22 5.9Z" />,
        linkedin: <><path d="M5 9v10M5 5v.01M10 19v-6a4 4 0 0 1 8 0v6M10 10v9" /><circle cx="5" cy="5" r="1.5" /></>,
        youtube: <><rect x="2" y="5" width="20" height="14" rx="4" /><path d="m10 9 5 3-5 3z" /></>,
    };
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{icons[network]}</svg>;
}

function PaymentLogo({ method }) {
    const logos = {
        paystack: <><span className="payment-logo-symbol payment-logo-paystack">P</span><span>Paystack</span></>,
        flutterwave: <><span className="payment-logo-symbol payment-logo-flutterwave">F</span><span>flutterwave</span></>,
        remita: <><span className="payment-logo-symbol payment-logo-remita">R</span><span>Remita</span></>,
        bank_transfer: <><svg viewBox="0 0 28 24" aria-hidden="true"><path d="M2 8 14 2l12 6v2H2zm2 3h3v8H4zm7 0h3v8h-3zm7 0h3v8h-3zM2 21h24v2H2z" /></svg><span>Bank Transfer</span></>,
        pay_on_delivery: <><svg viewBox="0 0 28 24" aria-hidden="true"><path d="M3 7 14 2l11 5v14H3zm2 1v11h18V8l-9-4zM8 11h12v2H8zm0 4h7v2H8z" /><circle cx="20" cy="17" r="4" /><path d="M20 14.5v5m1.5-3.5c0-.7-.6-1-1.5-1s-1.5.3-1.5 1 .6 1 1.5 1 1.5.3 1.5 1-.6 1-1.5 1-1.5-.3-1.5-1" /></svg><span>Pay on Delivery</span></>,
    };
    const content = logos[method];
    return content ? <span className={`marketplace-payment-logo marketplace-payment-logo-${method}`} aria-label={method.replaceAll('_', ' ')}>{content}</span> : null;
}

function MenuIcon() {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>;
}

function SearchIcon() {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>;
}

function PhoneIcon() {
    return <svg className="marketplace-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.2 3.5h2.5l1.2 4.2-1.8 1.8a15 15 0 0 0 5.4 5.4l1.8-1.8 4.2 1.2v2.5c0 1.1-.9 2-2 2C10 18.8 5.2 14 5.2 5.5c0-1.1.9-2 2-2Z" /></svg>;
}

function HeartIcon() {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.7c0 4.2-8.8 10-8.8 10S3.2 12.9 3.2 8.7A4.2 4.2 0 0 1 11 6.4l1 1 1-1a4.2 4.2 0 0 1 7.8 2.3Z" /></svg>;
}

function CartIcon() {
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 8H6" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>;
}

function DepartmentIcon({ icon }) {
    const name = (icon || '').toLowerCase();
    if (name.includes('fashion') || name.includes('shirt')) {
        return <svg className="marketplace-department-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m8 4 4 2 4-2 5 4-3 4-2-1v9H8v-9l-2 1-3-4 5-4Z" /></svg>;
    }
    if (name.includes('food') || name.includes('grocery') || name.includes('utensil')) {
        return <svg className="marketplace-department-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3v7m3-7v7M4 7h3m-1.5 3v11M14 3v18m0-18c3 2 4 5 4 9h-4" /></svg>;
    }
    if (name.includes('service')) {
        return <svg className="marketplace-department-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>;
    }
    return <svg className="marketplace-department-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2" /><path d="M10 18.5h4" /></svg>;
}
