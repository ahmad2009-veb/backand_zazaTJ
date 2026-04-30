<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Обрабатывает заявки от ресторанов-партнёров и B2B-клиентов
 * с лендинга zazaTJ (React).
 *
 * GET|POST /api/v1/partner-request/restaurant
 * GET|POST /api/v1/partner-request/b2b
 * GET|POST /api/v1/partner-request/corporate
 * GET|POST /api/v1/partner-request/corporate-packages
 */
class PartnerRequestController extends Controller
{
    // ---------------------------------------------------------------
    // GET|POST /api/v1/partner-request/restaurant
    // Форма «Подключить ресторан» (страница /resDeliver на сайте)
    // ---------------------------------------------------------------
    public function storeRestaurant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'merchant_name' => 'required|string|max:200',
            'contact_name'  => 'nullable|string|max:200',
            'phone'         => 'required|string|max:30',
            'email'         => 'nullable|email|max:200',
            'city'          => 'nullable|string|max:100',
            'address'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $partner = PartnerRequest::create([
            'type'          => 'restaurant',
            'merchant_name' => $request->merchant_name,
            'contact_name'  => $request->contact_name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'city'          => $request->city,
            'address'       => $request->address,
            'status'        => 'new',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Ваша заявка принята! Мы свяжемся с вами в ближайшее время.',
            'id'      => $partner->id,
        ], 201);
    }

    // ---------------------------------------------------------------
    // GET|POST /api/v1/partner-request/b2b
    // Форма «B2B доставка» (страница /b2bdeliver на сайте)
    // ---------------------------------------------------------------
    public function storeB2B(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name'  => 'required|string|max:200',
            'contact_name'  => 'required|string|max:200',
            'phone'         => 'required|string|max:30',
            'email'         => 'required|email|max:200',
            'city'          => 'nullable|string|max:100',
            'description'   => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $partner = PartnerRequest::create([
            'type'          => 'b2b',
            'merchant_name' => $request->company_name, // нормализуем в одно поле
            'company_name'  => $request->company_name,
            'contact_name'  => $request->contact_name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'city'          => $request->city,
            'description'   => $request->description,
            'status'        => 'new',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Спасибо! Наш менеджер свяжется с вами в течение 24 часов.',
            'id'      => $partner->id,
        ], 201);
    }

    // ---------------------------------------------------------------
    // GET|POST /api/v1/partner-request/corporate(-packages)
    // Форма «Корпоративные пакеты» (страница /CorporatePackages)
    // ---------------------------------------------------------------
    public function storeCorporate(Request $request): JsonResponse
    {
        // Accept a few common aliases used by different landing form versions.
        $payload = [
            'contact_name' => $request->input('contact_name', $request->input('contact_person', $request->input('name'))),
            'phone'        => $request->input('phone', $request->input('phone_number')),
            'email'        => $request->input('email', $request->input('work_email')),
            'company_name' => $request->input('company_name', $request->input('company')),
            'city'         => $request->input('city'),
            'description'  => $request->input('description', $request->input('message')),
        ];

        $validator = Validator::make($payload, [
            'contact_name' => 'required|string|max:200',
            'phone'        => 'required|string|max:30',
            'email'        => 'required|email|max:200',
            'company_name' => 'nullable|string|max:200',
            'city'         => 'nullable|string|max:100',
            'description'  => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $partner = PartnerRequest::create([
            'type'          => 'corporate',
            'merchant_name' => $payload['company_name'] ?: $payload['contact_name'],
            'company_name'  => $payload['company_name'],
            'contact_name'  => $payload['contact_name'],
            'phone'         => $payload['phone'],
            'email'         => $payload['email'],
            'city'          => $payload['city'],
            'description'   => $payload['description'],
            'status'        => 'new',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Спасибо! Ваша заявка принята, отдел продаж свяжется с вами в ближайшее время.',
            'id'      => $partner->id,
        ], 201);
    }
}
