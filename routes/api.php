<?php

use App\Http\Controllers\Api\V1\Admin\AttributeController;
use App\Http\Controllers\Api\V1\Admin\BrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\ProductModerationController;
use App\Http\Controllers\Api\V1\Admin\SellerReviewController;
use App\Http\Controllers\Api\V1\Auth\EmailOtpController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Catalog\CatalogController;
use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Orders\OrderController;
use App\Http\Controllers\Api\V1\Payments\PaymentIntentController;
use App\Http\Controllers\Api\V1\Payments\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Seller\InventoryController;
use App\Http\Controllers\Api\V1\Seller\ProductMediaController;
use App\Http\Controllers\Api\V1\Seller\SellerAccountController;
use App\Http\Controllers\Api\V1\Seller\SellerOfferController;
use App\Http\Controllers\Api\V1\Seller\SellerProductController;
use App\Http\Controllers\Api\V1\Shopping\CartController;
use App\Http\Controllers\Api\V1\Shopping\WishlistController;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('throttle:api')
    ->group(function (): void {
        Route::get('/health', function () {
            return ApiResponse::success([
                'status' => 'ok',
                'application' => config('app.name'),
            ]);
        })->name('health');

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('/register', RegisterController::class)
                ->middleware('throttle:5,1')->name('register');
            Route::post('/login', LoginController::class)
                ->middleware('throttle:5,1')->name('login');
            Route::post('/email/verification-notification', [
                EmailVerificationController::class, 'send',
            ])->middleware(['auth:sanctum', 'throttle:3,1'])
                ->name('email.verification-notification');
            Route::get('/email/verify/{id}/{hash}', [
                EmailVerificationController::class, 'verify',
            ])->middleware(['signed', 'throttle:10,1'])->name('email.verify');
            Route::post('/otp/send', [EmailOtpController::class, 'send'])
                ->middleware('throttle:5,1')->name('otp.send');
            Route::post('/otp/verify', [EmailOtpController::class, 'verify'])
                ->middleware('throttle:10,1')->name('otp.verify');
            Route::post('/forgot-password', ForgotPasswordController::class)
                ->middleware('throttle:5,1')->name('forgot-password');
            Route::post('/reset-password', ResetPasswordController::class)
                ->middleware('throttle:5,1')->name('reset-password');
        });

        // Guest and signed-in cart endpoints. Guest identity is a random token stored only as a hash.
        Route::prefix('cart')->name('cart.')->group(function (): void {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::post('/items', [CartController::class, 'add'])->name('items.add');
            Route::patch('/items/{item}', [CartController::class, 'update'])->name('items.update');
            Route::delete('/items/{item}', [CartController::class, 'remove'])->name('items.remove');
        });

        // Provider webhook uses HMAC verification; provider-specific adapters/secrets must be configured.
        Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
            ->where('provider', '[a-zA-Z0-9_-]+')->middleware('throttle:30,1')
            ->name('webhooks.payments');

        // Public catalogue discovery.
        Route::prefix('catalog')->name('catalog.')->group(function (): void {
            Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');
            Route::get('/brands', [CatalogController::class, 'brands'])->name('brands');
            Route::get('/products', [CatalogController::class, 'products'])->name('products');
            Route::get('/categories/{category}/attributes', [
                CatalogController::class, 'categoryAttributes',
            ])->name('category-attributes');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/auth/logout', function (Request $request) {
                $request->user()->currentAccessToken()->delete();

                return ApiResponse::success(['message' => 'Logged out successfully.']);
            })->name('auth.logout');

            Route::post('/auth/logout-all', LogoutAllController::class)
                ->name('auth.logout-all');

            Route::get('/user', function (Request $request) {
                return ApiResponse::success($request->user());
            })->name('user');

            // Phase 6: Customer wishlist and safe guest-cart merge.
            Route::prefix('wishlist')->name('wishlist.')->group(function (): void {
                Route::get('/', [WishlistController::class, 'show'])->name('show');
                Route::post('/items', [WishlistController::class, 'add'])->name('items.add');
                Route::delete('/items/{item}', [WishlistController::class, 'remove'])->name('items.remove');
            });
            Route::post('/cart/merge-guest', [CartController::class, 'mergeGuest'])->name('cart.merge-guest');

            // Phase 7: Server-side checkout quotes and order placement.
            Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
            Route::post('/checkout/{checkout}/place', [CheckoutController::class, 'place'])->name('checkout.place');

            // Phase 8: Payment intent is intentionally disabled until provider integration is configured.
            Route::post('/orders/{order}/payment-intent', [PaymentIntentController::class, 'store'])->name('orders.payment-intent');

            // Phase 9: Customer orders and seller fulfilment.
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('/seller/accounts/{sellerAccount}/orders', [OrderController::class, 'sellerIndex'])->name('seller.orders.index');
            Route::post('/seller/accounts/{sellerAccount}/orders/{sellerOrder}/shipments', [OrderController::class, 'createShipment'])->name('seller.orders.shipments.store');
            Route::patch('/seller/accounts/{sellerAccount}/orders/{sellerOrder}/status', [OrderController::class, 'sellerStatus'])->name('seller.orders.status');

            // Phase 1: Seller organisation and seller-user membership.
            Route::prefix('seller/accounts')->name('seller.accounts.')->group(function (): void {
                Route::get('/', [SellerAccountController::class, 'index'])->name('index');
                Route::post('/', [SellerAccountController::class, 'store'])->name('store');
                Route::get('/{sellerAccount}', [SellerAccountController::class, 'show'])->name('show');
                Route::post('/{sellerAccount}/staff', [
                    SellerAccountController::class, 'addStaff',
                ])->middleware('permission:seller_staff.manage')->name('staff.store');
                Route::delete('/{sellerAccount}/staff/{user}', [
                    SellerAccountController::class, 'removeStaff',
                ])->middleware('permission:seller_staff.manage')->name('staff.destroy');
            });

            // Phase 2: Customer profile and addresses. All queries are owner-scoped.
            Route::prefix('customer')->name('customer.')->group(function (): void {
                Route::get('/profile', [CustomerProfileController::class, 'show'])->name('profile.show');
                Route::put('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
                Route::apiResource('addresses', AddressController::class);
            });

            // Phase 4: Seller-owned catalogue submissions and media.
            Route::prefix('seller/accounts/{sellerAccount}')->name('seller.accounts.')
                ->group(function (): void {
                    Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
                    Route::post('/products', [SellerProductController::class, 'store'])
                        ->middleware('permission:products.create')->name('products.store');
                    Route::get('/products/{product}', [SellerProductController::class, 'show'])
                        ->middleware('permission:products.view')->name('products.show');
                    Route::put('/products/{product}', [SellerProductController::class, 'update'])
                        ->middleware('permission:products.update')->name('products.update');
                    Route::post('/products/{product}/submit', [SellerProductController::class, 'submit'])
                        ->middleware('permission:products.update')->name('products.submit');
                    Route::post('/products/{product}/media', [ProductMediaController::class, 'store'])
                        ->middleware('permission:products.create')->name('products.media.store');
                    Route::delete('/products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
                        ->middleware('permission:products.update')->name('products.media.destroy');

                    // Phase 5: Seller offers and seller-managed stock (MVP).
                    Route::get('/offers', [SellerOfferController::class, 'index'])
                        ->middleware('permission:products.view')->name('offers.index');
                    Route::post('/offers', [SellerOfferController::class, 'store'])
                        ->middleware('permission:products.create')->name('offers.store');
                    Route::put('/offers/{offer}', [SellerOfferController::class, 'update'])
                        ->middleware('permission:products.update')->name('offers.update');
                    Route::post('/offers/{offer}/activate', [SellerOfferController::class, 'activate'])
                        ->middleware('permission:products.update')->name('offers.activate');
                    Route::post('/offers/{offer}/deactivate', [SellerOfferController::class, 'deactivate'])
                        ->middleware('permission:products.update')->name('offers.deactivate');
                    Route::get('/offers/{offer}/inventory', [InventoryController::class, 'show'])
                        ->middleware('permission:inventory.view')->name('inventory.show');
                    Route::post('/offers/{offer}/inventory/adjust', [InventoryController::class, 'adjust'])
                        ->middleware('permission:inventory.manage')->name('inventory.adjust');
                    Route::put('/offers/{offer}/inventory/threshold', [InventoryController::class, 'threshold'])
                        ->middleware('permission:inventory.manage')->name('inventory.threshold');
                });

            // Phase 4: Catalogue moderation.
            Route::prefix('admin/products')->name('admin.products.')
                ->middleware('role:super_admin,operations_admin')
                ->group(function (): void {
                    Route::get('/', [ProductModerationController::class, 'index'])
                        ->middleware('permission:products.view')->name('index');
                    Route::post('/{product}/approve', [ProductModerationController::class, 'approve'])
                        ->middleware('permission:products.approve')->name('approve');
                    Route::post('/{product}/reject', [ProductModerationController::class, 'reject'])
                        ->middleware('permission:products.approve')->name('reject');
                    Route::patch('/{product}/status', [ProductModerationController::class, 'setStatus'])
                        ->middleware('permission:products.approve')->name('status');
                });

            // Phase 1: Marketplace seller review actions.
            Route::prefix('admin/sellers')->name('admin.sellers.')
                ->middleware('role:super_admin,operations_admin')
                ->group(function (): void {
                    Route::get('/', [SellerReviewController::class, 'index'])
                        ->middleware('permission:sellers.view')->name('index');
                    Route::post('/{sellerAccount}/approve', [SellerReviewController::class, 'approve'])
                        ->middleware('permission:sellers.approve')->name('approve');
                    Route::post('/{sellerAccount}/reject', [SellerReviewController::class, 'reject'])
                        ->middleware('permission:sellers.approve')->name('reject');
                    Route::post('/{sellerAccount}/suspend', [SellerReviewController::class, 'suspend'])
                        ->middleware('permission:sellers.suspend')->name('suspend');
                });

            // Phase 3: Taxonomy administration.
            Route::prefix('admin')->name('admin.')
                ->middleware('role:super_admin,operations_admin')
                ->group(function (): void {
                    Route::get('/categories', [CategoryController::class, 'index'])
                        ->middleware('permission:categories.view')->name('categories.index');
                    Route::post('/categories', [CategoryController::class, 'store'])
                        ->middleware('permission:categories.create')->name('categories.store');
                    Route::get('/categories/{category}', [CategoryController::class, 'show'])
                        ->middleware('permission:categories.view')->name('categories.show');
                    Route::put('/categories/{category}', [CategoryController::class, 'update'])
                        ->middleware('permission:categories.update')->name('categories.update');
                    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
                        ->middleware('permission:categories.delete')->name('categories.destroy');
                    Route::put('/categories/{category}/attributes', [
                        CategoryController::class, 'syncAttributes',
                    ])->middleware('permission:categories.update')->name('categories.attributes.sync');

                    Route::get('/brands', [BrandController::class, 'index'])
                        ->middleware('permission:brands.view')->name('brands.index');
                    Route::post('/brands', [BrandController::class, 'store'])
                        ->middleware('permission:brands.create')->name('brands.store');
                    Route::get('/brands/{brand}', [BrandController::class, 'show'])
                        ->middleware('permission:brands.view')->name('brands.show');
                    Route::put('/brands/{brand}', [BrandController::class, 'update'])
                        ->middleware('permission:brands.update')->name('brands.update');
                    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])
                        ->middleware('permission:brands.delete')->name('brands.destroy');

                    Route::get('/attributes', [AttributeController::class, 'index'])
                        ->middleware('permission:attributes.view')->name('attributes.index');
                    Route::post('/attributes', [AttributeController::class, 'store'])
                        ->middleware('permission:attributes.create')->name('attributes.store');
                    Route::get('/attributes/{attribute}', [AttributeController::class, 'show'])
                        ->middleware('permission:attributes.view')->name('attributes.show');
                    Route::put('/attributes/{attribute}', [AttributeController::class, 'update'])
                        ->middleware('permission:attributes.update')->name('attributes.update');
                    Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])
                        ->middleware('permission:attributes.delete')->name('attributes.destroy');
                });
        });
    });
