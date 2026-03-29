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
    ShowroomProfileController
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
    Route::get('vehicles', [VehicleController::class, 'publicIndex'])->name('public.vehicles.index');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'publicShow'])->name('public.vehicles.show');
    Route::get('vehicles/{vehicle}/image', [VehicleController::class, 'publicPrimaryImage'])->name('public.vehicles.image');

    Route::get('nearby/cars', [NearbyController::class, 'cars'])->name('public.nearby.cars');
    Route::get('nearby/drivers', [NearbyController::class, 'drivers'])->name('public.nearby.drivers');
    Route::get('nearby/location', [NearbyController::class, 'location'])->name('public.nearby.location');
});

/*
|--------------------------------------------------------------------------
| ✅ ALIAS ROUTES (NO AUTH)
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
    | ✅ Secure self routes
    |--------------------------------------------------------------------------
    */
    Route::get('customers/me', [CustomerController::class, 'me'])->name('customers.me');
    Route::get('drivers/me', [DriverController::class, 'me'])->name('drivers.me');
    Route::get('bookings/me', [BookingController::class, 'me'])->name('bookings.me');

    /*
    |--------------------------------------------------------------------------
    | ✅ BOOKINGS: Allow ANY authenticated user (customer included) to CREATE
    |--------------------------------------------------------------------------
    */
    Route::post('bookings', [BookingController::class, 'store'])
        ->name('bookings.create');

    /*
    |--------------------------------------------------------------------------
    | Read-only for all authenticated users
    |--------------------------------------------------------------------------
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
    | Vehicle images
    |--------------------------------------------------------------------------
    */
    Route::get('vehicles/{vehicle}/images', [ImageGeneratorController::class, 'index'])
        ->name('vehicles.images.index');

    Route::get('vehicles/{vehicle}/images/primary', [ImageGeneratorController::class, 'primary'])
        ->name('vehicles.images.primary');

    Route::post('vehicles/{vehicle}/images', [ImageGeneratorController::class, 'store'])
        ->name('vehicles.images.store');

    Route::patch('vehicles/{vehicle}/images/{image}', [ImageGeneratorController::class, 'update'])
        ->name('vehicles.images.update');

    Route::delete('vehicles/{vehicle}/images/{image}', [ImageGeneratorController::class, 'destroy'])
        ->name('vehicles.images.destroy');

    /*
    |--------------------------------------------------------------------------
    | Showroom
    |--------------------------------------------------------------------------
    */
    Route::prefix('showroom')
        ->middleware(['role:owner|host|agent|manager|admin'])
        ->group(function () {

            Route::get('profile', [ShowroomProfileController::class, 'show'])
                ->name('showroom.profile.show');

            Route::post('profile', [ShowroomProfileController::class, 'upsert'])
                ->name('showroom.profile.upsert');

            Route::get('profiles', [ShowroomProfileController::class, 'index'])
                ->name('showroom.profiles.index');

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

            Route::get('{showroom}/vehicles', [ShowroomVehicleController::class, 'indexByShowroom'])
                ->whereNumber('showroom')
                ->name('showroom.vehicles.byShowroom');

            Route::get('{showroom}/vehicles/{vehicle}', [ShowroomVehicleController::class, 'showByShowroom'])
                ->whereNumber('showroom')
                ->name('showroom.vehicles.showByShowroom');
        });

    /*
    |--------------------------------------------------------------------------
    | Vehicles global write
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin|manager|agent|owner|host'])->group(function () {
        Route::apiResource('vehicles', VehicleController::class)
            ->except(['index', 'show'])
            ->names('vehicles.write');

        Route::post('vehicles/{vehicle}/status', [VehicleController::class, 'setStatus'])
            ->name('vehicles.write.status');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin / Manager write operations
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
            ->except(['index', 'show', 'store'])
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