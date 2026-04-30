<?php

namespace App\Data\Product;

use Illuminate\Support\Arr;

/**
 * Class ProductData
 * @package App\Data\Product
 */
class ProductData
{
    public float $discounted_price;

    /**
     * @param int $id
     * @param int $restaurant_id
     * @param string $restaurant_name
     * @param string $restaurant_slug
     * @param int $category_id
     * @param string $category_name
     * @param float $discount
     * @param string $discount_type
     * @param string $name
     * @param string $image
     * @param float $price
     * @param array $options
     * @param array $variations
     * @param array $extra
     * @param ?string $description = null
     */
    public function __construct(
        public int $id,
        public int $restaurant_id,
        public string $restaurant_name,
        public string $restaurant_slug,
        public int $category_id,
        public string $category_name,
        public float $discount,
        public string $discount_type,
        public string $name,
        public string $image,
        public float $price,
        public array $options,
        public array $variations,
        public array $extra,
        public ?string $description = null,
    ) {
        $this->discounted_price = get_product_discounted_price($this->price, $this->discount_type, $this->discount);
    }

    /**
     * @param array $data
     * @return \App\Data\Product\ProductData
     */
    public static function fromArray(array $data): ProductData
    {
        return new static(
            id: Arr::get($data, 'id'),
            restaurant_id: Arr::get($data, 'restaurant_id'),
            restaurant_name: Arr::get($data, 'restaurant_name'),
            restaurant_slug: Arr::get($data, 'restaurant_slug'),
            category_id: Arr::get($data, 'category_id'),
            category_name: Arr::get($data, 'category_name'),
            discount: Arr::get($data, 'discount'),
            discount_type: Arr::get($data, 'discount_type'),
            name: Arr::get($data, 'name'),
            image: Arr::get($data, 'image'),
            price: Arr::get($data, 'price'),
            options: Arr::get($data, 'options', []),
            variations: Arr::get($data, 'variations', []),
            extra: Arr::get($data, 'extra', []),
            description: Arr::get($data, 'description'),
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'restaurant_id'    => $this->restaurant_id,
            'restaurant_name'  => $this->restaurant_name,
            'restaurant_slug'  => $this->restaurant_slug,
            'category_id'      => $this->category_id,
            'category_name'    => $this->category_name,
            'discount'         => $this->discount,
            'discount_type'    => $this->discount_type,
            'name'             => $this->name,
            'image'            => $this->image,
            'price'            => $this->price,
            'discounted_price' => $this->discounted_price,
            'options'          => $this->options,
            'variations'       => $this->variations,
            'extra'            => $this->extra,
            'description'      => $this->description,
        ];
    }
}
