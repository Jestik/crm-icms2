<?php

class fieldProfit extends cmsFormField {

    public $title       = 'Окупаемость / Прибыль';
    public $sql         = 'varchar(1) NULL DEFAULT NULL'; 
    public $allow_index = false;
    public $var_type    = 'string';

    public function getOptions() {
        return [
            new fieldString('child_ctype_name', [
                'title' => 'Системное имя дочернего типа контента',
                'hint'  => 'Например: parts'
            ]),
            new fieldString('parent_price_field', [
                'title' => 'Системное имя поля цены у Родителя (Покупка)',
                'hint'  => 'Например: price'
            ]),
            new fieldString('child_price_field', [
                'title' => 'Системное имя поля цены у Дочернего типа (Продажа)',
                'hint'  => 'Например: price'
            ])
        ];
    }

    public function parse($value) {

        $child_ctype_name   = $this->getOption('child_ctype_name');
        $parent_price_field = $this->getOption('parent_price_field');
        $child_price_field  = $this->getOption('child_price_field');

        if (!$child_ctype_name || !$parent_price_field || !$child_price_field) {
            return '<em>Поле окупаемости не настроено</em>';
        }

        $parent_price = isset($this->item[$parent_price_field]) ? (float)$this->item[$parent_price_field] : 0;
        $parent_id    = $this->item['id'];

        $parent_ctype_name = !empty($this->item['ctype_name']) ? $this->item['ctype_name'] : $this->ctype_name;

        $content_model = cmsCore::getModel('content');
        $parent_ctype  = $content_model->getContentTypeByName($parent_ctype_name);
        $child_ctype   = $content_model->getContentTypeByName($child_ctype_name);

        if (!$parent_ctype || !$child_ctype) {
            return '<em>Ошибка типов контента</em>';
        }

        $db = cmsDatabase::getInstance();
        $child_table = $content_model->getContentTypeTableName($child_ctype_name);

        $sql = "SELECT SUM(i.{$child_price_field}) as total_sum
                FROM {#}{$child_table} i
                JOIN {#}content_relations_bind r ON r.child_item_id = i.id
                WHERE r.parent_item_id = '{$parent_id}' 
                  AND r.parent_ctype_id = '{$parent_ctype['id']}'
                  AND r.child_ctype_id = '{$child_ctype['id']}'";

        $result = $db->query($sql);
        $data   = $db->fetchAssoc($result);
        
        $child_sum = $data['total_sum'] ? (float)$data['total_sum'] : 0;

        $profit = $child_sum - $parent_price;

        $html = '<div style="background: #fdfdfd; padding: 12px; border: 1px solid #e0e0e0; border-radius: 6px; max-width: 320px; font-size: 14px; line-height: 1.5;">';
        
        $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 6px;">';
        $html .= '<span style="color: #666;">Цена покупки:</span> ';
        $html .= '<strong>' . number_format($parent_price, 0, '.', ' ') . '</strong>';
        $html .= '</div>';

        $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">';
        $html .= '<span style="color: #666;">Продано на сумму:</span> ';
        $html .= '<strong>' . number_format($child_sum, 0, '.', ' ') . '</strong>';
        $html .= '</div>';

        $html .= '<div style="border-top: 1px dashed #ccc; margin: 8px 0;"></div>';
        
        if ($profit < 0) {
            $left = abs($profit);
            $html .= '<div style="display: flex; justify-content: space-between; color: #d9534f; font-size: 15px;">';
            $html .= '<span>Убыток (осталось):</span> ';
            $html .= '<strong>' . number_format($left, 0, '.', ' ') . '</strong>';
            $html .= '</div>';
        } elseif ($profit > 0) {
            $html .= '<div style="display: flex; justify-content: space-between; color: #5cb85c; font-size: 15px;">';
            $html .= '<span>Прибыль:</span> ';
            $html .= '<strong>' . number_format($profit, 0, '.', ' ') . '</strong>';
            $html .= '</div>';
        } else {
            $html .= '<div style="display: flex; justify-content: space-between; color: #777; font-size: 15px;">';
            $html .= '<span>Итог:</span> ';
            $html .= '<strong>Окупилось в ноль</strong>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function getInput($value) {
        return '';
    }
    
    public function store($value, $is_submitted, $old_value = null) {
        return null;
    }
}
