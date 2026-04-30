<?php

namespace App\Http\Resources;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Food;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $choice = null;
        $additional = $this->getAdditional();
        if (isset($this->additional['variations'])) {
            $choice = $this->getChoice();
        }
        $resource = [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->getPrice($choice, $additional),
            'image' => url('storage/product/' . $this['image']),
            'restaurant_id' => $this->restaurant_id,
        ];
        if ($choice !== null) {
            $resource['choice'] = $choice;
        }
        if ($additional !== null) {
            $resource['additional'] = $additional;
        }
        return $resource;
    }

    private function getChoice(): array
    {
        $user_variations = $this->additional['variations'];
        $options = json_decode($this['choice_options'], true);
        $choice = array_map(function ($variation) use ($options) {
            $optionIndex = array_search($variation['choice_name'], array_map(fn($item) => $item['name'], $options));
            $option = $options[$optionIndex];
            if ($optionIndex === false) {
                return null;
            }
            $valueIndex = array_search($variation['value'], $option['options']);

            if ($valueIndex === false) {
                return null;
            }
            return [
                'name' => $option['name'],
                'title' => $option['title'],
                'value' => $option['options'][$valueIndex],
            ];
        }, $user_variations);
        return array_filter($choice, fn($item) => $item !== null);
    }

    private function getVariation($choice, $variations)
    {
        $choice_values = array_map(fn($option) => $option['value'], $choice);
        rsort($choice_values);
        $variation_name = join('-', $choice_values);
        $variation_index = array_search($variation_name, array_map(fn($item) => $item['type'], $variations));
        return $variations[$variation_index];
    }

    private function getPrice($choice, $additional)
    {
        $total = $this->price;
        if (empty($choice)) {
            return $total;
        }
        $variations = json_decode($this->variations, true);
        $variation = $this->getVariation($choice, $variations);
        if (!empty($variation)) {
            $total = $variation['price'];
        }

        $additional_prices = 0;
        if (isset($this->additional['additional'])) {
            $additional_prices = array_reduce($additional, fn($prev, $item) => $prev + $item['price'], 0);
        }
        return $total + $additional_prices;
    }

    private function getAdditional()
    {
        if (!isset($this->additional['additional'])) {
            return null;
        }
        $addOns = Helpers::addon_data_formatting(
            data: AddOn::withoutGlobalScope('translate')
                ->whereIn('id', $this->additional['additional'])
                ->active()
                ->get(),
            multi_data: true,
        );

        return array_map(fn(AddOn $addOn) => [
            'id' => $addOn->id,
            'name' => $addOn->name,
            'price' => $addOn->price,
        ], $addOns);
    }
}
