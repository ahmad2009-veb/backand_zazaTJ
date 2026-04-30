<?php

namespace App\Http\Resources\Api\V3\Vendor;

use App\Enums\ReceiptStatusEnum;
use App\Models\Receipt;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Group receipt items by product
        $groupedItems = $this->groupItemsByProduct();

        // Determine receipt type
        $receiptType = $this->determineReceiptType();

        $data = [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'receipt_type' => $receiptType,
            'warehouse' => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ],
            'total_amount' => (float) $this->total_amount,
            'quantity' => count($groupedItems),
            'items' => $groupedItems,
            'notes' => $this->notes,
            'received_at' => $this->received_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];

        // Add counterparty or recipient vendor based on what's available
        // For vendor-to-vendor transfers, try to find the counterparty that references the recipient vendor
        $counterparty = $this->counterparty;
        
        // If no direct counterparty but recipient_vendor_id is set, look for counterparty that references this vendor
        // Get recipient_vendor_id from the model attribute or relationship
        $recipientVendorId = $this->recipient_vendor_id ?? ($this->recipientVendor ? $this->recipientVendor->id : null);
        
        // Also check warehouse transfer if recipient_vendor_id is not set but transfer exists
        if (!$recipientVendorId && $this->warehouse_transfer_id && $this->warehouseTransfer) {
            // For incoming transfers, the sender is transfer->vendor_id
            $recipientVendorId = $this->warehouseTransfer->vendor_id;
        }
        
        if (!$counterparty && $recipientVendorId) {
            $counterparty = \App\Models\Counterparty::where('vendor_id', $this->vendor_id)
                ->where('vendor_reference_id', $recipientVendorId)
                ->where('type', 'supplier')
                ->with('referencedVendor.store')
                ->first();
            
            // If still not found, ensure it exists (for older transfers that might not have counterparty)
            if (!$counterparty && $recipientVendorId) {
                $senderVendor = \App\Models\Vendor::with('store')->find($recipientVendorId);
                if ($senderVendor) {
                    $store = $senderVendor->store;
                    $counterparty = \App\Models\Counterparty::create([
                        'vendor_id' => $this->vendor_id,
                        'vendor_reference_id' => $recipientVendorId,
                        'counterparty' => $senderVendor->f_name . ' ' . ($senderVendor->l_name ?? ''),
                        'name' => $store ? $store->name : ($senderVendor->f_name . ' ' . ($senderVendor->l_name ?? '')),
                        'address' => $store ? $store->address : null,
                        'phone' => $senderVendor->phone,
                        'type' => 'supplier',
                        'status' => 'active',
                        'balance' => 0,
                    ]);
                    
                    // Update receipt to have counterparty_id for future requests
                    $this->counterparty_id = $counterparty->id;
                    $this->save();
                }
            }
        }
        
        if ($counterparty) {
            // Use effective attributes if it's a vendor-referenced counterparty
            $counterpartyName = $counterparty->isVendorReference() ? $counterparty->effective_name : $counterparty->name;
            $counterpartyPhone = $counterparty->isVendorReference() ? $counterparty->effective_phone : $counterparty->phone;
            
            $data['counterparty'] = [
                'id' => $counterparty->id,
                'name' => $counterpartyName,
                'type' => $counterparty->type?->value ?? $counterparty->type,
                'phone' => $counterpartyPhone,
            ];
            $data['recipient_vendor'] = null;
        } elseif ($this->recipientVendor) {
            // Fallback to recipient vendor if no counterparty found
            $data['counterparty'] = null;
            $data['recipient_vendor'] = [
                'id' => $this->recipientVendor->id,
                'name' => trim($this->recipientVendor->f_name . ' ' . ($this->recipientVendor->l_name ?? '')),
                'phone' => $this->recipientVendor->phone,
            ];
        } else {
            $data['counterparty'] = null;
            $data['recipient_vendor'] = null;
        }

        // Add warehouse transfer reference if exists
        if ($this->warehouse_transfer_id) {
            $transfer = $this->warehouseTransfer;
            $data['warehouse_transfer'] = [
                'id' => $this->warehouse_transfer_id,
                'transfer_number' => $transfer?->transfer_number,
                'transfer_type' => $transfer?->transfer_type?->value ?? null,
                'transfer_type_label' => $transfer?->transfer_type?->label() ?? null,
            ];
        }

        // Add original receipt reference if this is a refund receipt
        if ($this->original_receipt_id) {
            $data['original_receipt'] = [
                'id' => $this->original_receipt_id,
                'receipt_number' => $this->originalReceipt?->receipt_number,
            ];
        }

        // Check if this receipt has been refunded (for transfer receipts - pending or completed)
        if ($this->warehouse_transfer_id) {
            $hasRefund = Receipt::where('original_receipt_id', $this->id)
                ->exists();
            $data['is_refunded'] = $hasRefund;
            
            // If refunded, add refund receipt info
            if ($hasRefund) {
                $refundReceipt = Receipt::where('original_receipt_id', $this->id)->first();
                $data['refund_receipt'] = [
                    'id' => $refundReceipt->id,
                    'receipt_number' => $refundReceipt->receipt_number,
                    'status' => $refundReceipt->status->value,
                    'status_label' => $refundReceipt->status->label(),
                ];
            }
        } else {
            $data['is_refunded'] = false;
        }

        return $data;
    }

    /**
     * Determine the type of receipt
     * 
     * @return string 'regular'|'transfer_outgoing'|'transfer_incoming'|'refund'
     */
    private function determineReceiptType(): string
    {
        // Check if this is a refund receipt
        if ($this->original_receipt_id) {
            return 'refund';
        }

        // Check if this is a transfer receipt
        if ($this->warehouse_transfer_id && $this->warehouseTransfer) {
            $transfer = $this->warehouseTransfer;
            
            // Check if this receipt belongs to the sender (outgoing transfer)
            if ($this->vendor_id == $transfer->vendor_id) {
                return 'transfer_outgoing';
            }
            
            // Check if this receipt belongs to the recipient (incoming transfer)
            if ($transfer->to_vendor_id && $this->vendor_id == $transfer->to_vendor_id) {
                return 'transfer_incoming';
            }
        }

        // Default to regular receipt
        return 'regular';
    }

    /**
     * Group receipt items by product and include variations array
     */
    private function groupItemsByProduct()
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $productId = $item->product_id;

            // Initialize product group if not exists
            if (!isset($grouped[$productId])) {
                // Get categories and subcategories
                $categories = [];
                $subcategories = [];
                if ($item->product->category_ids) {
                    try {
                        $categoryIds = json_decode($item->product->category_ids, true);
                        if (is_array($categoryIds)) {
                            foreach ($categoryIds as $cat) {
                                if (isset($cat['id']) && isset($cat['position'])) {
                                    $categoryId = (int) $cat['id'];
                                    $category = \App\Models\Category::find($categoryId);
                                    if ($category) {
                                        if ($cat['position'] == 1) {
                                            $categories[] = [
                                                'id' => $category->id,
                                                'name' => $category->name,
                                            ];
                                        } elseif ($cat['position'] == 2) {
                                            $subcategories[] = [
                                                'id' => $category->id,
                                                'name' => $category->name,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Exception) {
                        // If category_ids is not valid JSON, ignore
                    }
                }

                // Load all variations for this product from product_variations table
                $productVariations = [];
                if ($item->product && method_exists($item->product, 'variations')) {
                    $variations = $item->product->variations()->get();
                    foreach ($variations as $variation) {
                        // Extract variation_type from variation_id
                        $variationType_extracted = null;
                        if ($variation->variation_id) {
                            $firstUnderscorePos = strpos($variation->variation_id, '_');
                            if ($firstUnderscorePos !== false) {
                                $variationType_extracted = substr($variation->variation_id, 0, $firstUnderscorePos);
                            }
                        }

                        $productVariations[] = [
                            'id' => $variation->id,
                            'variation_id' => $variation->variation_id,
                            'variation_type' => $variationType_extracted,
                            'attribute_value' => $variation->attribute_value,
                            'attribute_id' => $variation->attribute_id,
                            'barcode' => $variation->barcode,
                            'cost_price' => (float) $variation->cost_price,
                            'sale_price' => (float) $variation->sale_price,
                            'quantity' => (float) $variation->quantity,
                        ];
                    }
                }

                $grouped[$productId] = [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'code' => $item->product->product_code,
                        'image' => $item->product->image ?? null,
                        'variation_name' => $item->product->variation_name,
                        'categories' => $categories,
                        'subcategories' => $subcategories,
                        'variations' => $productVariations,
                    ],
                ];
            }

            // For products without variations, add receipt item data (quantity, purchase_price, retail_price)
            // If this item has no variation_id, it means it's a product without variations
            if (!$item->product_variation_id) {
                $grouped[$productId]['quantity'] = (float) $item->quantity;
                $grouped[$productId]['purchase_price'] = (float) $item->unit_price;
                $grouped[$productId]['retail_price'] = (float) ($item->product->price ?? $item->unit_price);
                $grouped[$productId]['total_price'] = (float) $item->total_price;
            }
        }

        // Return as array of values (not keyed by product_id)
        return array_values($grouped);
    }
}

