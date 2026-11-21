
<?php

use App\Http\Controllers\Api\V1\Auth\EmailOtpController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('throttle:api')
    ->group(function (): void {

        // -------------------------------------------------
        // HEALTH CHECK
        // -------------------------------------------------

        Route::get('/health', function () {
            return ApiResponse::success([
                'status' => 'ok',
                'application' => config('app.name'),
            ]);
        })->name('health');

        // -------------------------------------------------
        // PUBLIC AUTHENTICATION ROUTES
        // -------------------------------------------------

        Route::prefix('auth')->name('auth.')->group(function (): void {

            Route::post('/register', RegisterController::class)
                ->middleware('throttle:5,1')
                ->name('register');

            Route::post('/login', LoginController::class)
                ->middleware('throttle:5,1')
                ->name('login');

            Route::post('/email/verification-notification', [
                EmailVerificationController::class,
                'send',
            ])->middleware(['auth:sanctum', 'throttle:3,1'])
                ->name('email.verification-notification');

            Route::get('/email/verify/{id}/{hash}', [
                EmailVerificationController::class,
                'verify',
            ])->middleware(['signed', 'throttle:10,1'])
                ->name('email.verify');

            Route::post('/otp/send', [EmailOtpController::class, 'send'])
                ->middleware('throttle:5,1')
                ->name('otp.send');

            Route::post('/otp/verify', [EmailOtpController::class, 'verify'])
                ->middleware('throttle:10,1')
                ->name('otp.verify');

            Route::post('/forgot-password', ForgotPasswordController::class)
                ->middleware('throttle:5,1')
                ->name('forgot-password');

            Route::post('/reset-password', ResetPasswordController::class)
                ->middleware('throttle:5,1')
                ->name('reset-password');
        });

        // -------------------------------------------------
        // AUTHENTICATED USER ROUTES
        // -------------------------------------------------

        Route::middleware('auth:sanctum')->group(function (): void {

            Route::post('/auth/logout', function (Request $request) {
                $request->user()->currentAccessToken()->delete();

                return ApiResponse::success([
                    'message' => 'Logged out successfully.',
                ]);
            })->name('auth.logout');

            Route::post('/auth/logout-all', LogoutAllController::class)
                ->name('auth.logout-all');

            Route::get('/user', function (Request $request) {
                return ApiResponse::success($request->user());
            })->name('user');
        });

        // -------------------------------------------------
        // RBAC PROTECTED MODULE ROUTES
        // Add module routes here as they are implemented.
        // -------------------------------------------------

        // SUPER ADMIN
        Route::middleware([
            'auth:sanctum',
            'role:super_admin',
        ])->prefix('admin')->name('admin.')->group(function (): void {

            // Future global administration routes.
            // Example:
            // Route::get('/roles', ...)->middleware('permission:roles.view');
        });

        // OPERATIONS ADMIN
        Route::middleware([
            'auth:sanctum',
            'role:operations_admin',
        ])->prefix('operations')->name('operations.')->group(function (): void {

            // Future seller, catalogue and order operations.
        });

        // SELLER OWNER
        Route::middleware([
            'auth:sanctum',
            'role:seller_owner',
        ])->prefix('seller')->name('seller.')->group(function (): void {

            // Future seller-owned catalogue, orders and inventory.
        });

        // SELLER STAFF
        Route::middleware([
            'auth:sanctum',
            'role:seller_staff',
        ])->prefix('seller-staff')->name('seller-staff.')->group(function (): void {

            // Future owner-assigned staff tasks.
        });

        // FINANCE ADMIN
        Route::middleware([
            'auth:sanctum',
            'role:finance_admin',
        ])->prefix('finance')->name('finance.')->group(function (): void {

            // Future finance, reconciliation and payout routes.
        });

        // SUPPORT AGENT
        Route::middleware([
            'auth:sanctum',
            'role:support_agent',
        ])->prefix('support')->name('support.')->group(function (): void {

            // Future support ticket routes.
        });

        // WAREHOUSE / FULFILMENT
        Route::middleware([
            'auth:sanctum',
            'role:warehouse_fulfilment',
        ])->prefix('warehouse')->name('warehouse.')->group(function (): void {

            // Future warehouse and fulfilment routes.
        });

        // AUDITOR / ANALYST
        Route::middleware([
            'auth:sanctum',
            'role:auditor_analyst',
        ])->prefix('audit')->name('audit.')->group(function (): void {

            // Future read-only reports and audit routes.
        });

        // CUSTOMER
        Route::middleware([
            'auth:sanctum',
            'role:customer',
        ])->prefix('customer')->name('customer.')->group(function (): void {

            // Future customer account and order routes.
        });
    });
