<?php

use App\CentralLogics\Helpers;
use App\Data\Product\ProductData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

if (!function_exists('translate')) {
    function translate($key, $replace = [])
    {
        $key   = strpos($key, 'messages.') === 0 ? substr($key, 9) : $key;
        $local = Helpers::default_lang();
        App::setLocale($local);
        try {
            $lang_array = include(base_path('resources/lang/' . $local . '/messages.php'));

            if (!array_key_exists($key, $lang_array)) {
                $processed_key    = str_replace('_', ' ', Helpers::remove_invalid_charcaters($key));
                $lang_array[$key] = $processed_key;
                $str              = "<?php return " . var_export($lang_array, true) . ";";
                file_put_contents(base_path('resources/lang/' . $local . '/messages.php'), $str);
                $result = $processed_key;
            } else {
                $result = trans('messages.' . $key, $replace);
            }
        } catch (\Exception $exception) {
            info($exception);
            $result = trans('messages.' . $key, $replace);
        }

        return $result;
    }
}

if (!function_exists('get_product_discounted_price')) {
    function get_product_discounted_price(float $price, string $discountType, float $discount): float
    {
        if ($discountType == 'percent') {
            return $price - ($price * $discount / 100.0);
        } else {
            if ($discountType == 'amount') {
                return $price - $discount;
            } else {
                return $price;
            }
        }
    }
}

if (!function_exists('get_product_calculated_price')) {
    function get_product_calculated_price(ProductData $productData, array $data): float
    {
        $discountedPrice = $productData->discounted_price;
        $discountAmount  = $productData->price - $discountedPrice;
        $totalPrice      = 0;

        $quantity = Arr::get($data, 'quantity', 1);
        $options  = Arr::get($data, 'options', []);
        $extra    = Arr::get($data, 'extra', []);

        if (sizeof($options)) {
            $selectedVariation = join('-', $options);
            $variation         = collect($productData->variations)->where('type', $selectedVariation)->first();
            $variationPrice    = Arr::get($variation, 'price', 0);
            $variationPrice    = ($variationPrice - $discountAmount) * $quantity;

            $totalPrice += $variationPrice;
        } else {
            $totalPrice += $discountedPrice * $quantity;
        }

        if (sizeof($extra)) {
            $addOns = collect($productData->extra);
            foreach ($extra as $item) {
                $id    = Arr::get($item, 'id');
                $addOn = $addOns->where('id', $id)->first();

                $quantity = Arr::get($item, 'quantity', 1);
                $price    = Arr::get($addOn, 'price', 0);

                $totalPrice += $quantity * $price;
            }
        }

        return $totalPrice;
    }
}
