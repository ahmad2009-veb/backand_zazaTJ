<?php

use App\Http\Controllers\Api\V3\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V3\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V3\CampaignController;
use App\Http\Controllers\Api\V3\CategoryController;
use App\Http\Controllers\Api\V3\CustomerAddressController;
use App\Http\Controllers\Api\V3\CustomerNotificationController;
use App\Http\Controllers\Api\V3\FoodController;
use App\Http\Controllers\Api\V3\OrderController;
use App\Http\Controllers\Api\V3\ProductController;
use App\Http\Controllers\Api\V3\RestaurantController;
use App\Http\Controllers\Api\V3\SearchController;
use App\Http\Controllers\Api\V3\UserController;
use App\Http\Controllers\Api\V3\WishlistController;
use App\Http\Controllers\Messangers\MessengersController;
use Illuminate\Support\Facades\Route;


Route::group(['namespace' => 'Api\V3'], function () {
    Route::prefix('messengers')->group(function () {
            Route::get('connect', [MessengersController::class, 'getLink'])->name('messengers.connect');
            Route::get('callback', [MessengersController::class, 'callback'])->name('messengers.callback');
            Route::post('disconnect', [MessengersController::class, 'disconnect'])->name('messengers.disconnect');
            Route::get('webhook', [MessengersController::class, 'webhook']);
            Route::get('checkConnectionStatus', [MessengersController::class, 'checkConnectionStatus']);
            Route::post('webhook', [MessengersController::class, 'webhookPost']);
            Route::post('sendReply', [MessengersController::class, 'sendReply']);
            Route::get('listChats', [MessengersController::class, 'listChats']);
            Route::get('getChatBySender/{senderId}', [MessengersController::class, 'getChatBySender']);
            Route::post('markMessagesAsSeen/{senderId}', [MessengersController::class, 'markMessagesAsSeen']);

            // Facebook required callbacks for App Review
            Route::post('data-deletion', [MessengersController::class, 'dataDeletionCallback'])->name('messengers.data-deletion');
            Route::post('deauthorize', [MessengersController::class, 'deauthorizeCallback'])->name('messengers.deauthorize');
        });
    
    //Auth
    Route::post('register', [\App\Http\Controllers\Api\V3\Auth\CustomerAuthController::class, 'requestOtp']);
    Route::post('verify-phone', [\App\Http\Controllers\Api\V3\Auth\CustomerAuthController::class, 'verifyPhoneCreateUser']);
    Route::post('login', [\App\Http\Controllers\Api\V3\Auth\CustomerAuthController:: class, 'login']);
    Route::put('reset-password-request', [ResetPasswordController::class, 'reset_password_request']);
    Route::post('verify-otp', [ResetPasswordController::class, 'verify_otp']);
    Route::post('check-password-otp', [ResetPasswordController::class, 'checkOtp']);
    Route::post('reset-password-submit', [ResetPasswordController::class, 'reset_password_submit']);
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->middleware('auth:api');
    Route::post('update-profile', [UserController::class, 'update_profile'])->middleware('auth:api');
    Route::post('confirm-profile', [UserController::class, 'confirm_profile'])->middleware('auth:api');
    Route::get('verify-delete', [UserController::class, 'verifyDeleteAccount'])->middleware('auth:api');;
    Route::post('/account/delete', [UserController::class, 'confirmDeleteAccount'])->middleware('auth:api');;
    Route::post('/request-delete-account', [\App\Http\Controllers\Web\PrivacyController::class, 'sendMessage']);
    Route::post('/feedback', [\App\Http\Controllers\Web\PrivacyController::class, 'sendFeedback']);

// middleware bearer token

    Route::middleware('auth:api')->group(function () {

        Route::get('/user', [UserController::class, 'index']);

        Route::post('create-address', [CustomerAddressController::class, 'store']);
        Route::patch('customer-address/{id}', [CustomerAddressController::class, 'update']);
        Route::get('customer-addresses', [CustomerAddressController::class, 'customer_addresses']);
        Route::get('customer-notifications', [CustomerNotificationController::class, 'getUserNotifications']);
        Route::get('notifications', [CustomerNotificationController::class, 'system_notifications']);
        Route::post('customer/device_token', [UserController::class, 'userDeviceTokenStore'])->middleware('auth:api');

        //wishlist

        Route::get('wishlist', [WishlistController::class, 'get_customer_wishlist']);
        Route::post('add-to-wishlist', [WishlistController::class, 'addToWishList']);
        Route::post('remove-from-wishlist', [WishlistController::class, 'remove_from_wishlist']);

        //order

        Route::post('orders', [OrderController::class, 'makeOrder']);
        Route::get('orders', [OrderController::class, 'getCustomerOrders']);
        Route::post('orders/repeat/{order}', [OrderController::class, 'repeatOrder']);
        Route::get('orders/{restaurant}', [OrderController::class, 'getRestaurantOrders']);
        Route::post('orders/get-total-cart', [OrderController::class, 'getTotalOrderCart']);
        Route::post('orders/get-cart-items', [OrderController::class, 'getCartItems']);
    });

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('getProducts', [ProductController::class, 'getProductsByRestaurantSlug']);
    Route::get('restaurant/foods/{restaurant}/{category}', [RestaurantController::class, 'foods']);
    Route::get('restaurant-categories/{restaurant}', [RestaurantController::class, 'restaurantCategories']);
    Route::get('products/{restaurant_id}', [ProductController::class, 'getProductsByRestaurantSlug']);

    Route::prefix('restaurants')->group(function () {
        Route::get('/', [RestaurantController::class, 'index']);
        Route::post('/by-ids', [RestaurantController::class, 'getByIds']);
        Route::get('category/{category}', [CategoryController::class, 'getRestaurantsByCategoryId']);
        Route::get('{restaurant}', [RestaurantController::class, 'show']);
        Route::post('submit-review', [RestaurantController::class, 'submitRestaurantReview'])->middleware('auth:api');
    });

    Route::post('foods/get-by-ids', [FoodController::class, 'getFoodsByIds']);
    Route::get('foods/{food}', [FoodController::class, 'getFoodById']);
    Route::get('foods/best-sellers/{restaurant}', [FoodController::class, 'getBestSellers']);
    Route::get('search', [SearchController::class, 'search']);
    Route::get('/search/user_foods_top_rest', [SearchController::class, 'user_foods_and_top_searched_rest']);
    Route::get('getCampaigns', [CampaignController::class, 'getCampaigns']);
    Route::get('campaign/{campaign}', [CampaignController::class, 'getCampaignById']);
    Route::get('/campaign_rules', [CampaignController::class, 'getCampaignWithRuleAndCompleteness'])->middleware('auth:api');
    Route::get('customer/campaigns', [CampaignController::class, 'pastCampaigns'])->middleware('auth:api');
    Route::get('customer/active-campaigns', [CampaignController::class, 'getActiveCampaignsWithPoints'])->middleware('auth:api');

    //user bonuses
    Route::get('user/bonuses/{user}', [UserController::class, 'user_bonuses'])->middleware('auth:api');
    Route::get('user/bonuses', [UserController::class, 'bonuses'])->middleware('auth:api');
    Route::post('/user/fcm-token', [UserController::class, 'update_fcm_token'])->middleware('auth:api');
    Route::post('orders/paid/{order}', [OrderController::class, 'makeOrderPaid']);

    //loyalty point routes
    Route::get('loyalty-point', [\App\Http\Controllers\Api\V3\LoyaltyPointController::class, 'index']);
    Route::post('loyalty-point/user', [\App\Http\Controllers\Api\V3\LoyaltyPointController::class, 'add_points_to_user']);
    Route::post('loyalty_point/store', [\App\Http\Controllers\Api\V3\LoyaltyPointController::class, 'store']);


});




















