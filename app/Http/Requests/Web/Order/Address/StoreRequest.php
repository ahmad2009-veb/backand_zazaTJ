<?php

namespace App\Http\Requests\Web\Order\Address;

use App\Models\Zone;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'latitude'              => ['required', 'numeric'],
            'longitude'             => ['required', 'numeric'],
            'contact_person_number' => ['required', 'string', 'max: 255'],
            'contact_person_name'   => ['required', 'string', 'max:255'],
            'road'                  => ['nullable', 'string'],
            'house'                 => ['nullable', 'string'],
            'floor'                 => ['nullable', 'string'],
        ];
    }

    /**
     * @param $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->latitude && $this->longitude) {
                $point = new Point($this->latitude, $this->longitude);
                $zones = Zone::contains('coordinates', $point)->get(['id']);

                if (count($zones) == 0) {
                    $validator->errors()->add('road', 'Сервис не доступен в этой области');
                } else {
                    $this->merge(['zone_id' => $zones[0]->id]);
                }
            }
        });
    }
}
