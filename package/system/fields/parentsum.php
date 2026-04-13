<?php
declare(strict_types=1);

class fieldParentsum extends cmsFormField {

    public $title       = 'Сумма родительских записей';
    public $sql         = 'varchar(1) NULL DEFAULT NULL'; 
    public $allow_index = false;
    public $var_type    = 'string';

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

    public function parse($value) {
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
            return '<em class="text-danger">Ошибка: имя поля некорректно</em>';
        }

        $child_ctype_raw = !empty($this->item['ctype_name']) ? (string)$this->item['ctype_name'] : (string)$this->ctype_name;
        $child_ctype_name = preg_replace('/[^a-z0-9_]/i', '', $child_ctype_raw);

        $content_model = cmsCore::getModel('content');
        $child_ctype   = $content_model->getContentTypeByName($child_ctype_name);
        $parent_ctype  = $content_model->getContentTypeByName($parent_ctype_name);

        if (!$parent_ctype || !$child_ctype) {
            return '<em class="text-danger">Ошибка: типы контента не найдены</em>';
        }

        $db = cmsDatabase::getInstance();
        $parent_table = (string)$content_model->getContentTypeTableName($parent_ctype['name']);

        if (!preg_match('/^[a-z0-9_]+$/i', $parent_table)) {
            return '<em class="text-danger">Ошибка: имя таблицы некорректно</em>';
        }

        $parent_ctype_id = (int)$parent_ctype['id'];
        $child_ctype_id  = (int)$child_ctype['id'];

        $sql = "SELECT SUM(i.{$parent_sum_field}) as total_sum
                FROM {#}{$parent_table} i
                JOIN {#}content_relations_bind r ON r.parent_item_id = i.id
                WHERE r.child_item_id = {$child_id} 
                  AND r.parent_ctype_id = {$parent_ctype_id}
                  AND r.child_ctype_id = {$child_ctype_id}";

        $result = $db->query($sql);
        $data   = $db->fetchAssoc($result);
        
        $parent_sum = !empty($data['total_sum']) ? (float)$data['total_sum'] : 0.0;

        ob_start();
        try {
            ?>
            <div class="field_parent_sum_card" style="background: #fdfdfd; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 350px; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #777;">Сумма:</span> 
                    <strong style="color: #333; font-size: 16px;"><?php echo number_format($parent_sum, 0, '.', ' '); ?> €</strong>
                </div>
            </div>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldParentsum Error: ' . $e->getMessage());
            return '<div class="text-danger">Ошибка вычисления суммы</div>';
        }
    }

    public function getInput($value) {
        return '';
    }
    
    public function store($value, $is_submitted, $old_value = null) {
        return null;
    }
}
