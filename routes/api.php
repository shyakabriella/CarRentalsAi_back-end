<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\API\{
    VehicleTypeController,
    LocationController,
    VehicleController,
    DriverController,
    CustomerController,
    BookingController,
    PaymentController,
    DriverReviewController,
    MaintenanceRecordController,
    DriverAvailabilityController,
    AiMatchLogController,
    RegisterController,
    ImageGeneratorController,
    ShowroomVehicleController,
    UploadController,
    NearbyController,
    ShowroomProfileController // ✅ ADD
};

/*
|--------------------------------------------------------------------------
| Public Auth
|--------------------------------------------------------------------------
*/
Route::post('/login',    [RegisterController::class, 'login'])->name('auth.login');
Route::post('/register', [RegisterController::class, 'register'])->name('auth.register');

/*
|--------------------------------------------------------------------------
| ✅ PUBLIC (NO AUTH)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {

    // Public vehicles browse
    Route::get('vehicles', [VehicleController::class, 'publicIndex'])->name('public.vehicles.index');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'publicShow'])->name('public.vehicles.show');
    Route::get('vehicles/{vehicle}/image', [VehicleController::class, 'publicPrimaryImage'])->name('public.vehicles.image');

    // ✅ Nearby endpoints (no auth)
    Route::get('nearby/cars', [NearbyController::class, 'cars'])->name('public.nearby.cars');
    Route::get('nearby/drivers', [NearbyController::class, 'drivers'])->name('public.nearby.drivers');
    Route::get('nearby/location', [NearbyController::class, 'location'])->name('public.nearby.location');
});

/*
|--------------------------------------------------------------------------
| ✅ ALIAS ROUTES (NO AUTH) — if frontend calls /api/nearby/...
|--------------------------------------------------------------------------
*/
Route::get('nearby/cars', [NearbyController::class, 'cars'])->name('nearby.cars');
Route::get('nearby/drivers', [NearbyController::class, 'drivers'])->name('nearby.drivers');
Route::get('nearby/location', [NearbyController::class, 'location'])->name('nearby.location');

