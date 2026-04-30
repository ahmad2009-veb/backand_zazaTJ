<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\Vendor\CounterpartyStoreRequest;
use App\Http\Requests\Api\v3\Vendor\CounterpartyUpdateRequest;
use App\Http\Resources\Vendor\CounterpartyResource;
use App\Http\Resources\Vendor\CounterpartySearchResource;
use App\Http\Traits\VendorEmployeeAccess;
use App\Models\Counterparty;
use App\Models\VendorCounterpartyType;
use App\Enums\CounterpartyTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CounterpartyController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Display a listing of counterparties
     */
    public function index(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $search = trim($request->input('search', ''));
        $keys = $search === '' ? [] : array_filter(explode(' ', $search));

        $query = Counterparty::forVendor($vendor->id)
            ->with(['referencedVendor.store', 'customType']);

        // Search functionality - split search into keywords and search across multiple fields
        if (count($keys) > 0) {
            $query->where(function($q) use ($keys) {
                foreach ($keys as $word) {
                    $q->orWhere('counterparty', 'like', "%{$word}%")
                      ->orWhere('name', 'like', "%{$word}%")
                      ->orWhere('notes', 'like', "%{$word}%")
                      // Also search in referenced vendor's data
                      ->orWhereHas('referencedVendor', function($vendorQuery) use ($word) {
                          $vendorQuery->where('f_name', 'like', "%{$word}%")
                                      ->orWhere('l_name', 'like', "%{$word}%")
                                      ->orWhere('phone', 'like', "%{$word}%")
                                      ->orWhereHas('store', function($storeQuery) use ($word) {
                                          $storeQuery->where('name', 'like', "%{$word}%")
                                                      ->orWhere('address', 'like', "%{$word}%");
                                      });
                      });
                }
            });
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->ofType($request->type);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $counterparties = $query->paginate($request->get('per_page', 15));

        return CounterpartyResource::collection($counterparties);
    }

    /**
     * Search counterparties (limited results for quick search)
     */
    public function search(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Counterparty::query()
            ->forVendor($vendor->id)
            ->with(['referencedVendor.store', 'customType'])
            ->limit(8);

        // Filter by type if provided
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
            
            // If searching for suppliers, also include vendors that have sent transfers
            if ($request->type === 'supplier') {
                // Get vendor IDs that have sent transfers to this vendor
                $supplierVendorIds = DB::table('warehouse_transfers')
                    ->where('to_vendor_id', $vendor->id)
                    ->where('transfer_type', 'external')
                    ->whereNotNull('vendor_id')
                    ->distinct()
                    ->pluck('vendor_id')
                    ->toArray();
                
                // Ensure counterparties exist for these vendors
                if (!empty($supplierVendorIds)) {
                    foreach ($supplierVendorIds as $supplierVendorId) {
                        // Check if counterparty already exists
                        $existingCounterparty = Counterparty::where('vendor_id', $vendor->id)
                            ->where('vendor_reference_id', $supplierVendorId)
                            ->where('type', 'supplier')
                            ->first();
                        
                        if (!$existingCounterparty) {
                            // Create counterparty for this vendor
                            $supplierVendor = \App\Models\Vendor::with('store')->find($supplierVendorId);
                            if ($supplierVendor) {
                                $store = $supplierVendor->store;
                                Counterparty::create([
                                    'vendor_id' => $vendor->id,
                                    'vendor_reference_id' => $supplierVendorId,
                                    'counterparty' => $supplierVendor->f_name . ' ' . ($supplierVendor->l_name ?? ''),
                                    'name' => $store ? $store->name : ($supplierVendor->f_name . ' ' . ($supplierVendor->l_name ?? '')),
                                    'address' => $store ? $store->address : null,
                                    'phone' => $supplierVendor->phone,
                                    'type' => 'supplier',
                                    'status' => 'active',
                                    'balance' => 0,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Search by text if provided
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('counterparty', 'like', '%' . $searchTerm . '%')
                  ->orWhere('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('notes', 'like', '%' . $searchTerm . '%')
                  // Also search in referenced vendor's data
                  ->orWhereHas('referencedVendor', function($vendorQuery) use ($searchTerm) {
                      $vendorQuery->where('f_name', 'like', '%' . $searchTerm . '%')
                                  ->orWhere('l_name', 'like', '%' . $searchTerm . '%')
                                  ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                                  ->orWhereHas('store', function($storeQuery) use ($searchTerm) {
                                      $storeQuery->where('name', 'like', '%' . $searchTerm . '%')
                                                  ->orWhere('address', 'like', '%' . $searchTerm . '%');
                                  });
                  });
            });
        }

        $counterparties = $query->get();

        return CounterpartySearchResource::collection($counterparties);
    }

    /**
     * Store a newly created counterparty
     */
    public function store(CounterpartyStoreRequest $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['vendor_id'] = $vendor->id;
            $data['status'] = $data['status'] ?? 'active';
            $data['balance'] = $data['balance'] ?? 0;

            // Check if type is an enum value or custom type name
            $typeValue = $data['type'];
            $enumValues = CounterpartyTypeEnum::values();
            
            if (in_array($typeValue, $enumValues)) {
                // It's an enum type
                $data['custom_type_id'] = null;
            } else {
                // It's a custom type - look it up by value
                $customType = VendorCounterpartyType::where('value', $typeValue)
                    ->where('vendor_id', $vendor->id)
                    ->first();
                
                if (!$customType) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Тип "' . $typeValue . '" не найден. Используйте один из стандартных типов или сначала создайте пользовательский тип через /custom-types endpoint.',
                        'errors' => [
                            'type' => ['Тип "' . $typeValue . '" не существует. Сначала создайте пользовательский тип.']
                        ]
                    ], 422);
                }
                
                // Set custom_type_id and use OTHER as the enum type
                $data['custom_type_id'] = $customType->id;
                $data['type'] = CounterpartyTypeEnum::OTHER->value;
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('counterparty_photos', $filename, 'public');
                $data['photo'] = $path;
            }

            $counterparty = Counterparty::create($data);

            DB::commit();

            return response()->json([
                'message' => 'Контрагент успешно создан',
                'data' => new CounterpartyResource($counterparty)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при создании контрагента: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified counterparty
     */
    public function show(Counterparty $counterparty)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if counterparty belongs to the vendor
        if ($counterparty->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Контрагент не найден'], 404);
        }

        // Load referenced vendor and custom type relationships
        $counterparty->load(['referencedVendor.store', 'customType']);

        return new CounterpartyResource($counterparty);
    }

    /**
     * Update the specified counterparty
     */
    public function update(CounterpartyUpdateRequest $request, Counterparty $counterparty)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if counterparty belongs to the vendor
        if ($counterparty->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Контрагент не найден'], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Check if type is being updated
            if (isset($data['type'])) {
                $typeValue = $data['type'];
                $enumValues = CounterpartyTypeEnum::values();
                
                if (in_array($typeValue, $enumValues)) {
                    // It's an enum type
                    $data['custom_type_id'] = null;
                } else {
                    // It's a custom type - look it up by value
                    $customType = VendorCounterpartyType::where('value', $typeValue)
                        ->where('vendor_id', $vendor->id)
                        ->first();
                    
                    if (!$customType) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Тип "' . $typeValue . '" не найден. Используйте один из стандартных типов или сначала создайте пользовательский тип через /custom-types endpoint.',
                            'errors' => [
                                'type' => ['Тип "' . $typeValue . '" не существует. Сначала создайте пользовательский тип.']
                            ]
                        ], 422);
                    }
                    
                    // Set custom_type_id and use OTHER as the enum type
                    $data['custom_type_id'] = $customType->id;
                    $data['type'] = CounterpartyTypeEnum::OTHER->value;
                }
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($counterparty->photo && Storage::disk('public')->exists($counterparty->photo)) {
                    Storage::disk('public')->delete($counterparty->photo);
                }

                $photo = $request->file('photo');
                $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('counterparty_photos', $filename, 'public');
                $data['photo'] = $path;
            }

            $counterparty->update($data);

            DB::commit();

            return response()->json([
                'message' => 'Контрагент успешно обновлен',
                'data' => new CounterpartyResource($counterparty->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при обновлении контрагента: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified counterparty
     */
    public function destroy(Counterparty $counterparty)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if counterparty belongs to the vendor
        if ($counterparty->vendor_id !== $vendor->id) {
            return response()->json(['message' => 'Контрагент не найден'], 404);
        }

        DB::beginTransaction();
        try {
            // Delete photo if exists
            if ($counterparty->photo && Storage::disk('public')->exists($counterparty->photo)) {
                Storage::disk('public')->delete($counterparty->photo);
            }

            $counterparty->delete();

            DB::commit();

            return response()->json([
                'message' => 'Контрагент успешно удален'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при удалении контрагента: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get counterparty types for dropdown (both default and custom types)
     */
    public function getTypeCounterparties()
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $types = Counterparty::getTypes($vendor->id);

        return response()->json([
            'types' => $types
        ]);
    }

    /**
     * Get counterparty statuses for dropdown
     */
    public function statuses()
    {
        return response()->json([
            'statuses' => collect(Counterparty::getStatuses())->map(function ($label, $value) {
                return ['value' => $value, 'label' => $label];
            })->values()
        ]);
    }

    /**
     * Get all custom counterparty types for the vendor
     */
    public function getCustomTypes()
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

            $customTypes = VendorCounterpartyType::where('vendor_id', $vendor->id)
                ->orderBy('value')
                ->get();

        return response()->json([
            'data' => $customTypes
        ]);
    }

    /**
     * Store a new custom counterparty type
     */
    public function storeCustomType(Request $request)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        // Auto-generate value from label (transliterate to latin/slug)
        $value = $this->transliterateToLatin($request->label);
        
        // Ensure value doesn't conflict with enum values
        $enumValues = CounterpartyTypeEnum::values();
        if (in_array($value, $enumValues)) {
            return response()->json([
                'message' => 'Сгенерированное значение "' . $value . '" конфликтует со стандартным типом. Попробуйте другое название.',
                'errors' => [
                    'label' => ['Название конфликтует со стандартным типом. Попробуйте другое название.']
                ]
            ], 422);
        }

        // Check uniqueness
        $existing = VendorCounterpartyType::where('vendor_id', $vendor->id)
            ->where('value', $value)
            ->first();
        
        if ($existing) {
            return response()->json([
                'message' => 'Тип с таким названием уже существует.',
                'errors' => [
                    'label' => ['Тип с таким названием уже существует.']
                ]
            ], 422);
        }

        $customType = VendorCounterpartyType::create([
            'vendor_id' => $vendor->id,
            'value' => $value,
            'label' => $request->label,
        ]);

        return response()->json([
            'message' => 'Пользовательский тип успешно создан',
            'data' => $customType
        ], 201);
    }

    /**
     * Update a custom counterparty type
     */
    public function updateCustomType(Request $request, $id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $customType = VendorCounterpartyType::where('id', $id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (!$customType) {
            return response()->json(['message' => 'Пользовательский тип не найден'], 404);
        }

        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Get label from request - handles both JSON and form-data
            $label = $request->input('label');
            
            if (empty($label)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Поле "label" обязательно для заполнения.'
                ], 422);
            }
            
            // Auto-regenerate value from new label (same logic as create)
            $newValue = $this->transliterateToLatin($label);
            
            // Check if new value conflicts with enum
            $enumValues = CounterpartyTypeEnum::values();
            if (in_array($newValue, $enumValues)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Сгенерированное значение "' . $newValue . '" конфликтует со стандартным типом.',
                    'errors' => [
                        'label' => ['Название конфликтует со стандартным типом.']
                    ]
                ], 422);
            }
            
            // Check uniqueness (excluding current record)
            $existing = VendorCounterpartyType::where('vendor_id', $vendor->id)
                ->where('value', $newValue)
                ->where('id', '!=', $id)
                ->first();
            
            if ($existing) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Тип с таким названием уже существует.',
                    'errors' => [
                        'label' => ['Тип с таким названием уже существует.']
                    ]
                ], 422);
            }
            
            // Update both value and label
            $customType->update([
                'value' => $newValue,
                'label' => $label,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Пользовательский тип успешно обновлен',
                'data' => $customType->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при обновлении типа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a custom counterparty type
     */
    public function destroyCustomType($id)
    {
        $vendor = $this->getActingVendor();
        if (!$vendor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        DB::beginTransaction();
        try {
            $customType = VendorCounterpartyType::where('id', $id)
                ->where('vendor_id', $vendor->id)
                ->first();

            if (!$customType) {
                return response()->json(['message' => 'Пользовательский тип не найден'], 404);
            }

            // Check if any counterparties are using this type
            $usageCount = Counterparty::where('custom_type_id', $id)->count();
            if ($usageCount > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Невозможно удалить тип, так как он используется ' . $usageCount . ' контрагентом(ами)'
                ], 400);
            }

            $deleted = $customType->delete();
            
            if (!$deleted) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Не удалось удалить тип'
                ], 500);
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Пользовательский тип успешно удален'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при удалении типа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transliterate label to latin/slug value
     * Converts Cyrillic and other characters to latin equivalents
     */
    private function transliterateToLatin($text)
    {
        // Russian/Cyrillic to Latin transliteration map
        $transliterationMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        // Convert to lowercase and transliterate
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, $transliterationMap);
        
        // Convert to slug (remove special chars, replace spaces with underscores)
        $value = Str::slug($text, '_');
        
        // Ensure it's not empty
        if (empty($value)) {
            $value = 'custom_type_' . time();
        }
        
        return $value;
    }
}
