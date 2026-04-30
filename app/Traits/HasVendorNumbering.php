<?php

namespace App\Traits;

trait HasVendorNumbering
{
    /**
     * Boot the trait
     */
    protected static function bootHasVendorNumbering()
    {
        static::creating(function ($model) {
            if (!$model->getAttribute($model->getNumberField())) {
                $model->setAttribute(
                    $model->getNumberField(),
                    $model->generateNextNumber()
                );
            }
        });
    }

    /**
     * Generate the next number for this vendor
     */
    public function generateNextNumber(): int
    {
        $vendorField = $this->getVendorField();
        $numberField = $this->getNumberField();
        $vendorId = $this->getVendorId();

        if (!$vendorId) {
            throw new \Exception("Vendor ID is required for numbering");
        }

        $maxNumber = static::where($vendorField, $vendorId)->max($numberField) ?? 0;
        return $maxNumber + 1;
    }

    /**
     * Get the vendor ID (handles different relationship structures)
     */
    public function getVendorId()
    {
        $vendorField = $this->getVendorField();

        // If model has direct vendor_id field
        if ($this->getAttribute($vendorField)) {
            return $this->getAttribute($vendorField);
        }

        // If model has store_id and store has vendor_id (like Orders)
        if ($vendorField === 'store_id' && $this->store && $this->store->vendor_id) {
            return $this->store->vendor_id;
        }

        return null;
    }

    /**
     * Get the vendor field name (override in model if different)
     */
    public function getVendorField(): string
    {
        return 'vendor_id';
    }

    /**
     * Get the number field name (must be implemented in each model)
     */
    abstract public function getNumberField(): string;

    /**
     * Regenerate numbers for existing records (use in migrations)
     */
    public static function regenerateNumbers()
    {
        $model = new static();
        $vendorField = $model->getVendorField();
        $numberField = $model->getNumberField();

        // Get all vendor IDs
        $vendorIds = static::distinct()->pluck($vendorField);

        foreach ($vendorIds as $vendorId) {
            $records = static::where($vendorField, $vendorId)
                           ->orderBy('id')
                           ->get();

            foreach ($records as $index => $record) {
                $record->update([$numberField => $index + 1]);
            }
        }
    }

    /**
     * Override toArray to replace id with vendor number in API responses
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Replace id with vendor number if it exists, otherwise keep original id
        $numberField = $this->getNumberField();
        $vendorNumber = $this->attributes[$numberField] ?? null;

        // Use vendor number if it exists, otherwise keep the original database ID
        $array['id'] = $vendorNumber ?? $array['id'];

        return $array;
    }

    /**
     * Get display ID - returns vendor number if exists, otherwise regular ID
     */
    public function getDisplayIdAttribute(): int
    {
        $numberField = $this->getNumberField();
        $vendorNumber = $this->getAttribute($numberField);

        // Return vendor number if it exists and is not null, otherwise return regular ID
        return $vendorNumber ?? $this->getAttribute('id');
    }

    /**
     * Get formatted display ID with prefix (e.g., "ORD-001", "EMP-005")
     */
    public function getFormattedDisplayIdAttribute(): string
    {
        $prefix = $this->getDisplayPrefix();
        $displayId = $this->display_id;

        // Pad with zeros for better formatting
        $paddedId = str_pad($displayId, 3, '0', STR_PAD_LEFT);

        return $prefix . '-' . $paddedId;
    }

    /**
     * Get the display prefix for formatted ID (override in models)
     */
    public function getDisplayPrefix(): string
    {
        // Default prefix based on model name
        $modelName = class_basename(static::class);
        return strtoupper(substr($modelName, 0, 3));
    }
}