/*
|--------------------------------------------------------------------------
| Authenticated APIs
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    */
    Route::post('uploads/vehicle-image', [UploadController::class, 'vehicleImage'])
        ->name('uploads.vehicle-image');

    /*
    |--------------------------------------------------------------------------
    | Meta
    |--------------------------------------------------------------------------
    */
    Route::prefix('meta')->group(function () {
        Route::get('vehicle-make-models', function () {
            return DB::table('vehicle_make_models')
                ->orderBy('make')
                ->orderBy('model')
                ->get();
        })->name('meta.vehicle-make-models');
    });

    /*
    |--------------------------------------------------------------------------
    | Read-only for all authenticated users
    |--------------------------------------------------------------------------
    | ✅ IMPORTANT: give each resource a UNIQUE name prefix
    */
    Route::apiResource('vehicle-types', VehicleTypeController::class)
        ->only(['index', 'show'])
        ->names('vehicle-types.read');

    Route::apiResource('locations', LocationController::class)
        ->only(['index', 'show'])
        ->names('locations.read');

    Route::apiResource('vehicles', VehicleController::class)
        ->only(['index', 'show'])
        ->names('vehicles.read');

    Route::apiResource('drivers', DriverController::class)
        ->only(['index', 'show'])
        ->names('drivers.read');

    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->names('customers.read');

    Route::apiResource('bookings', BookingController::class)
        ->only(['index', 'show'])
        ->names('bookings.read');

    Route::apiResource('payments', PaymentController::class)
        ->only(['index', 'show'])
        ->names('payments.read');

    Route::apiResource('driver-reviews', DriverReviewController::class)
        ->only(['index', 'show'])
        ->names('driver-reviews.read');

    Route::apiResource('maintenance-records', MaintenanceRecordController::class)
        ->only(['index', 'show'])
        ->names('maintenance-records.read');

    Route::apiResource('driver-availabilities', DriverAvailabilityController::class)
        ->only(['index', 'show'])
        ->names('driver-availabilities.read');

    Route::apiResource('ai-match-logs', AiMatchLogController::class)
        ->only(['index', 'show'])
        ->names('ai-match-logs.read');

    /*
    |--------------------------------------------------------------------------
    | ✅ SHOWROOM (owner box)
    |--------------------------------------------------------------------------
    */
    Route::prefix('showroom')
        ->middleware(['role:owner|agent|manager|admin'])
        ->group(function () {

            // ✅ Showroom Profile (NEW)
            Route::get('profile', [ShowroomProfileController::class, 'show'])
                ->name('showroom.profile.show');

            Route::post('profile', [ShowroomProfileController::class, 'upsert'])
                ->name('showroom.profile.upsert');

            // ✅ Showroom Vehicles
            Route::apiResource('vehicles', ShowroomVehicleController::class)
                ->parameters(['vehicles' => 'vehicle'])
                ->names('showroom.vehicles');

            Route::post('vehicles/{vehicle}/claim', [ShowroomVehicleController::class, 'claim'])
                ->name('showroom.vehicles.claim');

            Route::get('vehicles/{vehicle}/images', [ImageGeneratorController::class, 'index'])
                ->name('showroom.images.index');

            Route::get('vehicles/{vehicle}/images/primary', [ImageGeneratorController::class, 'primary'])
                ->name('showroom.images.primary');

            Route::post('vehicles/{vehicle}/images', [ImageGeneratorController::class, 'store'])
                ->name('showroom.images.store');

            Route::patch('vehicles/{vehicle}/images/{image}', [ImageGeneratorController::class, 'update'])
                ->name('showroom.images.update');

            Route::delete('vehicles/{vehicle}/images/{image}', [ImageGeneratorController::class, 'destroy'])
                ->name('showroom.images.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Vehicles global write (admins/managers/agents only)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin|manager|agent'])->group(function () {

        Route::apiResource('vehicles', VehicleController::class)
            ->except(['index', 'show'])
            ->names('vehicles.write');

        Route::post('vehicles/{vehicle}/status', [VehicleController::class, 'setStatus'])
            ->name('vehicles.write.status');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin/Manager write operations (global)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin|manager'])->group(function () {

        Route::apiResource('vehicle-types', VehicleTypeController::class)
            ->except(['index', 'show'])
            ->names('vehicle-types.admin');

        Route::apiResource('locations', LocationController::class)
            ->except(['index', 'show'])
            ->names('locations.admin');

        Route::apiResource('drivers', DriverController::class)
            ->except(['index', 'show'])
            ->names('drivers.admin');

        Route::apiResource('customers', CustomerController::class)
            ->except(['index', 'show'])
            ->names('customers.admin');

        Route::apiResource('bookings', BookingController::class)
            ->except(['index', 'show'])
            ->names('bookings.admin');

        Route::post('bookings/{booking}/assign-driver', [BookingController::class, 'assignDriver'])
            ->name('bookings.admin.assign-driver');

        Route::post('bookings/{booking}/status', [BookingController::class, 'setStatus'])
            ->name('bookings.admin.status');

        Route::apiResource('payments', PaymentController::class)
            ->except(['index', 'show'])
            ->names('payments.admin');

        Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])
            ->name('payments.admin.mark-paid');

        Route::apiResource('driver-reviews', DriverReviewController::class)
            ->except(['index', 'show'])
            ->names('driver-reviews.admin');

        Route::apiResource('maintenance-records', MaintenanceRecordController::class)
            ->except(['index', 'show'])
            ->names('maintenance-records.admin');

        Route::apiResource('driver-availabilities', DriverAvailabilityController::class)
            ->except(['index', 'show'])
            ->names('driver-availabilities.admin');

        Route::apiResource('ai-match-logs', AiMatchLogController::class)
            ->except(['index', 'show'])
            ->names('ai-match-logs.admin');
    });
});