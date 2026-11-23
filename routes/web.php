<?php

use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\Web\AgentRegistrationController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\PortalController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('storefront.shop');
Route::get('/services', [StorefrontController::class, 'services'])->name('storefront.services');
Route::get('/deals', [StorefrontController::class, 'deals'])->name('storefront.deals');
Route::get('/about', [StorefrontController::class, 'about'])->name('storefront.about');
Route::get('/contact', [StorefrontController::class, 'contact'])->name('storefront.contact');
Route::post('/contact', [StorefrontController::class, 'submitContact'])->middleware('throttle:6,1')->name('storefront.contact.submit');
Route::get('/become-vendor', [StorefrontController::class, 'becomeAgent'])->name('storefront.become-agent');
Route::post('/become-vendor', [AgentRegistrationController::class, 'register'])->middleware('throttle:6,1')->name('storefront.become-agent.register');
Route::get('/track-order', [StorefrontController::class, 'trackOrder'])->name('storefront.track-order');
Route::post('/track-order', [StorefrontController::class, 'lookupOrder'])->middleware('throttle:10,1')->name('storefront.track-order.lookup');
Route::get('/vendors', [StorefrontController::class, 'vendors'])->name('storefront.vendors');
Route::get('/compare', fn () => Inertia::render('Storefront/LocalListings', ['page' => 'compare']))->name('storefront.compare');
Route::get('/wishlist', fn () => Inertia::render('Storefront/LocalListings', ['page' => 'wishlist']))->name('storefront.wishlist');
Route::get('/privacy-policy', fn () => Inertia::render('Storefront/PublicPage', ['page' => 'privacy']))->name('storefront.privacy');
Route::get('/terms-and-conditions', fn () => Inertia::render('Storefront/PublicPage', ['page' => 'terms']))->name('storefront.terms');
Route::get('/blog', [StorefrontController::class, 'blog'])->name('storefront.blog');
Route::get('/blog/{slug}', [StorefrontController::class, 'blogPost'])->name('storefront.blog.show');
Route::get('/storefront/listings', [StorefrontController::class, 'hydrateListings'])->name('storefront.listings');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');
Route::post('/products/{slug}/reviews', [StorefrontController::class, 'submitListingReview'])
    ->middleware(['auth', 'role:customer', 'throttle:6,1'])->name('storefront.products.reviews.store');
Route::post('/newsletter', [StorefrontController::class, 'subscribe'])->middleware('throttle:10,1')->name('storefront.newsletter');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/cart', [CustomerController::class, 'cart'])->name('cart.show');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/account', [CustomerController::class, 'account'])->name('account.show');
    Route::get('/account/dashboard', [CustomerController::class, 'dashboard'])->name('account.dashboard');
    Route::get('/account/orders/{order}', [CustomerController::class, 'showOrder'])->name('account.orders.show');
    Route::get('/account/orders/{order}/receipt', [CustomerController::class, 'receipt'])->name('account.orders.receipt');
    Route::post('/account/wallet/deposits', [CustomerController::class, 'fundWallet'])->name('account.wallet.deposit');
    Route::get('/checkout', [CustomerController::class, 'checkout'])->name('checkout.show');
    Route::post('/checkout', [CustomerController::class, 'placeOrder'])->name('checkout.place');
    Route::post('/checkout/address', [CustomerController::class, 'storeAddress'])->name('checkout.address');
    Route::get('/checkout/complete', [CustomerController::class, 'complete'])->name('checkout.complete');
    Route::get('/payments/return/{reference}', [CustomerController::class, 'paymentReturn'])->name('payments.return');
    Route::post('/payments/{reference}/proof', [CustomerController::class, 'uploadPaymentProof'])->name('payments.proof');
});

Route::middleware('auth')->prefix('dashboard')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [PortalController::class, 'orders'])->name('orders');
});

Route::middleware(['auth', 'role:agent'])->prefix('agent-invoices')->name('agent-invoices.')->group(function () {
    Route::get('/payments/return/{reference}', [AgentRegistrationController::class, 'returnFromGateway'])->name('return');
    Route::get('/{invoice}', [AgentRegistrationController::class, 'show'])->name('show');
    Route::post('/{invoice}/pay', [AgentRegistrationController::class, 'pay'])->name('pay');
    Route::post('/{invoice}/proof', [AgentRegistrationController::class, 'uploadProof'])->name('proof');
});
