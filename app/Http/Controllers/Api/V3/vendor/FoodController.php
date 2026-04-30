<?php

namespace App\Http\Controllers\Api\V3\vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v3\admin\FoodUpdateRequest;
use App\Http\Requests\Api\v3\FoodCreateRequest;
use App\Http\Requests\Api\v3\Vendor\VendorFoodCreateRequest;
use App\Http\Requests\Api\v3\Vendor\VendorFoodUpdateRequest;
use App\Http\Resources\Admin\ProductShowResource;
use App\Http\Resources\ProductItemResource;
use App\Http\Resources\Vendor\VendorProductResource;
use App\Models\Food;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FoodController extends Controller
{
    public function index(Request $request)
    {
        $vendor = auth()->user();
        $search = $request->input('search', '');
        $restaurant = $vendor->restaurants()->first();

        $foods = Food::query()->where('restaurant_id', $restaurant->id)
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%");
            })->paginate($request->input('per_page', 12));

        return VendorProductResource::collection($foods);
    }

    public function store(VendorFoodCreateRequest $request)
    {
        if ($request['discount_type'] == 'percentage') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            return response()->json(['message' => trans('messages.discount_can_not_be_more_than_or_equal')]);
        }

        $food = new Food;
        $food->name = $request->name;

        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }
        $food->category_ids = json_encode($category);
        $food->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $food->description = $request->description;
        $food->restaurant_id = $request->restaurant_id;
        $food->veg = $request->veg;
        $food->discount = $request->discount;

        $choice_options = [];

        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    return response()->json(['message' => trans('messages.attribute_choice_option_value_can_not_be_null')]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', '', $request[$str])));
                $choice_options[] = $item;
            }
        }

//        return $request->choice;
        $food->choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
        $options = [];
        if ($request->has('choice_no')) {
            $choices = $request->get('choice_no');
            sort($choices);
            foreach ($choices as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);

                array_push($options, explode(',', $my_str));
            }
        }
        $variations = [];
//Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);

        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $variations[] = $item;
            }
        }
        $food->variations = json_encode($variations);
        $food->price = $request->price;
        $food->image = Helpers::upload('product/', 'png', $request->file('image'));
        $food->available_time_starts = $request->available_time_starts;
        $food->available_time_ends = $request->available_time_ends;
        $food->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $food->discount_type = $request->discount_type;
        $food->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $food->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);

        $food->save();
        return response()->json(['message' => 'created successfully'], 201);


    }

    public function show(Food $food)
    {
        // Load variations relationship if available
        if (method_exists($food, 'variations')) {
            $food->load('variations');
        }
        return ProductShowResource::make($food);
    }

    public function update(VendorFoodUpdateRequest $request, Food $food) {

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            return response()->json(['message' => trans('messages.discount_can_not_be_more_than_or_equal')]);
        }
        $food->name = $request->name;

        // Update category IDs
        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }

        $food->category_ids = json_encode($category);
        $food->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $food->description = $request->description;
        $food->restaurant_id = $request->restaurant_id;
        $food->veg = $request->veg;
        $food->discount = $request->discount;

        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    return response()->json(['message' => trans('messages.attribute_choice_option_value_can_not_be_null')]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', '', $request[$str])));
                $choice_options[] = $item;
            }
        }

        $food->choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }

        $variations = [];
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $variations[] = $item;
            }
        }
        $food->variations = json_encode($variations);

        $food->price = $request->price;
        if ($request->hasFile('image')) {
            $food->image = Helpers::upload('product/', 'png', $request->file('image'));
        }
        $food->available_time_starts = $request->available_time_starts;
        $food->available_time_ends = $request->available_time_ends;
        $food->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $food->discount_type = $request->discount_type;
        $food->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $food->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);

        $food->save();

        return response()->json(['message' => 'Updated successfully'], 200);
    }
    public function delete(Food $food)
    {
        if ($food->image) {
            if (Storage::disk('public')->exists('product/' . $food['image'])) {
                Storage::disk('public')->delete('product/' . $food['image']);
            }
        }
        $food->translations()->delete();
        $food->delete();

        return response()->json(['message' => trans('messages.product_deleted_successfully')]);

    }

    public function getFoodsByIds(Request $request)
    {
        $food_ids = $request->food_ids ? explode(',', $request->food_ids) : [];
        $products = Product::query()
            ->with('variations')
            ->when(!empty($food_ids), function ($query) use ($food_ids) {
                return $query->whereIn('id', $food_ids);
            })
            ->get();

        return ProductItemResource::collection($products);
    }
}
