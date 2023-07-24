<?php

namespace Statamic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Traits\Localizable;
use Statamic\Facades\Site;

class FormRequest extends LaravelFormRequest
{
    use Localizable;

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

    public function messages()
    {
        $site = Site::findByUrl(URL::previous()) ?? Site::default();

        return $this->withLocale($site->lang(), function () {
            return Lang::get('validation');
        });
    }
}
