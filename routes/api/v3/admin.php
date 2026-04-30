<?php
// Admin-specific routes
use App\Http\Controllers\Api\cashbox\CashboxController;
use App\Http\Controllers\Api\cashbox\CashierController;
use App\Http\Controllers\Api\V3\admin\AddOnController;
use App\Http\Controllers\Api\V3\admin\AdminController;
use App\Http\Controllers\Api\V3\admin\AdminRoleController;
use App\Http\Controllers\Api\V3\admin\ArrivalController;
use App\Http\Controllers\Api\V3\admin\AttributeController;
use App\Http\Controllers\Api\V3\admin\AuthController;
use App\Http\Controllers\Api\V3\admin\BannerController;
use App\Http\Controllers\Api\V3\admin\CampaignController;
use App\Http\Controllers\Api\V3\admin\CampaignRulesController;
use App\Http\Controllers\Api\V3\admin\CategoryController;
use App\Http\Controllers\Api\V3\admin\CustomerController;
use App\Http\Controllers\Api\V3\admin\DashboardController;
use App\Http\Controllers\Api\V3\admin\DeliveryManController;
use App\Http\Controllers\Api\V3\admin\DeliveryTypeController;
use App\Http\Controllers\Api\V3\admin\Eda24CampaignController;
use App\Http\Controllers\Api\V3\admin\EmployeeController;
use App\Http\Controllers\Api\V3\admin\EmployeeRoleController;
use App\Http\Controllers\Api\V3\admin\FinanceController;
use App\Http\Controllers\Api\V3\admin\FoodController;
use App\Http\Controllers\Api\V3\admin\LoyaltyPointController;
use App\Http\Controllers\Api\V3\admin\NewOrderController;
use App\Http\Controllers\Api\V3\admin\OptionsController;
use App\Http\Controllers\Api\V3\admin\OrderController;
use App\Http\Controllers\Api\V3\admin\RestaurantController;
use App\Http\Controllers\Api\V3\admin\SaleController;
use App\Http\Controllers\Api\V3\admin\TransactionCategoryController;
use App\Http\Controllers\Api\V3\admin\TransactionController;
use App\Http\Controllers\Api\V3\admin\VendorController;
use App\Http\Controllers\Api\V3\admin\WarehouseController;
use App\Http\Controllers\Api\V3\admin\WarehouseSaleController;
use App\Http\Controllers\Api\V3\admin\ZoneController;
use Illuminate\Support\Facades\Route;


