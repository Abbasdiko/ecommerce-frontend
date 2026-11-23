<?php

use App\Http\Controllers\Api\Admin\AboutPageController as AdminAboutPageController;
use App\Http\Controllers\Api\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Api\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Api\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Api\Admin\ListingOptionController;
use App\Http\Controllers\Api\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentConfigController as AdminPaymentConfigController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\Agent\CommissionController as AgentCommissionController;
use App\Http\Controllers\Api\Agent\CouponController as AgentCouponController;
use App\Http\Controllers\Api\Agent\InventoryController as AgentInventoryController;
use App\Http\Controllers\Api\Agent\KycController;
use App\Http\Controllers\Api\Agent\OrderController as AgentOrderController;
use App\Http\Controllers\Api\Agent\ReportController as AgentReportController;
use App\Http\Controllers\Api\Agent\ReviewController as AgentReviewController;
use App\Http\Controllers\Api\Agent\SettingsController as AgentSettingsController;
use App\Http\Controllers\Api\Agent\WalletController as AgentWalletController;
use App\Http\Controllers\Api\Agent\WithdrawalController as AgentWithdrawalController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\BankAccountController;
use App\Http\Controllers\Api\Customer\CheckoutController;
use App\Http\Controllers\Api\Customer\CommissionController;
use App\Http\Controllers\Api\Customer\OrderController as MyOrderController;
use App\Http\Controllers\Api\Customer\ReviewController as MyReviewController;
use App\Http\Controllers\Api\Customer\SettingsController;
use App\Http\Controllers\Api\Customer\SettlementController;
use App\Http\Controllers\Api\Customer\WalletController as MyWalletController;
use App\Http\Controllers\Api\Customer\WishlistController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServicePackageController;
use App\Http\Controllers\Api\Storefront\CatalogController;
use App\Http\Controllers\Api\Storefront\ContentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- Public auth ----
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('auth/sso/exchange', [AuthController::class, 'exchangeSsoTicket'])->middleware('throttle:12,1');

    // ---- Payment gateway webhooks (public, signature-checked, idempotent) ----
    Route::post('payments/webhook/{gateway}', [PaymentController::class, 'webhook']);

    // ---- Public platform settings (single source of truth for every portal) ----
    Route::get('settings/public', [PublicSettingsController::class, 'show']);

    // ---- Storefront (public catalog for the React marketplace) ----
    Route::prefix('storefront')->group(function () {
        Route::get('home', [CatalogController::class, 'home']);
        Route::get('listings', [CatalogController::class, 'listings']);
        Route::get('listings/{slug}', [CatalogController::class, 'listing']);
        Route::get('vendors', [CatalogController::class, 'vendors']);
        Route::get('vendors/{slug}', [CatalogController::class, 'vendor']);
        Route::get('departments', [CatalogController::class, 'departments']);

        // Content
        Route::get('blog', [ContentController::class, 'blog']);
        Route::get('blog/{slug}', [ContentController::class, 'blogPost']);
        Route::get('services', [ContentController::class, 'services']);
        Route::post('newsletter', [ContentController::class, 'subscribe'])->middleware('throttle:10,1');
        Route::post('contact', [ContentController::class, 'contact'])->middleware('throttle:6,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', DashboardController::class);

        // ---- Users & roles ----
        Route::apiResource('users', UserController::class);
        Route::get('roles', [RoleController::class, 'index']);

        // ---- Customers ----
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

        // ---- Agents ----
        Route::get('agents', [AgentController::class, 'index']);
        Route::post('agents', [AgentController::class, 'store']); // agent self-onboarding
        Route::get('agents/{agent}', [AgentController::class, 'show']);
        Route::patch('agents/{agent}', [AgentController::class, 'update']);
        Route::delete('agents/{agent}', [AgentController::class, 'destroy']);
        Route::post('agents/{agent}/approve', [AgentController::class, 'approve']);
        Route::post('agents/{agent}/reject', [AgentController::class, 'reject']);
        Route::post('agents/{agent}/status', [AgentController::class, 'suspend']);

        // ---- Services & packages ----
        Route::apiResource('services', ServiceController::class);
        Route::get('services/{service}/packages', [ServicePackageController::class, 'index']);
        Route::post('services/{service}/packages', [ServicePackageController::class, 'store']);
        Route::patch('services/{service}/packages/{package}', [ServicePackageController::class, 'update']);
        Route::delete('services/{service}/packages/{package}', [ServicePackageController::class, 'destroy']);

        // ---- Subscriptions ----
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::post('subscriptions/{subscription}/pay', [SubscriptionController::class, 'pay']);
        Route::post('subscriptions/{subscription}/activate', [SubscriptionController::class, 'activate']);
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        // ---- Categories ----
        Route::apiResource('categories', CategoryController::class)->except('show');

        // ---- Listings ----
        Route::apiResource('listings', ListingController::class);
        Route::get('listing-options', [ListingOptionController::class, 'index']);
        Route::put('admin/listing-options', [ListingOptionController::class, 'update']);
        Route::get('listings/{listing}/reviews', [ListingController::class, 'reviews']);
        Route::put('listings/{listing}/reviews/{review}', [ListingController::class, 'updateReview']);
        Route::delete('listings/{listing}/reviews/{review}', [ListingController::class, 'destroyReview']);
        Route::post('listings/{listing}/approve', [ListingController::class, 'approve']);
        Route::post('listings/{listing}/reject', [ListingController::class, 'reject']);
        Route::post('listings/{listing}/resubmit', [ListingController::class, 'resubmit']);

        // ---- Audit logs ----
        Route::get('audit-logs', [AuditLogController::class, 'index']);

        // ---- Bank accounts (shared: customers for payouts, agents for withdrawals) ----
        Route::get('bank-accounts', [BankAccountController::class, 'index']);
        Route::post('bank-accounts', [BankAccountController::class, 'store']);
        Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update']);
        Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy']);

        // ---- Payments (shared) ----
        Route::get('payments/options', [PaymentController::class, 'options']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments/{reference}/verify', [PaymentController::class, 'verify']);
        Route::post('payments/{reference}/proof', [PaymentController::class, 'uploadProof']);

        // ---- Admin: bank transfers & withdrawals ----
        Route::get('admin/payments', [AdminPaymentController::class, 'index']);
        Route::post('admin/payments/{payment}/confirm', [AdminPaymentController::class, 'confirm']);
        Route::post('admin/payments/{payment}/reject', [AdminPaymentController::class, 'reject']);
        Route::get('admin/withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::post('admin/withdrawals/{withdrawal}/transition', [AdminWithdrawalController::class, 'transition']);
        Route::post('admin/withdrawals/{withdrawal}/paystack', [AdminWithdrawalController::class, 'paystack']);
        Route::post('admin/withdrawals/{withdrawal}/verify-paystack', [AdminWithdrawalController::class, 'verifyPaystack']);
        Route::post('admin/withdrawals/{withdrawal}/finalize-paystack', [AdminWithdrawalController::class, 'finalizePaystack']);

        // ---- Admin: orders ----
        Route::get('admin/orders', [AdminOrderController::class, 'index']);
        Route::get('admin/orders/{order}', [AdminOrderController::class, 'show']);
        Route::post('admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        // ---- Admin: refunds / returns / cancellations ----
        Route::get('admin/returns', [AdminRefundController::class, 'returns']);
        Route::post('admin/returns/{returnRequest}/approve', [AdminRefundController::class, 'approveReturn']);
        Route::post('admin/returns/{returnRequest}/reject', [AdminRefundController::class, 'rejectReturn']);
        Route::get('admin/cancellations', [AdminRefundController::class, 'cancellations']);
        Route::post('admin/cancellations/{cancellationRequest}/resolve', [AdminRefundController::class, 'resolveCancellation']);
        Route::get('admin/refunds', [AdminRefundController::class, 'history']);

        // ---- Admin: commissions & rules ----
        Route::get('admin/commissions', [AdminCommissionController::class, 'index']);
        Route::get('admin/commission-rules', [AdminCommissionController::class, 'rules']);
        Route::post('admin/commission-rules', [AdminCommissionController::class, 'storeRule']);
        Route::put('admin/commission-rules/{commissionRule}', [AdminCommissionController::class, 'updateRule']);
        Route::delete('admin/commission-rules/{commissionRule}', [AdminCommissionController::class, 'destroyRule']);

        // ---- Admin: platform coupons ----
        Route::get('admin/coupons', [AdminCouponController::class, 'index']);
        Route::post('admin/coupons', [AdminCouponController::class, 'store']);
        Route::put('admin/coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('admin/coupons/{coupon}', [AdminCouponController::class, 'destroy']);

        // ---- Admin: settings ----
        Route::get('admin/about-page', [AdminAboutPageController::class, 'show']);
        Route::put('admin/about-page', [AdminAboutPageController::class, 'update']);
        Route::get('admin/settings', [AdminSettingsController::class, 'show']);
        Route::put('admin/settings', [AdminSettingsController::class, 'update']);
        Route::post('admin/settings/brand', [AdminSettingsController::class, 'uploadBrand']);
        Route::delete('admin/settings/brand', [AdminSettingsController::class, 'clearBrand']);
        Route::get('admin/payments-config', [AdminPaymentConfigController::class, 'show']);
        Route::put('admin/payments-config', [AdminPaymentConfigController::class, 'update']);

        // ---- Finance: agent balances & settlements ----
        Route::get('admin/agent-balances', [AdminFinanceController::class, 'agentBalances']);
        Route::get('admin/company-wallet', [AdminFinanceController::class, 'companyWallet']);
        Route::post('admin/agents/{agent}/payout', [AdminFinanceController::class, 'payout']);
        Route::get('admin/settlements', [AdminFinanceController::class, 'settlements']);
        Route::post('admin/settlements/{settlement}/transition', [AdminFinanceController::class, 'transitionSettlement']);

        // ---- Content: blog / newsletter / contact ----
        Route::apiResource('admin/blog', AdminBlogController::class);
        Route::get('admin/newsletter-subscribers', [AdminNewsletterController::class, 'index']);
        Route::get('admin/newsletter-subscribers/export', [AdminNewsletterController::class, 'export']);
        Route::patch('admin/newsletter-subscribers/{subscriber}', [AdminNewsletterController::class, 'update']);
        Route::delete('admin/newsletter-subscribers/{subscriber}', [AdminNewsletterController::class, 'destroy']);
        Route::get('admin/contact-messages', [AdminContactController::class, 'index']);
        Route::get('admin/contact-messages/{contactMessage}', [AdminContactController::class, 'show']);
        Route::patch('admin/contact-messages/{contactMessage}', [AdminContactController::class, 'update']);
        Route::delete('admin/contact-messages/{contactMessage}', [AdminContactController::class, 'destroy']);

        // ---- Reports ----
        Route::get('admin/reports/sales', [AdminReportController::class, 'sales']);
        Route::get('admin/reports/payments', [AdminReportController::class, 'payments']);
        Route::get('admin/reports/commissions', [AdminReportController::class, 'commissions']);
        Route::get('admin/reports/agents', [AdminReportController::class, 'agents']);
        Route::get('admin/reports/coupons', [AdminReportController::class, 'coupons']);

        // ---- Disputes (shared: customer opens, agent/staff respond) ----
        Route::get('disputes', [DisputeController::class, 'index']);
        Route::post('disputes', [DisputeController::class, 'store']);
        Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
        Route::post('disputes/{dispute}/reply', [DisputeController::class, 'reply']);
        Route::post('disputes/{dispute}/resolve', [DisputeController::class, 'resolve']);

        // ---- Support tickets (shared) ----
        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
        Route::patch('tickets/{ticket}', [TicketController::class, 'updateStatus']);

        // ---- Admin: agent KYC review ----
        Route::get('agents/{agent}/kyc', [AgentController::class, 'kyc']);
        Route::post('agents/{agent}/kyc/approve', [AgentController::class, 'kycApprove']);
        Route::post('agents/{agent}/kyc/reject', [AgentController::class, 'kycReject']);

        // ---- Agent portal (self-scoped) ----
        Route::prefix('agent')->middleware('role:agent')->group(function () {
            Route::get('kyc', [KycController::class, 'show']);
            Route::post('kyc/documents', [KycController::class, 'upload']);
            Route::post('kyc/submit', [KycController::class, 'submit']);

            Route::get('orders', [AgentOrderController::class, 'index']);
            Route::get('orders/{agentOrder}', [AgentOrderController::class, 'show']);
            Route::post('orders/{agentOrder}/status', [AgentOrderController::class, 'updateStatus']);

            Route::get('wallet', [AgentWalletController::class, 'show']);
            Route::get('withdrawals', [AgentWithdrawalController::class, 'index']);
            Route::post('withdrawals', [AgentWithdrawalController::class, 'store']);
            Route::get('commissions', [AgentCommissionController::class, 'index']);

            Route::get('coupons', [AgentCouponController::class, 'index']);
            Route::post('coupons', [AgentCouponController::class, 'store']);
            Route::put('coupons/{coupon}', [AgentCouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [AgentCouponController::class, 'destroy']);

            Route::get('reviews', [AgentReviewController::class, 'index']);
            Route::post('reviews/{review}/respond', [AgentReviewController::class, 'respond']);

            Route::get('inventory', [AgentInventoryController::class, 'index']);
            Route::post('inventory/{listing}/adjust', [AgentInventoryController::class, 'adjust']);
            Route::get('inventory/{listing}/history', [AgentInventoryController::class, 'history']);

            Route::get('reports/sales', [AgentReportController::class, 'sales']);

            Route::get('settings', [AgentSettingsController::class, 'show']);
            Route::put('settings', [AgentSettingsController::class, 'update']);
        });

        // ---- Customer portal (self-scoped) ----
        Route::prefix('me')->middleware('role:customer')->group(function () {
            // ---- Checkout ----
            Route::post('checkout/quote', [CheckoutController::class, 'quote']);
            Route::post('checkout/validate-coupon', [CheckoutController::class, 'validateCoupon']);
            Route::post('checkout/place', [CheckoutController::class, 'place']);

            Route::get('orders', [MyOrderController::class, 'index']);
            Route::get('orders/requests', [MyOrderController::class, 'requests']);
            Route::get('orders/{order}', [MyOrderController::class, 'show']);
            Route::post('orders/{order}/cancellation', [MyOrderController::class, 'requestCancellation']);
            Route::post('orders/{order}/return', [MyOrderController::class, 'requestReturn']);

            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::put('addresses/{address}', [AddressController::class, 'update']);
            Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
            Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

            Route::get('wishlist', [WishlistController::class, 'index']);
            Route::post('wishlist', [WishlistController::class, 'store']);
            Route::delete('wishlist/{listing}', [WishlistController::class, 'destroy']);

            Route::get('reviews', [MyReviewController::class, 'index']);
            Route::get('reviews/pending', [MyReviewController::class, 'pending']);
            Route::post('reviews', [MyReviewController::class, 'store']);
            Route::put('reviews/{review}', [MyReviewController::class, 'update']);
            Route::delete('reviews/{review}', [MyReviewController::class, 'destroy']);

            Route::get('wallet', [MyWalletController::class, 'show']);
            Route::post('wallet/deposits', [MyWalletController::class, 'deposit']);
            Route::get('commissions', [CommissionController::class, 'index']);
            Route::get('referrals', [CommissionController::class, 'referrals']);

            Route::get('settlements', [SettlementController::class, 'index']);
            Route::post('settlements', [SettlementController::class, 'store']);

            Route::get('settings', [SettingsController::class, 'show']);
            Route::put('settings', [SettingsController::class, 'update']);
        });
    });
});
