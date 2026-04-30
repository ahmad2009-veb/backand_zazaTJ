<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\Http\Controllers\Controller;
use App\Http\Traits\VendorEmployeeAccess;
use App\Enums\VariationTypeEnum;
use Illuminate\Http\Request;

class VariationController extends Controller
{
    use VendorEmployeeAccess;

    /**
     * Get all variation types
     * Returns: [
     *   {
     *     "value": 1,
     *     "label": "Несколько вариантов",
     *     "type_name": "multi"
     *   },
     *   {
     *     "value": 2,
     *     "label": "Один вариант",
     *     "type_name": "single"
     *   }
     * ]
     */
    public function types()
    {
        $types = collect(VariationTypeEnum::cases())->map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
                'type_name' => $case->typeName()
            ];
        })->toArray();

        return response()->json([
            'types' => $types
        ]);
    }
}

