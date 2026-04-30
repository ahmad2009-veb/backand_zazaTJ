<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Resources\Json\JsonResource;

class CounterpartyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Use effective attributes when vendor_reference_id is set (to get fresh data from referenced vendor)
        $counterparty = $this->isVendorReference() ? $this->effective_counterparty : $this->counterparty;
        $name = $this->isVendorReference() ? $this->effective_name : $this->name;
        $address = $this->isVendorReference() ? $this->effective_address : $this->address;
        $phone = $this->isVendorReference() ? $this->effective_phone : $this->phone;
        
        // For vendor-referenced counterparties, get photo from vendor's image
        $photo = $this->photo_url;
        if ($this->isVendorReference() && $this->referencedVendor && !$photo) {
            $photo = $this->referencedVendor->image ? 'storage/' . $this->referencedVendor->image : null;
        }
        
        // Determine type value and label (supports both enum and custom types)
        // Use custom type value if available, otherwise use enum value
        $typeValue = $this->custom_type_id && $this->customType 
            ? $this->customType->value 
            : ($this->type?->value);
        $typeLabel = $this->effective_type_label ?? ($this->type?->label());
        
        return [
            'id' => $this->id,
            'counterparty' => $counterparty,
            'name' => $name,
            'address' => $address,
            'requisite' => $this->requisite,
            'phone' => $phone,
            'type' => $typeValue,
            'type_label' => $typeLabel,
            'is_custom_type' => !is_null($this->custom_type_id),
            'balance' => $this->balance,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'photo' => $photo,
            'vendor_reference_id' => $this->vendor_reference_id, // Include to identify vendor-referenced counterparties
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get the status label
     */
    private function getStatusLabel()
    {
        $statuses = [
            'active' => 'Активный',
            'inactive' => 'Неактивный',
        ];

        return $statuses[$this->status] ?? $this->status;
    }
}
