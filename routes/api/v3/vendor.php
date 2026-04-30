<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V3\vendor\FoodController;
use App\Http\Controllers\Api\V3\vendor\SaleController;
use App\Http\Controllers\Api\cashbox\CashboxController;
use App\Http\Controllers\Api\cashbox\CashierController;
use App\Http\Controllers\Api\V3\vendor\AddonController;
use App\Http\Controllers\Api\V3\vendor\OrderController;
use App\Http\Controllers\Api\V3\vendor\WalletController;
use App\Http\Controllers\Api\V3\vendor\ArrivalController;
use App\Http\Controllers\Api\V3\vendor\FinanceController;
use App\Http\Controllers\Api\V3\vendor\ProductController;
use App\Http\Controllers\Api\V3\vendor\CategoryController;
use App\Http\Controllers\Api\V3\vendor\CustomerController;
use App\Http\Controllers\Api\V3\vendor\EmployeeController;
use App\Http\Controllers\Api\V3\admin\RestaurantController;
use App\Http\Controllers\Api\V3\vendor\AttributeController;
use App\Http\Controllers\Api\V3\vendor\DashboardController;
use App\Http\Controllers\Api\V3\vendor\WarehouseController;
use App\Http\Controllers\Api\V3\admin\DeliveryManController;
use App\Http\Controllers\Api\V3\vendor\auth\LoginController;
use App\Http\Controllers\Api\V3\vendor\TransactionController;
use App\Http\Controllers\Api\V3\vendor\CounterpartyController;
use App\Http\Controllers\Api\V3\vendor\EmployeeRoleController;
use App\Http\Controllers\Api\V3\vendor\OrderInstallmentsController;
use App\Http\Controllers\Api\V3\vendor\TransactionCategoryController;
use App\Http\Controllers\Api\V3\vendor\TransactionScheduleController;
use App\Http\Controllers\Api\V3\vendor\VariationController;
use App\Http\Controllers\Api\V3\vendor\ReceiptController;
use App\Http\Controllers\Api\V3\vendor\WarehouseTransferController;