Route::group(['namespace' => 'Api\V3\Admin', 'prefix' => 'admin'], function () {

    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/register', [AuthController::class, 'register']);
        Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:admin-api');
        Route::get('/getAdmin', [AuthController::class, 'getAdmin'])->middleware('auth:admin-api');
        Route::get('permissions', [AuthController::class, 'getPermissions'])->middleware('auth:admin-api');
        Route::post('/profile', [AuthController::class, 'edit'])->middleware('auth:admin-api');
    });

    Route::group(['middleware' => 'auth:admin-api'], function () {
        //Dashboard
        Route::group(['prefix' => 'dashboard', 'middleware' => ['api_module_permission:dashboard']], function () {
            Route::get('order-statistics', [DashboardController::class, 'statistics']);
            Route::get('total-sell-statistics', [DashboardController::class, 'totalSellStatistics']);
            Route::get('last-orders', [DashboardController::class, 'lastOrders']);
            Route::get('top-restaurants', [DashboardController::class, 'topStores']);
            Route::get('top-foods', [DashboardController::class, 'topProducts']);
            Route::get('top-deliveryman', [DashboardController::class, 'topDeliveryman']);
            Route::get('admin-foods', [DashboardController::class, 'adminFoods']);
            Route::get('delivery-man-orders/{deliveryMan}', [DashboardController::class, 'deliverymanOrders']);
            Route::get('top-clients', [DashboardController::class, 'topClients']);
        });

        Route::get('/', [DashboardController::class, 'dashboard']);
        Route::get('/get-zones', [DashboardController::class, 'getZones']);
        Route::get('/restaurants', [RestaurantController::class, 'index']);

        //Campaign crud
        Route::group(['middleware' => ['api_module_permission:campaign']], function () {

            Route::get('/campaigns', [CampaignController::class, 'index']);
            Route::get('/campaigns/{campaign}', [CampaignController::class, 'getCampaign']);

            Route::post('/campaigns/store', [CampaignController::class, 'store'])->middleware(['api_module_permission:campaign-create']);
            Route::post('/campaigns/status/{campaign}', [CampaignController::class, 'updateStatus'])->middleware(['api_module_permission:campaign-update-status']);;
            Route::post('/campaigns/update/{campaign}', [CampaignController::class, 'update'])->middleware(['api_module_permission:campaign-update']);
            Route::delete('/campaigns/delete/{campaign}', [CampaignController::class, 'delete'])->middleware(['api_module_permission:campaign-delete']);
            Route::get('/campaigns-rules', [CampaignRulesController::class, 'index']);
            Route::post('/campaigns-rules/status/{campaign_rule}', [CampaignRulesController::class, 'updateStatus']);
            Route::delete('/campaigns-rules/{campaign_rule}', [CampaignRulesController::class, 'delete']);
            //campaign items
            Route::post('/campaigns-item/{campaign}', [CampaignRulesController::class, 'addCampaignItem']);
            Route::get('/campaigns-items-by/{campaign}', [CampaignRulesController::class, 'getItemsByCamp']);

            Route::get('/campaign/restaurants', [CampaignController::class, 'getCampaignRestaurants']);
            Route::delete('/campaign/restaurant', [CampaignController::class, 'campaignRestaurantDelete']);
        });

        //notification
        Route::group(['prefix' => 'order', 'middleware' => ['api_module_permission:order']], function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/list', [OrderController::class, 'list']);
            Route::get('/counts', [OrderController::class, 'counts']);
            Route::get('/details/{order}', [OrderController::class, 'details'])->middleware('api_module_permission:order-show');
            Route::post('/status', [OrderController::class, 'status'])->middleware('api_module_permission:order-update-status');
            Route::post('/update-shipping/{order}', [OrderController::class, 'update_shipping'])->middleware('api_module_permission:order-update');
            Route::get('/orders-export/{type}', [OrderController::class, 'orders_export'])->middleware('api_module_permission:order-export');
            Route::post('/add-delivery-man/{order_id}/{delivery_man_id}', [OrderController::class, 'add_delivery_man']);
            Route::get('/generate-invoice/{id}', [OrderController::class, 'generate_invoice']);
            Route::post('add-payment-ref-code/{id}', [OrderController::class, 'add_payment_ref_code']);
            Route::get('restaurant-filter/{restaurant_id}', [OrderController::class, 'restaurant_filter']);
            Route::post('/search', [OrderController::class, 'search']);
            Route::post('/restaurant-order-search', [OrderController::class, 'restaurant_order_search']);
            Route::get('/receiver/{order}', [OrderController::class, 'getReceiver']);

            Route::get('export-statistics', [OrderController::class, 'export_statistics'])->withoutMiddleware(['auth:admin-api', 'api_module_permission:order']);
            //update order
            Route::put('/{order}', [OrderController::class, 'update'])->middleware('api_module_permission:order-update');
            Route::post('quick-view', [OrderController::class, 'quick_view'])->middleware('api_module_permission:order-show');
            Route::get('/receipt/{order}', [OrderController::class, 'getReceipt']);
        });

        //Cashier
        Route::group(['prefix' => 'cashier', 'middleware' => ['api_module_permission:restaurant']], function () {
            Route::get('restaurant', [CashierController::class, 'getCashierRestaurant']);
            Route::get('restaurant-orders', [CashierController::class, 'getCashierOrders']);
            Route::get('restaurant-foods', [CashierController::class, 'getFoods']);
            Route::get('restaurant-categories', [CashierController::class, 'getResCategories']);
            Route::get('restaurantFoodsByCatId/{category?}', [CashierController::class, 'getFoodsByCatId']);
            Route::post('get-foods-by-ids', [CashboxController::class, 'getFoodsByIds']);
        });

        Route::get('cashbox-users/{restaurant}', [CashierController::class, 'getUsers'])->withoutMiddleware('auth:admin-api');


        Route::group(['middleware' => ['auth', 'cashier']], function () {
            Route::get('cashbox/invoice', [CashboxController::class, 'getInvoices']);
            Route::post('cashbox-user', [CashierController::class, 'getUserPoint']);
            Route::post('cashbox/order', [CashboxController::class, 'makeOrder']);
            Route::get('cashbox/detail/{order}', [CashboxController::class, 'getDetail']);
            Route::post('cashbox/refund-partial/{order}', [CashboxController::class, 'refundPartial']);
            Route::post('cashbox/refund-full/{order}', [CashboxController::class, 'refundFull']);
        });

        //category
        Route::group(['prefix' => 'category', 'middleware' => ['api_module_permission:category']], function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::get('/all-categories', [CategoryController::class, 'allCategories']);
            Route::get('subcategories', [CategoryController::class, 'subcategories']);
            Route::get('/{id}', [CategoryController::class, 'restaurantCategories']);
            Route::get('/sub-categories/{category}', [CategoryController::class, 'restaurantSubCategories']);
            Route::post('store', [CategoryController::class, 'store'])->middleware('api_module_permission:category-create');
            Route::put('update/{category}', [CategoryController::class, 'update'])->middleware('api_module_permission:category-update');
            Route::post('update-priority/{category}', [CategoryController::class, 'update_priority'])->middleware('api_module_permission:category-update');
            Route::post('status/{category}', [CategoryController::class, 'updateStatus'])->middleware('api_module_permission:category-update');
            Route::delete('delete/{category}', [CategoryController::class, 'delete'])->middleware('api_module_permission:category-delete');
            Route::get('export-categories/{type}', [CategoryController::class, 'exportCategories']);
        });

        Route::group(['prefix' => 'attribute', 'middleware' => ['api_module_permission:attribute']], function () {
            Route::get('/', [AttributeController::class, 'index']);
            Route::post('/store', [AttributeController::class, 'store'])->middleware('api_module_permission:attribute-create');
            Route::put('/update/{attribute}', [AttributeController::class, 'edit'])->middleware('api_module_permission:attribute-update');
            Route::delete('/delete/{attribute}', [AttributeController::class, 'delete'])->middleware('api_module_permission:attribute-delete');
            Route::get('/export/{type}', [AttributeController::class, 'export_attributes']);
        });

        Route::group(['prefix' => 'food', 'middleware' => ['api_module_permission:food']], function () {
            Route::get('get-foods', [FoodController::class, 'getFoods']);
            Route::get('/by-ids', [FoodController::class, 'getFoodsByIds']);
            Route::get('/get-food/{product}', [FoodController::class, 'show']);
            Route::post('/status/{product}', [FoodController::class, 'updateStatus'])->middleware('api_module_permission:food-update-status');
            Route::post('import', [FoodController::class, 'bulkImportData'])->middleware('api_module_permission:food-import');
            Route::post('export-data', [FoodController::class, 'bulkExportData'])->middleware('api_module_permission:food-export');
            Route::get('/{id}', [FoodController::class, 'index']);
            Route::get('/by-names/{restaurant}', [FoodController::class, 'getFoodsByNames']);
            Route::delete('/delete/{product}', [FoodController::class, 'delete'])->middleware('api_module_permission:food-delete');
            Route::post('/store', [FoodController::class, 'store'])->middleware('api_module_permission:food-create');
            Route::post('/update/{product}', [FoodController::class, 'update'])->middleware('api_module_permission:food-update');
            Route::get('/import/template', [FoodController::class, 'getTemplate']);
        });

        Route::group(['prefix' => 'customer', 'middleware' => ['api_module_permission:customer']], function () {
            Route::get('/search', [CustomerController::class, 'search']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/', [CustomerController::class, 'index'])->middleware('api_module_permission:customer-list');
            Route::get('/export/{type}', [CustomerController::class, 'export'])->withoutMiddleware(['auth:admin-api', 'api_module_permission:customer']);
            Route::get('/{user}', [CustomerController::class, 'getUser']);
            Route::post('/update/{user}', [CustomerController::class, 'update'])->middleware('api_module_permission:customer-update');
        });

        Route::group(['prefix' => 'addon', 'middleware' => ['api_module_permission:addon']], function () {
            Route::get('/', [AddOnController::class, 'index']);
            Route::get('/{restaurant}', [AddOnController::class, 'restaurantAddOns']);
            Route::get('/export-data/{type}', [AddOnController::class, 'exportData']);
            Route::post('/import-data', [AddOnController::class, 'importData']);
            Route::post('/store', [AddOnController::class, 'store'])->middleware('api_module_permission:addon-create');
            Route::post('/status/{add_on}', [AddOnController::class, 'status'])->middleware('api_module_permission:addon-update');
            Route::post('/update/{add_on}', [AddOnController::class, 'update'])->middleware('api_module_permission:addon-update-status');
            Route::delete('/delete/{add_on}', [AddOnController::class, 'delete'])->middleware('api_module_permission:addon-delete');
        });

        Route::group(['prefix' => 'delivery-man', 'middleware' => ['api_module_permission:deliveryman']], function () {
            Route::get('/select-options', [DeliveryManController::class, 'selectOptionsAdmin']);
            Route::get('/', [DeliveryManController::class, 'index']);
            Route::get('/{deliveryMan}', [DeliveryManController::class, 'show']);
            Route::get('/export/{type}', [DeliveryManController::class, 'exportDeliveryMens']);
            Route::get('/available-for-order/{order}', [DeliveryManController::class, 'getAvailableDeliveryMans']);
            Route::post('/store', [DeliveryManController::class, 'store'])->middleware('api_module_permission:deliveryman-create');
            Route::post('/update/{deliveryMan}', [DeliveryManController::class, 'update'])->middleware('api_module_permission:deliveryman-update');
            Route::delete('/delete/{deliveryMan}', [DeliveryManController::class, 'delete'])->middleware('api_module_permission:deliveryman-delete');
        });

        Route::group(['prefix' => 'restaurant', 'middleware' => ['api_module_permission:restaurant']], function () {
            Route::get('/', [RestaurantController::class, 'list']);
            Route::get('/main', [RestaurantController::class, 'mainRestaurants']);
            Route::get('/{store}', [RestaurantController::class, 'getById']);
            Route::post('/export-data', [RestaurantController::class, 'exportData']);
            Route::post('/store', [RestaurantController::class, 'store'])->middleware('api_module_permission:restaurant-create');
            Route::post('/update/{store}', [RestaurantController::class, 'update'])->middleware('api_module_permission:restaurant-update');
            Route::delete('/delete/{store}', [RestaurantController::class, 'delete'])->middleware('api_module_permission:restaurant-delete');
            Route::post('/status/{store}', [RestaurantController::class, 'updateStatus'])->middleware('api_module_permission:restaurant-update');
        });

        Route::group(['prefix' => 'vendor'], function () {
            Route::get('/', [VendorController::class, 'index']);
            Route::get('/search', [VendorController::class, 'search']);
            Route::get('/{vendor}', [VendorController::class, 'show']);
        });

        Route::group(['prefix' => 'banner', 'middleware' => ['api_module_permission:banner']], function () {
            Route::get('/', [BannerController::class, 'index']);
            Route::post('/store', [BannerController::class, 'store'])->middleware('api_module_permission:banner-create');
            Route::post('/update/{banner}', [BannerController::class, 'update'])->middleware('api_module_permission:banner-update');
            Route::post('/status/{banner}', [BannerController::class, 'status'])->middleware('api_module_permission:banner-update-status');
            Route::delete('/delete/{banner}', [BannerController::class, 'destroy'])->middleware('api_module_permission:banner-delete');
        });

        Route::group(['prefix' => 'points', 'middleware' => ['api_module_permission:point']], function () {
            Route::get('/spent', [LoyaltyPointController::class, 'index']);
            Route::get('/spent/export', [LoyaltyPointController::class, 'exportData']);
            Route::get('/getUsers', [LoyaltyPointController::class, 'getUsers']);
        });

        Route::group(['prefix' => 'employee', 'middleware' => ['api_module_permission:employee']], function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/store', [EmployeeController::class, 'store']);
            Route::post('/update/{id}', [EmployeeController::class, 'update']);
            Route::delete('/delete/{id}', [EmployeeController::class, 'delete']);
            Route::get('/export', [EmployeeController::class, 'exportData']);
        });

        Route::group(['prefix' => 'admins', 'middleware' => ['api_module_permission:admin']], function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::post('/store', [AdminController::class, 'store'])->middleware('api_module_permission:admin-store');
            Route::post('/update/{admin}', [AdminController::class, 'update'])->middleware('api_module_permission:admin-update');
            Route::delete('/delete/{admin}', [AdminController::class, 'destroy'])->middleware('api_module_permission:admin-delete');
            Route::get('/export', [AdminController::class, 'exportData'])->middleware('api_module_permission:admin-export');
        });

        Route::group(['prefix' => 'admin-roles', 'middleware' => ['api_module_permission:admin-role']], function () {
            Route::get('/', [AdminRoleController::class, 'index']);
            //        Route::post('/store', [EmployeeController::class, 'store'])->middleware('api_module_permission:admin-role-store');
            //        Route::post('/update/{id}', [EmployeeController::class, 'update'])->middleware('api_module_permission:admin-role-update');
            //        Route::delete('/delete/{id}', [EmployeeController::class, 'delete'])->middleware('api_module_permission:admin-role-delete');
            //        Route::get('/export', [EmployeeController::class, 'exportData'])->middleware('api_module_permission:admin-role-export');
        });

        Route::group(['prefix' => 'zone', 'middleware' => ['api_module_permission:zone']], function () {
            Route::get('/', [ZoneController::class, 'index']);
            Route::post('/store', [ZoneController::class, 'store'])->middleware('api_module_permission:zone-create');
        });

        Route::group(['prefix' => 'delivery-types'], function () {
            Route::get('/', [DeliveryTypeController::class, 'index']);
            Route::put('/{deliveryType}', [DeliveryTypeController::class, 'update']);
        });

        Route::group(['prefix' => 'warehouse'], function () {
            Route::get('/', [WarehouseController::class, 'index']);
            Route::get('/main', [WarehouseController::class, 'mainWarehouses']);
            Route::post('/', [WarehouseController::class, 'store']);
            Route::get('/{warehouse}', [WarehouseController::class, 'show']);
            Route::put('/{warehouse}', [WarehouseController::class, 'update']);
            Route::delete('/{warehouse}', [WarehouseController::class, 'destroy']);
            Route::post('/status/{warehouse}', [WarehouseController::class, 'toggleStatus']);
            Route::get('/data/export', [WarehouseController::class, 'exportData']);
        });



        Route::group(['prefix' => 'main'], function () {

            Route::group(['prefix' => 'warehouse'], function () {
                Route::get('/products', [WarehouseController::class, 'getWarehouseProducts']);
                Route::post('/products', [WarehouseController::class, 'createProduct']);
                Route::get('/counts', [WarehouseController::class, 'counts']);
            });
            Route::group(['prefix' => 'arrivals'], function () {
                Route::get('/', [ArrivalController::class, 'index']);
                Route::get('/products', [ArrivalController::class, 'getProducts']);
                Route::get('/{arrival}', [ArrivalController::class, 'show']);
                Route::post('/', [ArrivalController::class, 'store']);
                Route::put('/{arrival}', [ArrivalController::class, 'update']);
            });

            Route::group(['prefix' => 'sales'], function () {
                Route::get('/', [SaleController::class, 'index']);
                Route::post('/', [SaleController::class, 'store']);
                Route::put('/{sale}', [SaleController::class, 'update']);
                Route::get('/{sale}', [SaleController::class, 'show']);
                Route::post('/status/{sale}', [SaleController::class, 'toggleStatus']);
            });
        });

        Route::group(['prefix' => 'options'], function () {
            Route::get('/stores', [OptionsController::class, 'stores']);
        });

        Route::group(['prefix' => 'finance'], function () {
            Route::get('product-profitability', [FinanceController::class, 'productProfitability']);
            Route::get('main-finance-statistics',  [FinanceController::class, 'mainIncomeStatistics']);
            Route::get('monthly-finance-statistics', [FinanceController::class, 'monthlyFinanceStatistics']);
        });

        Route::group(['prefix' => 'transaction-category'], function () {
            Route::get('/', [TransactionCategoryController::class, 'categories']);
            Route::get('/subcategories', [TransactionCategoryController::class, 'subcategories']);
            Route::get('/category', [TransactionCategoryController::class, 'categoryOption']);
            Route::get('/subcategories/{category}', [TransactionCategoryController::class, 'getSubcategoriesByCategoryIdOption']);
            Route::post('/', [TransactionCategoryController::class, 'store']);
            Route::put('/{category}', [TransactionCategoryController::class, 'update']);
            Route::delete('/{category}', [TransactionCategoryController::class, 'delete']);
        });

        Route::group(['prefix' => 'transaction'], function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::post('/', [TransactionController::class, 'store']);
            Route::get('/types', [TransactionController::class, 'typesOptions']);
            Route::get('/{transaction}', [TransactionController::class, 'show']);
            Route::put('/{transaction}', [TransactionController::class, 'update']);
        });
    });
});
