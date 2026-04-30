<?php

namespace App\Actions\Product;

use App\Actions\BaseAction;
use App\CentralLogics\Helpers;
use App\Data\Product\ProductData;
use App\Models\AddOn;
use App\Models\Food;

class GetProductDataAction extends BaseAction
{
    /**
     * @param \App\Models\Food $food
     * @return array
     */
    public function handle(Food $food): array
    {
        $options    = json_decode($food->choice_options, true);
        $variations = json_decode($food->variations, true);
        $extra      = $this->getExtra($food);

        $productData = new ProductData(
            id: $food->id,
            restaurant_id: $food->restaurant_id,
            restaurant_name: $food->restaurant->name,
            restaurant_slug: $food->restaurant->slug,
            category_id: $food->category_id,
            category_name: $food->subCategory ? $food->subCategory->name : 'Unknown',
            discount: $food->discount,
            discount_type: $food->discount_type,
            name: $food->name,
            image: $food->image ?? '',
            price: $food->price,
            options: $options,
            variations: $variations,
            extra: $extra,
            description: $food->description,
        );

        return $productData->toArray();
    }

    /**
     * @param \App\Models\Food $food
     * @return array
     */
    public function getExtra(Food $food): array
    {
        $addOns = Helpers::addon_data_formatting(
            data: AddOn::withoutGlobalScope('translate')
                ->whereIn('id', json_decode($food->add_ons))
                ->active()
                ->get(),
            multi_data: true,
        );

        return array_map(fn(AddOn $addOn) => [
            'id'    => $addOn->id,
            'name'  => $addOn->name,
            'price' => $addOn->price,
        ], $addOns);
    }
}
