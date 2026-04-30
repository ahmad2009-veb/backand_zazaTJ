<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShowProductResource extends JsonResource
{
    protected static $auditLogs = [];

    public static function setAuditLogs($logs)
    {
        self::$auditLogs = $logs;
    }
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $audit = self::$auditLogs[$this->product_id] ?? null;
        $product = json_decode($this->product);

        // Handle variation - extract variation_type from variation_id
        $variation = null;
        $variationData = json_decode($this->variation, true);

        if ($variationData && is_array($variationData) && !empty($variationData)) {
            $variationId = $variationData[0] ?? null;

            if ($variationId) {
                // Extract variation_type from variation_id
                // Format: {variation_type}_{timestamp}_{index}
                $variationType = null;
                $firstUnderscorePos = strpos($variationId, '_');
                if ($firstUnderscorePos !== false) {
                    $variationType = substr($variationId, 0, $firstUnderscorePos);
                }

                $variation = [
                    'variation_id' => $variationId,
                    'variation_type' => $variationType,
                ];
            }
        }

        return [
            'id' => $this->id,
            'price' => $this->price,
            'discount' => $this->discount,
            'variation' => $variation,
            'add_ons' => json_decode($this->add_ons),
            'quantity' => $this->quantity,
            'total_add_on_price' => $this->total_add_on_price,
            'details' => [
                'id' => $product?->id,
                'name' => $product?->name,
                'price' => $product?->price,
                'image' => $product?->image !== null ? url('storage/product/' . $product->image) : null,
            ],
            'audit_log' => $audit ? [
                'original_quantity' => $audit->original_quantity,
                'new_quantity' => $audit->new_quantity,
                'action' => $audit->action,
                'actor_role' => $audit->actor_role,
                'logged_at' => $audit->logged_at,
            ] : null,
        ];
    }
}
