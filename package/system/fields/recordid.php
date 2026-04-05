<?php

class fieldRecordid extends cmsFormField {

    public $title       = 'ID записи';
    public $sql         = 'int(11) UNSIGNED NULL DEFAULT NULL';
    public $filter_type = 'int';
    public $var_type    = 'int';

    public function getRules() {
        return [['number']];
    }

    public function parseTeaser($value) {
        return $this->parse($value);
    }

    public function parse($value) {
        return !empty($this->item['id']) ? (int)$this->item['id'] : '';
    }

    public function applyFilter($model, $value) {
        if (is_numeric($value)) {
            return $model->filterEqual('id', (int)$value);
        }
        
        return $model;
    }

    public function getInput($value) {
        return ''; 
    }

    public function getFilterInput($value) {
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $val  = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        
        return '<input type="number" name="'.$name.'" value="'.$val.'" class="input form-control" placeholder="ID">';
    }

    public function store($value, $is_submitted, $old_value = null) {
        return null; 
    }

    public function storeFilter($value) {
        return $value ? (int)$value : null;
    }
}
