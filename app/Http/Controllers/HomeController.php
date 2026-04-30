<?php

namespace App\Http\Controllers;

use App\CentralLogics\BannerLogic;
use App\CentralLogics\CategoryLogic;
use App\CentralLogics\RestaurantLogic;
use App\Models\BusinessSetting;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        /*$this->middleware('auth');*/
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $banners             = BannerLogic::get_banners(); //TODO: cache banners
        $popularCategories   = CategoryLogic::popular(); //TODO: cache categories
        $topRatedRestaurants = $this->getTopRatedRestaurants(); //TODO: cache restaurants

        return view('home', compact('banners', 'popularCategories', 'topRatedRestaurants'));
    }

    public function terms_and_conditions()
    {
        $data = self::get_settings('terms_and_conditions');

        return view('terms-and-conditions', compact('data'));
    }

    public static function get_settings($name)
    {
        $config = null;
        $data   = BusinessSetting::where(['key' => $name])->first();
        if (isset($data)) {
            $config = json_decode($data['value'], true);
            if (is_null($config)) {
                $config = $data['value'];
            }
        }

        return $config;
    }

    public function about_us()
    {
        $data = self::get_settings('about_us');

        return view('about-us', compact('data'));
    }

    public function contact_us()
    {
        return view('contact-us');
    }

    public function privacy_policy()
    {
        $data = self::get_settings('privacy_policy');

        return view('privacy-policy', compact('data'));
    }

    private function getTopRatedRestaurants()
    {
        return RestaurantLogic::get_popular_restaurants(9);
    }
}
