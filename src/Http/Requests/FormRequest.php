<?php

namespace Statamic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;

class FormRequest extends LaravelFormRequest
{
    public function rules()
    {
        if (! $this->isPrecognitive()) {
            return [];
        }

        $fields = $this->route('form')->blueprint()->fields();
        $assetsFields = $fields->all()
            ->filter(function ($field) {
                return $field->fieldtype()->handle() === 'assets';
            })
            ->keys();

        return $fields->except($assetsFields)->validator()->rules();
    }
}