Route::group(['namespace' => 'Api\V3\Vendor', 'prefix' => 'vendor'], function () {

    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth:vendor_api');
        Route::get('/getVendor', [LoginController::class, 'getVendor'])->middleware('auth:vendor_api');
        Route::get('permissions', [LoginController::class, 'getPermissions'])->middleware('auth:vendor_api');
    });

    Route::post('/registration', [RestaurantController::class, 'store']);


    Route::group(['prefix' => 'employee'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('/login', [LoginController::class, 'loginEmployee']);
            Route::get('/logout', [LoginController::class, 'logout'])->middleware('auth:vendor_employee_api');
            Route::get('/getEmployee', [LoginController::class, 'getVendorEmployee'])->middleware('auth:vendor_employee_api');
            Route::get('permissions', [LoginController::class, 'getPermissions'])->middleware('auth:vendor_employee_api');
        });

        Route::group(['middleware' => ['auth:vendor_employee_api']], function () {
            Route::get('restaurant', [CashierController::class, 'getCashierRestaurant']);
            Route::get('restaurant-orders', [CashierController::class, 'getCashierOrders']);
            Route::get('restaurant-foods', [CashierController::class, 'getFoods']);
            Route::get('restaurant-categories', [CashierController::class, 'getResCategories']);
            Route::get('restaurantFoodsByCatId/{category?}', [CashierController::class, 'getFoodsByCatId']);
            Route::post('get-foods-by-ids', [CashboxController::class, 'getFoodsByIds']);

            Route::get('cashbox/invoice', [CashboxController::class, 'getInvoices']);
            Route::post('cashbox-user', [CashierController::class, 'getUserPoint']);
            Route::post('cashbox/order', [CashboxController::class, 'makeOrder']);
            Route::get('cashbox/detail/{order}', [CashboxController::class, 'getDetail']);
            Route::post('cashbox/refund-partial/{order}', [CashboxController::class, 'refundPartial']);
            Route::post('cashbox/refund-full/{order}', [CashboxController::class, 'refundFull']);
            Route::get('cashbox/get-last-fd/{order}', [CashboxController::class, 'getLastFd']);
        });
    });

    Route::group(['middleware' => ['auth:vendor_api,vendor_employee_api']], function () {
        Route::prefix('installments')->group(function () {
            Route::get('/', [OrderInstallmentsController::class, 'index'])->middleware('vendor_employee_access:finance');
            Route::get('/{order}', [OrderInstallmentsController::class, 'show'])->middleware('vendor_employee_access:finance');
            Route::post('/pay', [OrderInstallmentsController::class, 'pay'])->middleware('vendor_employee_access:finance');
        });

        Route::group(['prefix' => 'order', 'middleware' => 'vendor_employee_access:orders'], function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/counts', [OrderController::class, 'counts']);
            Route::get('/countTotalPrice', [OrderController::class, 'countTotalPrice']);
            Route::get('/list', [OrderController::class, 'list']);
            Route::get('/details/{order}', [OrderController::class, 'details']);
            Route::post('/status', [OrderController::class, 'status']);
            Route::post('/update-shipping/{order}', [OrderController::class, 'update_shipping']);

            Route::post('/add-delivery-man/{order_id}/{delivery_man_id}', [OrderController::class, 'add_delivery_man']);
            Route::get('/generate-invoice/{id}', [OrderController::class, 'generate_invoice']);
            Route::post('add-payment-ref-code/{id}', [OrderController::class, 'add_payment_ref_code']);
            
            Route::put('/{order}', [OrderController::class, 'update']);
            Route::post('edit-order/{order}', [OrderController::class, 'edit']);
            Route::post('quick-view', [OrderController::class, 'quick_view']);
            Route::get('/receiver/{order}', [OrderController::class, 'getReceiver']);
            Route::get('/receipt/{order}', [OrderController::class, 'getReceipt']);
        });


        Route::group(['prefix' => 'delivery-man', 'middleware' => 'vendor_employee_access:couriers'], function () {
            Route::get('/select-options', [DeliveryManController::class, 'selectOptionsVendor']);
            Route::get('/', [DeliveryManController::class, 'indexVendor']);
            Route::get('/{deliveryMan}', [DeliveryManController::class, 'show']);
            Route::get('/export/{type}', [DeliveryManController::class, 'exportDeliveryMens']);
            Route::get('/available-for-order/{order}', [DeliveryManController::class, 'getAvailableDeliveryMansVendor']);
            Route::post('/store', [DeliveryManController::class, 'store']);
            Route::post('/update/{deliveryMan}', [DeliveryManController::class, 'update']);
            Route::delete('/delete/{deliveryMan}', [DeliveryManController::class, 'delete']);
        });


        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/order-statistics', [DashboardController::class, 'statistics']);
            Route::get('/total-sell-statistics', [DashboardController::class, 'totalSellStatistics']);
            Route::get('/last-orders', [DashboardController::class, 'lastOrders']);
            Route::get('/top-foods', [DashboardController::class, 'topFoods']);
        });

        Route::group(['prefix' => 'food', 'middleware' => 'vendor_employee_access:warehouse'], function () {
            Route::get('/by-ids', [FoodController::class, 'getFoodsByIds']);
            Route::get('/get-foods', [FoodController::class, 'index']);
            Route::get('/get-food/{food}', [FoodController::class, 'show']);
            Route::post('/store', [FoodController::class, 'store']);
            Route::post('/update/{food}', [FoodController::class, 'update']);
            Route::delete('/delete/{food}', [FoodController::class, 'delete']);
            Route::get('/{warehouse}', [WarehouseController::class, 'getWarehouseProducts']);
        });

        Route::group(['prefix' => 'addon'], function () {
            Route::get('/', [AddonController::class, 'index']);
            Route::post('/store', [AddOnController::class, 'store']);
            Route::post('/status/{add_on}', [AddOnController::class, 'status']);
            Route::post('/update/{add_on}', [AddOnController::class, 'update']);
            Route::delete('/delete/{add_on}', [AddOnController::class, 'delete']);
        });

        Route::group(['prefix' => 'attribute'], function () {
            Route::get('/', [AttributeController::class, 'index']);
            Route::post('/store', [AttributeController::class, 'store']);
            Route::put('/edit/{attribute}', [AttributeController::class, 'update']);
            Route::delete('/delete/{attribute}', [AttributeController::class, 'destroy']);
        });

        //category
        Route::group(['prefix' => 'category'], function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::get('/all-categories', [CategoryController::class, 'allCategories']);
            Route::get('subcategories', [CategoryController::class, 'subcategories']);
            Route::get('/sub-categories/{category}', [CategoryController::class, 'getSubcategoriesByCategoryId']);
            Route::post('/store', [CategoryController::class, 'store']);
            Route::put('/update/{category}', [CategoryController::class, 'update']);
            Route::delete('/delete/{category}', [CategoryController::class, 'destroy']);
            Route::post('/status/{category}', [CategoryController::class, 'updateStatus']);
            Route::post('/update-priority/{category}', [CategoryController::class, 'updatePriority']);
            Route::get('/{warehouse}', [WarehouseController::class, 'categories']);
        });

        // Employee management - VENDORS ONLY (employees cannot manage other employees)
        Route::group(['prefix' => 'employees', 'middleware' => 'auth:vendor_api'], function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/store', [EmployeeController::class, 'store']);
            Route::post('/update/{vendor_employee}', [EmployeeController::class, 'update']);
            Route::delete('/delete/{vendor_employee}', [EmployeeController::class, 'destroy']);
        });

        Route::group(['prefix' => 'employee-roles'], function () {
            Route::get('/', [EmployeeRoleController::class, 'index']);
            Route::get('/available-modules', [EmployeeRoleController::class, 'availableModules']);
        });

        Route::group(['prefix' => 'warehouse', 'middleware' => 'vendor_employee_access:warehouse'], function () {
            Route::get('/select-options', [WarehouseController::class, 'selectOptions']);
            Route::get('/export-data/{type}', [WarehouseController::class, 'export']);
            Route::get('/products/list', [WarehouseController::class, 'getProducts']);

            Route::group(['prefix' => 'variations'], function () {
                Route::get('/types', [VariationController::class, 'types']);
            });

            Route::get('/', [WarehouseController::class, 'index']);
            Route::post('/', [WarehouseController::class, 'store']);
            Route::get('/{warehouse}', [WarehouseController::class, 'show']);
            Route::put('/{warehouse}', [WarehouseController::class, 'update']);
            Route::post('/status/{wareHouse}', [WarehouseController::class, 'toggleStatus']);
            Route::delete('/{warehouse}', [WarehouseController::class, 'destroy']);
            Route::get('/{warehouse}/products',[WarehouseController::class, 'getWarehouseProducts']);
            Route::post('/{warehouse}/stock-lookup', [WarehouseController::class, 'stockLookup']);
        });




        Route::group(['prefix' => 'products'], function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/template', [ProductController::class, 'getTemplate']);
            Route::post('/data-import', [ProductController::class, 'importProducts']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::post('/{product}', [ProductController::class, 'update']);
            Route::post('/{product}/add-products', [ProductController::class, 'addProducts']);
            Route::post('/{product}/update-image', [ProductController::class, 'updateImage']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
            Route::post('/status/{product}', [ProductController::class, 'toggleStatus']);
            Route::get('/export-data/{type}', [ProductController::class, 'export']);
        });



        Route::group(['prefix' => 'arrivals'], function () {
            Route::get('/', [ArrivalController::class, 'index']);
            Route::post('/', [ArrivalController::class, 'store']);
            Route::get('/template', [ArrivalController::class, 'getTemplate']);
            Route::put('/{arrival}', [ArrivalController::class, 'update']);
            Route::get('/{arrival}', [ArrivalController::class, 'show']);
            Route::delete('/{arrival}', [ArrivalController::class, 'delete']);
        });


        Route::group(['prefix' => 'customer', 'middleware' => 'vendor_employee_access:customers'], function () {
            Route::get('/', [CustomerController::class, 'index']);
            Route::get('/search', [CustomerController::class, 'search']);
            Route::post('/', [CustomerController::class, 'storeVendor']);
            Route::post('/import', [CustomerController::class, 'import']);
            Route::get('/template', [CustomerController::class, 'getTemplate']);
            Route::get('/{customer}', [CustomerController::class, 'show']);
            Route::put('/{customer}', [CustomerController::class, 'update']);
            Route::get('/{customer}/general-info', [CustomerController::class, 'generalInfo']);
            Route::get('/{customer}/loyalty-points', [CustomerController::class, 'loyaltyPoints']);
            Route::post('/{customer}/add-points', [CustomerController::class, 'addPoints']);
        });

        Route::group(['prefix' => 'sales'], function () {
            Route::get('/', [SaleController::class, 'index']);
            Route::get('/{sale}', [SaleController::class, 'show']);
            Route::post('/', [SaleController::class, 'store']);
            Route::put('/{sale}', [SaleController::class, 'update']);
            Route::post('/status/{sale}', [SaleController::class, 'toggleStatus']);
            Route::delete('/{sale}', [SaleController::class, 'delete']);
        });


        Route::group(['prefix' => 'finance', 'middleware' => 'vendor_employee_access:finance'], function () {
            Route::group(['prefix' => 'counterparties', 'middleware' => 'vendor_employee_access:finance'], function () {
                Route::get('/get-type-counterparties', [CounterpartyController::class, 'getTypeCounterparties']);
                Route::get('/', [CounterpartyController::class, 'index']);
                Route::get('/search', [CounterpartyController::class, 'search']);
                Route::post('/', [CounterpartyController::class, 'store']);
                Route::get('/types', [CounterpartyController::class, 'types']);
                Route::get('/statuses', [CounterpartyController::class, 'statuses']);
                
                // Custom type management routes
                Route::get('/custom-types', [CounterpartyController::class, 'getCustomTypes']);
                Route::post('/custom-types', [CounterpartyController::class, 'storeCustomType']);
                Route::put('/custom-types/{id}', [CounterpartyController::class, 'updateCustomType']);
                Route::delete('/custom-types/{id}', [CounterpartyController::class, 'destroyCustomType']);
                
                Route::get('/{counterparty}', [CounterpartyController::class, 'show']);
                Route::put('/{counterparty}', [CounterpartyController::class, 'update']);
                Route::delete('/{counterparty}', [CounterpartyController::class, 'destroy']);
            });
            
            Route::get('product-profitability', [FinanceController::class, 'productProfitability']);
            Route::post('/product-profitability/export', [FinanceController::class, 'productProfitabilityExport']);
            Route::get('main-finance-statistics', [FinanceController::class, 'mainIncomeStatistics']);
            Route::get('monthly-finance-statistics', [FinanceController::class, 'monthlyFinanceStatistics']);
            Route::get('profit-margin', [FinanceController::class, 'marginStatistics']);
            Route::get('calendar/{yearMonth}', [FinanceController::class, 'calendar']);
            Route::get('wallets', [FinanceController::class, 'wallets']);
            Route::post('wallets/activate', [FinanceController::class, 'activateWallet']);
            Route::post('wallets/deactivate', [FinanceController::class, 'deactivateWallet']);


        });

        Route::group(['prefix' => 'transaction', 'middleware' => 'vendor_employee_access:finance'], function () {
            Route::get('/', [TransactionController::class, 'index']);
            Route::get('/countTotals', [TransactionController::class, 'countTotals']);
            Route::post('/', [TransactionController::class, 'store']);
            Route::get('/types', [TransactionController::class, 'typesOptions']);
            Route::get('/calendar', [TransactionController::class, 'calendar']);

            Route::get('/available-wallets', [WalletController::class, 'availableWallets']);
            Route::post('/wallets', [WalletController::class, 'store']);
            Route::get('/wallet-transactions', [WalletController::class, 'transactions']);
            Route::post('/wallet-transactions', [WalletController::class, 'makeTransaction']);
            Route::get('/wallet-transaction-stats', [WalletController::class, 'transactionStats']);

            Route::get('/{transaction}', [TransactionController::class, 'show']);
            Route::put('/{transaction}', [TransactionController::class, 'update']);
        });

        Route::group(['prefix' => 'transaction-category', 'middleware' => 'vendor_employee_access:finance'], function () {
            Route::get('/', [TransactionCategoryController::class, 'index']);
            Route::get('/subcategories', [TransactionCategoryController::class, 'subcategories']);
            Route::get('/subcategories/{category}', [TransactionCategoryController::class, 'getSubcategoriesByCategoryIdOption']);
            Route::get('/category', [TransactionCategoryController::class, 'categoryOption']);
            Route::post('/', [TransactionCategoryController::class, 'store']);
            Route::put('/{category}', [TransactionCategoryController::class, 'update']);
            Route::delete('/{category}', [TransactionCategoryController::class, 'delete']);
        });

        Route::group(['prefix' => 'transaction-schedules', 'middleware' => 'vendor_employee_access:finance'], function () {
            Route::get('/', [TransactionScheduleController::class, 'index']);
            Route::post('/', [TransactionScheduleController::class, 'store']);
            Route::get('/calendar/{yearMonth}', [TransactionScheduleController::class, 'calendar']);
            Route::get('/calendar-with-approval/{yearMonth}', [TransactionScheduleController::class, 'calendarWithApproval']);
            Route::get('/approvals', [TransactionScheduleController::class, 'approvalsForDate']);
            Route::post('/approve/{id}', [TransactionScheduleController::class, 'approve']);
            Route::get('/cycle-types', [TransactionScheduleController::class, 'getCycleTypes']);
            Route::get('/statuses', [TransactionScheduleController::class, 'getStatuses']);
            Route::get('/{id}', [TransactionScheduleController::class, 'show']);
            Route::put('/{id}', [TransactionScheduleController::class, 'update']);
            Route::delete('/{id}', [TransactionScheduleController::class, 'destroy']);
        });

        Route::group(['prefix' => 'ai', 'middleware' => 'vendor_employee_access:analytics'], function () {
            Route::post('/insights', [\App\Http\Controllers\Api\V3\vendor\AIInsightsController::class, 'getInsights']);
            Route::get('/inactive-customers', [\App\Http\Controllers\Api\V3\vendor\AIInsightsController::class, 'getInactiveCustomers'])
                ->name('vendor.ai.inactive-customers');
        });

        Route::group(['prefix' => 'receipts', 'middleware' => 'vendor_employee_access:inventory'], function () {
            Route::get('/', [ReceiptController::class, 'index']);
            Route::post('/', [ReceiptController::class, 'store']);
            Route::get('/statistics', [ReceiptController::class, 'statistics']);
            Route::get('/status/{status}', [ReceiptController::class, 'byStatus']);
            Route::get('/{receipt}', [ReceiptController::class, 'show']);
            Route::put('/{receipt}', [ReceiptController::class, 'update']);
        });

        Route::group(['prefix' => 'warehouse-transfers', 'middleware' => 'vendor_employee_access:inventory'], function () {
            Route::get('/', [WarehouseTransferController::class, 'index']);
            Route::post('/', [WarehouseTransferController::class, 'store']);
            Route::get('/types', [WarehouseTransferController::class, 'transferTypes']);
            Route::get('/statuses', [WarehouseTransferController::class, 'statuses']);
            Route::get('/incoming/count', [WarehouseTransferController::class, 'incomingCount']);
            Route::get('/incoming', [WarehouseTransferController::class, 'incoming']);
            Route::get('/search-vendors', [WarehouseTransferController::class, 'searchVendors']);
            Route::get('/{transfer}', [WarehouseTransferController::class, 'show']);
            Route::put('/{transfer}', [WarehouseTransferController::class, 'update']);
            Route::post('/{transfer}/accept', [WarehouseTransferController::class, 'acceptTransfer']);
            Route::post('/{transfer}/reject', [WarehouseTransferController::class, 'rejectTransfer']);
        });
    });
});
