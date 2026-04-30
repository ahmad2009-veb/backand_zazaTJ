<?php

namespace App\Services;

use App\Models\User;
use App\Models\CustomerImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;
use Carbon\Carbon;

class CustomerImportService
{
    protected $storeId;
    protected $requiredHeaders = [
        'name',
        'phone',
    ];

    protected $optionalHeaders = [
        'purchase_date',
        'products',
        'total_order_price',
        'discount',
        'birthdate',
        'source',
        'loyalty_points',
        'loyalty_points_percentage',
        'size',
        'products_quantity',
        // 'last_purchase_date'
    ];

    public function __construct($storeId)
    {
        $this->storeId = $storeId;
    }

    public function import(UploadedFile $file): array
    {
        try {
            // Import the file using FastExcel
            $collections = (new FastExcel)->import($file);

            if ($collections->isEmpty()) {
                throw new \Exception('Файл пуст');
            }

            // Get headers from first row
            $firstRow = $collections->first();
            $headers = array_keys($firstRow);
            $this->validateHeaders($headers);

            // Process data rows
            $results = [
                'total' => 0,
                'imported' => 0,
                'errors' => [],
                'failed_rows' => []
            ];

            DB::beginTransaction();

            $rowNumber = 1;
            foreach ($collections as $rowData) {
                $rowNumber++;
                $results['total']++;

                try {
                    // Clean the row data keys to remove Russian words within header
                    $cleanedRowData = $this->cleanRowDataKeys($rowData);
                    $this->processCustomerRow($cleanedRowData, $rowNumber);
                    $results['imported']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Строка " . $rowNumber . ": " . $e->getMessage();
                    $results['failed_rows'][] = [
                        'row_number' => $rowNumber,
                        'data' => $rowData,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Customer import error on row " . $rowNumber . ": " . $e->getMessage());
                }
            }

            DB::commit();

            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Ошибка импорта: ' . $e->getMessage());
        }
    }

    protected function validateHeaders(array $headers): void
    {
        $missingHeaders = [];

        $cleanHeaders = array_map(function($header) {
            return trim(explode('(', $header)[0]);
        }, $headers);

        foreach ($this->requiredHeaders as $requiredHeader) {
            if (!in_array($requiredHeader, $cleanHeaders)) {
                $missingHeaders[] = $requiredHeader;
            }
        }

        if (!empty($missingHeaders)) {
            throw new \Exception('Отсутствуют обязательные колонки: ' . implode(', ', $missingHeaders));
        }
    }

    protected function cleanRowDataKeys(array $rowData): array
    {
        $cleanedData = [];

        foreach ($rowData as $key => $value) {
            $cleanKey = trim(explode('(', $key)[0]);
            $cleanedData[$cleanKey] = $value;
        }

        return $cleanedData;
    }



    protected function processCustomerRow(array $data, int $rowNumber): void
    {
        if (empty($data['name'])) {
            throw new \Exception('ФИО обязательно');
        }

        if (empty($data['phone'])) {
            throw new \Exception('Телефон обязателен');
        }

        $phone = $this->parsePhone($data['phone']);

        // Parse dates
        $birthDate = $this->parseDate($data['birthdate']);
        $purchaseDate = $this->parseDate($data['purchase_date']);
        // $lastPurchaseDate = $this->parseDate($data['last_purchase_date']);

        $this->validateDates($birthDate, $purchaseDate);

        // Parse numeric values
        $loyaltyPoints = $this->parseNumeric($data['loyalty_points'], 0);
        $loyaltyPercentage = $this->parseNumeric($data['loyalty_points_percentage'], 0);
        $totalAmount = $this->parseNumeric($data['total_order_price'], 0);
        $discount = $this->parseNumeric($data['discount'], 0);
        $orderCount = $this->parseNumeric($data['products_quantity'], 1);

        $customer = User::updateOrCreate(
            [
                'phone' => $phone,
                'created_by' => $this->storeId
            ],
            [
                'f_name' => trim($data['name']),
                'birth_date' => $birthDate,
                'source' => $data['source'] ?? null,
                'loyalty_points' => $loyaltyPoints,
                'loyalty_points_percentage' => $loyaltyPercentage,
                'password' => bcrypt('password'),
            ]
        );
        
        CustomerImport::create([
            'user_id' => $customer->id,
            'store_id' => $this->storeId,
            'purchase_date' => $purchaseDate,
            'products' => $data['products'] ?? null,
            'total_order_price' => $totalAmount,
            'discount' => $discount,
            'size' => $data['size'] ?? null,
            'total_order_count' => $orderCount
        ]);
    }

    protected function parsePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure phone starts with +992 for validation Tajikistan only
        if (strlen($phone) === 9) {
            $phone = '992' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '992') {
            // Already has country code
        } else {
            throw new \Exception('Неверный формат телефона: ' . $phone);
        }

        return '+' . $phone;
    }

    protected function parseDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        try {
            // Handle Excel date serial numbers (FastExcel usually converts these automatically)
            if (is_numeric($dateValue) && $dateValue > 25569) { // Excel epoch starts at 1900-01-01
                $unixTimestamp = ($dateValue - 25569) * 86400;
                $date = Carbon::createFromTimestamp($unixTimestamp);
                return $date->format('Y-m-d');
            }

            // Handle string dates in D/M/Y format
            if (is_string($dateValue) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateValue)) {
                $date = Carbon::createFromFormat('d/m/Y', $dateValue);
                return $date->format('Y-m-d');
            }

            // Fallback to general parsing
            $date = Carbon::parse($dateValue);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseNumeric($value, $default = 0): float
    {
        if (empty($value)) {
            return $default;
        }

        // Remove any non-numeric characters except decimal point
        $value = preg_replace('/[^0-9.]/', '', $value);

        return (float) $value ?: $default;
    }

    protected function validateDates(?string $birthDate, ?string $purchaseDate): void
    {
        $today = Carbon::now();

        // Validate purchase date - cannot be more than today (max is today)
        if ($purchaseDate) {
            $purchaseDateCarbon = Carbon::parse($purchaseDate);
            if ($purchaseDateCarbon->gt($today)) {
                throw new \Exception('Дата покупки не может быть больше сегодняшней даты');
            }
        }

        // Validate birth date - customer must be at least 12 years old
        if ($birthDate) {
            $birthDateCarbon = Carbon::parse($birthDate);
            $age = $birthDateCarbon->diffInYears($today);

            if ($age < 12) {
                throw new \Exception('Возраст клиента должен быть не менее 12 лет');
            }
        }
    }
}
