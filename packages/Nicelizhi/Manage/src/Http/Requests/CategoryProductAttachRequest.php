<?php

namespace Nicelizhi\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryProductAttachRequest extends FormRequest
{
    /**
     * Determine if the Configuration is authorized to make this request.
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
            'category_id' => 'required|integer|exists:categories,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ];
    }
}
