<?php
declare(strict_types=1);

class fieldParentsum extends cmsFormField {

    public $title       = 'Сумма родительских записей';
    public $sql         = 'varchar(1) NULL DEFAULT NULL'; 
    public $allow_index = false;
    public $var_type    = 'string';

    private static array $fields_cache = [];

    public function getOptions() {
        return [
            new fieldString('parent_ctype_name', [
                'title' => 'Системное имя родительского типа контента',
                'hint'  => 'Например: bestellungen'
            ]),
            new fieldString('parent_sum_field', [
                'title' => 'Системное имя поля у Родителя (которое суммируем)',
                'hint'  => 'Например: price'
            ])
        ];
    }

    public function parse(mixed $value): string {
        $child_id = isset($this->item['id']) ? (int)$this->item['id'] : 0;
        if (!$child_id) { 
            return ''; 
        }

        $parent_ctype_name = (string)$this->getOption('parent_ctype_name');
        $parent_sum_field  = (string)$this->getOption('parent_sum_field');

        if (!$parent_ctype_name || !$parent_sum_field) {
            return '<em class="text-muted">Поле суммы не настроено</em>';
        }

        if (!preg_match('/^[a-z0-9_]+$/i', $parent_sum_field)) {
            error_log("fieldParentsum: Обнаружены недопустимые символы в имени поля - {$parent_sum_field}");
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }

        $child_ctype_raw = !empty($this->item['ctype_name']) ? (string)$this->item['ctype_name'] : (string)$this->ctype_name;
        $child_ctype_name = preg_replace('/[^a-z0-9_]/i', '', $child_ctype_raw);

        $content_model = cmsCore::getModel('content');
        
        $child_ctype   = $content_model->getContentTypeByName($child_ctype_name);
        $parent_ctype  = $content_model->getContentTypeByName($parent_ctype_name);

        if (!$parent_ctype || !$child_ctype) {
            error_log("fieldParentsum: Типы контента не найдены");
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }

        if (!isset(self::$fields_cache[$parent_ctype_name])) {
            self::$fields_cache[$parent_ctype_name] = $content_model->getContentFields($parent_ctype_name);
        }
        $parent_fields = self::$fields_cache[$parent_ctype_name];
        
        $field = $parent_fields[$parent_sum_field] ?? null;

        if (!$field || empty($field['name']) || empty($field['type'])) {
            error_log("fieldParentsum: Поле {$parent_sum_field} не найдено или имеет неверную структуру.");
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }

        $safe_field_name = $field['name'];

        $allowed_types = ['number', 'price'];
        if (!in_array($field['type'], $allowed_types, true)) {
            error_log("fieldParentsum: Поле {$safe_field_name} имеет тип {$field['type']}, который нельзя суммировать.");
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }

        $parent_table = (string)$content_model->getContentTypeTableName($parent_ctype['name']);
        
        if (!preg_match('/^[a-z0-9_]+$/i', $parent_table)) {
            error_log("fieldParentsum: Некорректное имя таблицы - {$parent_table}");
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }

        $parent_ctype_id = (int)$parent_ctype['id'];
        $child_ctype_id  = (int)$child_ctype['id'];

        $calc_model = clone $content_model;
        
        $calc_model->selectOnly("SUM(`i`.`{$safe_field_name}`)", 'total_sum');
        
        $filter = sprintf(
            "r.parent_item_id = i.id AND r.child_item_id = %d AND r.parent_ctype_id = %d AND r.child_ctype_id = %d",
            $child_id,
            $parent_ctype_id,
            $child_ctype_id
        );
                  
        $calc_model->join('content_relations_bind', 'r', $filter);
        $data = $calc_model->getItem($parent_table);

        $parent_sum = (is_array($data) && isset($data['total_sum'])) 
            ? (float)$data['total_sum'] 
            : 0.0;

        $formatted_sum = number_format($parent_sum, decimals: 0, thousands_separator: ' ');
        $safe_sum = htmlspecialchars((string)$formatted_sum, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = <<<HTML
        <div class="field_parent_sum_card">
            <span class="sum-label">Сумма:</span> 
            <strong class="sum-value">%s €</strong>
        </div>
        HTML;

        return sprintf($html, $safe_sum);
    }

    public function getInput($value) {
        return '';
    }
    
    public function store($value, $is_submitted, $old_value = null) {
        return null;
    }
}
