<?php

namespace App\Http\Controllers\Api\V3\admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * List all vendors with search functionality
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);

        $vendors = Vendor::query()
            ->with(['store'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('f_name', 'like', '%' . $search . '%')
                        ->orWhere('l_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $vendors->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'f_name' => $vendor->f_name,
                    'l_name' => $vendor->l_name,
                    'full_name' => trim($vendor->f_name . ' ' . $vendor->l_name),
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                    'image' => $vendor->image,
                    'store' => $vendor->store ? [
                        'id' => $vendor->store->id,
                        'name' => $vendor->store->name,
                    ] : null,
                    'status' => $vendor->status,
                    'created_at' => $vendor->created_at?->format('d.m.Y H:i:s'),
                ];
            }),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
            'links' => [
                'first' => $vendors->url(1),
                'last' => $vendors->url($vendors->lastPage()),
                'prev' => $vendors->previousPageUrl(),
                'next' => $vendors->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Search vendors by phone or ID (for dropdowns/autocomplete)
     */
    public function search(Request $request)
    {
        $search = $request->input('search');

        if (!$search) {
            return response()->json(['data' => []]);
        }

        $vendors = Vendor::query()
            ->with(['store'])
            ->where(function ($query) use ($search) {
                $query->where('id', $search)
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('f_name', 'like', '%' . $search . '%')
                    ->orWhere('l_name', 'like', '%' . $search . '%');
            })
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $vendors->map(function ($vendor) {
                return [
                    'id' => $vendor->id,
                    'f_name' => $vendor->f_name,
                    'l_name' => $vendor->l_name,
                    'full_name' => trim($vendor->f_name . ' ' . $vendor->l_name),
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                    'store' => $vendor->store ? [
                        'id' => $vendor->store->id,
                        'name' => $vendor->store->name,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * Get vendor by ID
     */
    public function show(Vendor $vendor)
    {
        $vendor->load(['store', 'employees']);

        return response()->json([
            'data' => [
                'id' => $vendor->id,
                'f_name' => $vendor->f_name,
                'l_name' => $vendor->l_name,
                'full_name' => trim($vendor->f_name . ' ' . $vendor->l_name),
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'image' => $vendor->image,
                'store' => $vendor->store ? [
                    'id' => $vendor->store->id,
                    'name' => $vendor->store->name,
                ] : null,
                'employees_count' => $vendor->employees->count(),
                'status' => $vendor->status,
                'created_at' => $vendor->created_at?->format('d.m.Y H:i:s'),
            ],
        ]);
    }
}

