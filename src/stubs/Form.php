<?php

namespace App\Forms;

use Config;
use Kris\LaravelFormBuilder\Form;

class $FORM_NAME$ extends Form
{
    public function buildForm()
    {
        $this->add('name', 'text', [
            'label' => trans('$TRANSLATION_PREFIX$.name'),
            'rules' => ['required']
        ]);
    }
}
