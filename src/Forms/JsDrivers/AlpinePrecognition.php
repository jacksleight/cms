<?php

namespace Statamic\Forms\JsDrivers;

use Statamic\Statamic;

class AlpinePrecognition extends Alpine
{
    protected $ajaxScope;

    /**
     * Parse driver options.
     *
     * @param  array  $options
     */
    protected function parseOptions($options)
    {
        $this->scope = $options[0] ?? 'form';
        $this->ajaxScope = $options[1] ?? null;
    }

    /**
     * Add to form html tag attributes.
     *
     * @return array
     */
    public function addToFormAttributes()
    {
        return [
            'x-data' => $this->renderAlpineXData($this->getInitialFormData(), $this->scope, $this->ajaxScope),
            'x-on:submit.prevent' => $this->getAlpineXOnSubmit($this->scope, $this->ajaxScope),
        ];
    }

    /**
     * Add to renderable field html tag attributes.
     *
     * @param  \Statamic\Fields\Field  $field
     * @return array
     */
    public function addToRenderableFieldAttributes($field)
    {
        return [
            'x-model' => $this->getAlpineXDataKey($field->handle(), $this->scope),
            'x-on:change' => $this->renderAlpineXOnChange($field->handle(), $this->scope),
        ];
    }

    /**
     * Render alpine x-data string for fields, with scope if necessary.
     *
     * @param  array  $xData
     * @param  bool|string  $alpineScope
     * @return string
     */
    protected function renderAlpineXData($xData, $alpineScope, $alpineAjaxScope = null)
    {
        $actionUrl = $this->form->actionUrl();
        $dataObject = Statamic::modify($xData)->toJson()->entities();
        $errorsObject = Statamic::modify($this->getInitialFormErrors() ?? [])->toJson()->entities();
        $formObject = "\$form('post', '".$actionUrl."', ".$dataObject.').setErrors('.$errorsObject.')';

        if (is_string($alpineAjaxScope)) {
            return '{'.$alpineScope.': '.$formObject.', '.$alpineAjaxScope.': {}}';
        }

        return '{'.$alpineScope.': '.$formObject.'}';
    }

    /**
     * Render alpine x-on:submit string.
     *
     * @param  bool|string  $alpineScope
     * @param  bool|string  $alpineAjaxScope
     * @return string
     */
    protected function getAlpineXOnSubmit($alpineScope, $alpineAjaxScope)
    {
        if (! is_string($alpineAjaxScope)) {
            return;
        }

        return Statamic::modify($alpineScope."
            .submit({ 
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'multipart/form-data',
                },
            })
            .then(response => {
                ".$alpineScope.'.reset();
                '.$alpineAjaxScope.' = response.data;
            })
            .catch(error => {
                '.$alpineScope.'.setErrors(error.response.data.error);
                '.$alpineAjaxScope.' = error.response.data;
            })')->collapseWhitespace();
    }

    /**
     * Render alpine x-on:change string.
     *
     * @param  string  $fieldHandle
     * @param  bool|string  $alpineScope
     * @return string
     */
    protected function renderAlpineXOnChange($fieldHandle, $alpineScope)
    {
        return $alpineScope.".validate('".$fieldHandle."')";
    }
}
