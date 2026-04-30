<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CustomerAddress;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;

use App\Http\Resources\Admin\CustomerListResource;
use App\Http\Resources\Admin\CustomerSearchResource;
use App\Http\Resources\Customer\CustomerResource as VendorCustomerResource;
use App\Http\Resources\Customer\OrderResource as CustomerOrderResource;
use App\Http\Resources\Customer\CustomerImportResource;
use App\Http\Resources\Customer\LoyaltyPointTransactionResource;
use App\Services\LoyaltyPointsService;
use App\Services\CustomerImportService;
use App\Http\Requests\Api\v3\Vendor\ImportCustomerRequest;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;


class CustomerController extends Controller
{
    use VendorEmployeeAccess;

    protected LoyaltyPointsService $loyaltyPointsService;

    public function __construct(LoyaltyPointsService $loyaltyPointsService)
    {
        $this->loyaltyPointsService = $loyaltyPointsService;
    }
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor ? $vendor->store()->first() : null;

        if (!$store) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $search = trim($request->input('search', ''));
        $keys   = $search === '' ? [] : array_filter(explode(' ', $search));

        $query = User::query()
            ->where('created_by', $store->id)
            ->with(['createdClients', 'orders', 'addresses', 'customerImports'])
            ->withSum(['orders as total_order_amount' => function ($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled']);
            }], 'order_amount')
            ->withCount(['orders as total_order_count' => function ($q) {
                $q->whereNotIn('order_status', ['refunded', 'canceled']);
            }]);
    

        if (count($keys) > 0) {
            $query->where(function($q) use ($keys) {
                foreach ($keys as $word) {
                    $q->orWhere('f_name', 'like', "%{$word}%")
                      ->orWhere('l_name', 'like', "%{$word}%")
                      ->orWhere('email', 'like', "%{$word}%")
                      ->orWhere('phone', 'like', "%{$word}%");
                }
            });
        }

        // Filter by birthday month
        if ($request->has('birthday') && $request->birthday === 'monthly') {
            $currentMonth = now()->month;
            $query->whereMonth('birth_date', $currentMonth);
        }

