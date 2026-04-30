<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Class CategoriesController
 * @package App\Http\Controllers\Web
 */
class CategoriesController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index(Request $request, ?Category $category = null)
    {
        return view('web.categories.index', [
            'category' => $category,
            'q'        => $request->get('s'),
        ]);
    }
}
