<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryAdminResource extends JsonResource
{
    protected $subCategory;
    protected $subCategoryId;

    public function __construct($resource, $subCategoryId = null)
    {
        parent::__construct($resource);
        $this->subCategory = $resource;
        $this->subCategoryId = $subCategoryId;
    }
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $subCategory = $this->subCategory?->childes->where('id', $this->subCategoryId)->first();

        $subCategories = $subCategory ? [
            'id' => $subCategory->id,
            'name' => $subCategory->name,
        ] : [];

        return [
            'name' => $this->subCategory->name,  // Correct access to the parent category's name
            'sub_categories' => $subCategories ? [$subCategories] : [],  // Return as an array of one (or empty)
        ];
    }
}