        // Filter by new customers (joined this month)
        if ($request->has('type') && $request->type === 'new') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }
    
        $perPage   = min((int)$request->input('per_page', 15), 100);
        $customers = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    
        return CustomerListResource::collection($customers);
    }
    

    public function search(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor ? $vendor->store()->first() : null;

        if (!$store) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $customers = User::query()
            ->where('created_by', $store->id)
            ->limit(8)
            ->where(function($query) use ($request) {
                $query->where('f_name', 'like', '%' . $request->search . '%')
                    ->orWhere('l_name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            })
            ->with(['addresses', 'orders', 'customerImports'])
            ->get();
        return CustomerSearchResource::collection($customers);
    }

    public function storeVendor(Request $request)
    {
        $vendor = $this->getActingVendor();
        $store = $vendor ? $vendor->store()->first() : null;

        if (!$store) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'address'=> 'required|string',
            'phone' => [
                'required',
                'string',
                'size:13',
                Rule::unique('users')->where(function ($query) use ($store) {
                    return $query->where('created_by', $store->id);
                }),
            ],
            'birthdate' => 'nullable|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'source' => 'nullable|string|max:255',
            'loyalty_percentage' => 'nullable|numeric|min:0|max:100',
            'loyalty_enabled' => 'boolean',
            'manual_points_reason' => 'nullable|string',
        ]);

        // Parse birthdate from DD.MM.YYYY format to YYYY-MM-DD
        $birthDate = null;
        if ($request->birthdate) {
            try {
                $birthDate = \Carbon\Carbon::createFromFormat('d.m.Y', $request->birthdate)->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid birthdate format. Expected DD.MM.YYYY'], 422);
            }
        }

        $loyaltyPercentage = 0.00;
        if ($request->loyalty_enabled && $request->loyalty_percentage) {
            $loyaltyPercentage = $request->loyalty_percentage;
        }

        $customer = User::create([
            'f_name' => $request->name,
            'user_address' => $request->address,
            'phone' => $request->phone,
            'birth_date' => $birthDate,
            'source' => $request->source ?? null,
            'loyalty_points_percentage' => $loyaltyPercentage,
            'password' => bcrypt(Str::random(8)),
            'created_by' => $store->id
        ]);

        if ($request->manual_points_reason && $loyaltyPercentage > 0) {
            preg_match('/\d+/', $request->manual_points_reason, $matches);
            $manualPoints = isset($matches[0]) ? (float)$matches[0] : 0;

            if ($manualPoints > 0) {
                $this->loyaltyPointsService->addPointsManually(
                    $customer,
                    $manualPoints,
                    $request->manual_points_reason,
                    $vendor->id
                );
            }
        }

        return CustomerSearchResource::make($customer);
    }

    public function show(User $customer)
    {
        $customer->load(['orders', 'addresses', 'customerImports']);

        return VendorCustomerResource::make($customer);
    }

    /**
     * Update customer information
     */
    public function update(Request $request, User $customer)
    {
        // Get the acting vendor and store
        $vendor = $this->getActingVendor();
        $store = $vendor ? $vendor->store()->first() : null;

        if (!$store) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        // Validate the customer_id (user_number) exists for this vendor
        $vendorStoreId = $store->id;
        $customerIdToUpdate = $request->input('customer_id');

        // Find the actual customer by user_number within vendor scope
        $targetCustomer = User::where('user_number', $customerIdToUpdate)
                             ->where('created_by', $vendorStoreId)
                             ->first();

        if (!$targetCustomer) {
            return response()->json([
                'message' => 'Выбранный номер клиента недействителен.',
                'errors' => [
                    'customer_id' => ['Клиент с номером ' . $customerIdToUpdate . ' не найден.']
                ]
            ], 422);
        }

        // If trying to update a different customer than the one in the route
        if ($targetCustomer->id !== $customer->id) {
            return response()->json([
                'message' => 'Несоответствие ID клиента.',
                'errors' => [
                    'customer_id' => ['ID клиента в URL не соответствует ID в данных.']
                ]
            ], 422);
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => [
                'required',
                'string',
                'size:13',
                Rule::unique('users')->where(function ($query) use ($vendorStoreId) {
                    return $query->where('created_by', $vendorStoreId);
                })->ignore($customer->id),
            ],
            'birth_date' => 'nullable|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'source' => 'nullable|string|max:255',
            'loyalty_point_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'image' => 'nullable|string',
            'customer_id' => 'required|string',
        ], [
            // Russian validation messages
            'full_name.required' => 'Поле полное имя обязательно для заполнения.',
            'full_name.string' => 'Поле полное имя должно быть строкой.',
            'full_name.max' => 'Поле полное имя не должно превышать 255 символов.',
            'address.required' => 'Поле адрес обязательно для заполнения.',
            'address.string' => 'Поле адрес должно быть строкой.',
            'address.max' => 'Поле адрес не должно превышать 500 символов.',
            'phone.required' => 'Поле телефон обязательно для заполнения.',
            'phone.string' => 'Поле телефон должно быть строкой.',
            'phone.size' => 'Поле телефон должно содержать ровно 13 символов.',
            'phone.unique' => 'Такой номер телефона уже существует.',
            'birth_date.regex' => 'Поле дата рождения должно быть в формате ДД.ММ.ГГГГ.',
            'source.string' => 'Поле источник должно быть строкой.',
            'source.max' => 'Поле источник не должно превышать 255 символов.',
            'loyalty_point_percentage.numeric' => 'Поле процент лояльности должно быть числом.',
            'loyalty_point_percentage.min' => 'Поле процент лояльности должно быть не менее 0.',
            'loyalty_point_percentage.max' => 'Поле процент лояльности должно быть не более 100.',
            'notes.string' => 'Поле заметки должно быть строкой.',
            'image.string' => 'Поле изображение должно быть строкой.',
            'customer_id.required' => 'Поле номер клиента обязательно для заполнения.',
        ]);

        $birthDate = $customer->birth_date;
        if ($request->birth_date) {
            try {
                $birthDate = \Carbon\Carbon::createFromFormat('d.m.Y', $request->birth_date)->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['message' => 'Неверный формат даты рождения. Ожидается ДД.ММ.ГГГГ'], 422);
            }
        }

        $customer->update([
            'f_name' => $request->full_name,
            'user_address' => $request->address,
            'phone' => $request->phone,
            'birth_date' => $birthDate,
            'source' => $request->source ?? $customer->source,
            'loyalty_points_percentage' => $request->loyalty_point_percentage ?? $customer->loyalty_points_percentage,
        ]);

        $customer->load(['orders', 'addresses', 'customerImports']);

        return VendorCustomerResource::make($customer);
    }

    public function generalInfo(Request $request, User $customer)
    {
        $perPage = $request->get('per_page', 12);

        // Check if imported parameter is true
        if ($request->has('imported') && $request->imported === 'true') {
            // Return customer import data with same structure as orders
            $customerImports = $customer->customerImports()
                ->orderBy('purchase_date', 'desc')
                ->paginate($perPage ?? 10);

            return CustomerImportResource::collection($customerImports);
        }

        // Default behavior - return regular orders
        $orders = $customer->orders()
            ->with(['details.product'])
            ->whereNotIn('order_status', ['refunded', 'canceled'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage ?? 10);

        return CustomerOrderResource::collection($orders);
    }

    public function loyaltyPoints(Request $request, User $customer)
    {
        $perPage = $request->get('per_page', 12);

        LoyaltyPointTransactionResource::resetCheckpoint();

        $transactions = $this->loyaltyPointsService->getCustomerPointsHistory($customer, $perPage);
        $summary = $this->loyaltyPointsService->getCustomerLoyaltySummary($customer);

        return LoyaltyPointTransactionResource::collection($transactions)
            ->additional($summary);
    }

    public function addPoints(Request $request, User $customer)
    {
        $request->validate([
            'points_to_add' => 'required|numeric|min:0',
            'comment' => 'required|string',
        ]);

        $this->loyaltyPointsService->addPointsManually(
            $customer,
            $request->points_to_add,
            $request->comment,
            auth()->user()->id
        );

        $customer->refresh();
        $customer->load(['orders', 'addresses']);

        return response()->json([
            'message' => 'Баллы успешно добавлены',
            'data' => VendorCustomerResource::make($customer)
        ]);
    }

    public function import(ImportCustomerRequest $request)
    {
        try {
            $vendor = $this->getActingVendor();
            $store = $vendor ? $vendor->store()->first() : null;

            if (!$store) {
                return response()->json(['message' => 'Не авторизован'], 401);
            }

            $storeId = $store->id;
            $importService = new CustomerImportService($storeId);

            $file = $request->validated()['file'];
            $results = $importService->import($file);

            $message = "Импорт завершен. Обработано: {$results['total']}, Импортировано: {$results['imported']}";

            $responseData = [
                'message' => $message,
                'data' => $results
            ];

            if (!empty($results['errors'])) {
                $message .= ". Ошибки: " . count($results['errors']);
                $responseData['message'] = $message;

                // Generate error report Excel file
                $errorFilePath = $this->generateErrorReport($results['failed_rows']);
                $responseData['error_file_url'] = $errorFilePath;
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            // Only return 422 for critical errors (file format, missing required headers, etc.)
            // Row-level validation errors are handled within the import process
            return response()->json([
                'message' => 'Критическая ошибка импорта: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getTemplate()
    {
        $filePath = 'template/customer_import_template.xlsx';
        if (Storage::disk('public')->exists($filePath)) {
            
            return Storage::disk('public')->download($filePath);
        }
        return response()->json(['error' => 'Файл не найден.'], 404);
    }

    private function generateErrorReport(array $failedRows): string
    {
        $errorData = [];

        foreach ($failedRows as $failedRow) {
            $rowData = $failedRow['data'];

            $rowData = $this->formatDatesInRowData($rowData);

            $rowData['ERROR'] = $failedRow['error'];
            $errorData[] = $rowData;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "customer_import_errors_{$timestamp}.xlsx";
        $filePath = storage_path("app/public/temp/{$filename}");

        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // Create Excel file with error data
        (new FastExcel($errorData))->export($filePath);

        // Return download URL
        return url("storage/temp/{$filename}");
    }

    public function downloadErrorReport($filename)
    {
        $filePath = storage_path("app/public/temp/{$filename}");

        if (file_exists($filePath)) {
            return response()->download($filePath)->deleteFileAfterSend(true);
        }

        return response()->json(['error' => 'Файл не найден.'], 404);
    }

    private function formatDatesInRowData(array $rowData): array
    {
        $dateFields = ['birthdate (дата рождения)', 'purchase_date (дата покупки)', 'birthdate', 'purchase_date'];

        foreach ($dateFields as $field) {
            if (isset($rowData[$field]) && is_numeric($rowData[$field]) && $rowData[$field] > 25569) {
                $unixTimestamp = ($rowData[$field] - 25569) * 86400;
                $date = \Carbon\Carbon::createFromTimestamp($unixTimestamp);
                $rowData[$field] = $date->format('d/m/Y');
            }
        }

        return $rowData;
    }

}
