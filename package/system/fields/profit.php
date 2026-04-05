<?php
declare(strict_types=1);

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
        $parent_id = isset($this->item['id']) ? (int)$this->item['id'] : 0;
        if (!$parent_id) { 
            return ''; 
        }

        $child_ctype_name   = (string)$this->getOption('child_ctype_name');
        $parent_price_field = (string)$this->getOption('parent_price_field');
        $child_price_field  = (string)$this->getOption('child_price_field');

        if (!$child_ctype_name || !$parent_price_field || !$child_price_field) {
            return '<em class="text-muted">Поле окупаемости не настроено</em>';
        }

        if (!preg_match('/^[a-z0-9_]+$/i', $child_price_field)) {
            return '<em class="text-danger">Ошибка: имя поля цены некорректно</em>';
        }

        $parent_price = isset($this->item[$parent_price_field]) ? (float)$this->item[$parent_price_field] : 0.0;

        $parent_ctype_raw = !empty($this->item['ctype_name']) ? (string)$this->item['ctype_name'] : (string)$this->ctype_name;
        $parent_ctype_name = preg_replace('/[^a-z0-9_]/i', '', $parent_ctype_raw);

        $content_model = cmsCore::getModel('content');
        $parent_ctype  = $content_model->getContentTypeByName($parent_ctype_name);
        $child_ctype   = $content_model->getContentTypeByName($child_ctype_name);

        if (!$parent_ctype || !$child_ctype) {
            return '<em class="text-danger">Ошибка: типы контента не найдены</em>';
        }

        $db = cmsDatabase::getInstance();
        $child_table = (string)$content_model->getContentTypeTableName($child_ctype['name']);

        if (!preg_match('/^[a-z0-9_]+$/i', $child_table)) {
            return '<em class="text-danger">Ошибка: имя таблицы некорректно</em>';
        }

        $parent_ctype_id = (int)$parent_ctype['id'];
        $child_ctype_id  = (int)$child_ctype['id'];

        $sql = "SELECT SUM(i.{$child_price_field}) as total_sum
                FROM {#}{$child_table} i
                JOIN {#}content_relations_bind r ON r.child_item_id = i.id
                WHERE r.parent_item_id = {$parent_id} 
                  AND r.parent_ctype_id = {$parent_ctype_id}
                  AND r.child_ctype_id = {$child_ctype_id}";

        $result = $db->query($sql);
        $data   = $db->fetchAssoc($result);
        
        $child_sum = !empty($data['total_sum']) ? (float)$data['total_sum'] : 0.0;
        $profit    = $child_sum - $parent_price;

        ob_start();
        try {
            ?>
            <div class="field_profit_card" style="background: #fdfdfd; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 350px; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #777;">Цена покупки:</span> 
                    <strong style="color: #333;"><?php echo number_format($parent_price, 0, '.', ' '); ?> €</strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: #777;">Продано:</span> 
                    <strong style="color: #333;"><?php echo number_format($child_sum, 0, '.', ' '); ?> €</strong>
                </div>
                
                <div style="border-top: 2px dashed #eee; margin: 10px 0;"></div>
                
                <?php if ($profit < 0): ?>
                    <div style="display: flex; justify-content: space-between; color: #d9534f; font-size: 16px;">
                        <span>До окупаемости:</span> 
                        <strong><?php echo number_format(abs($profit), 0, '.', ' '); ?> €</strong>
                    </div>
                <?php elseif ($profit > 0): ?>
                    <div style="display: flex; justify-content: space-between; color: #449d44; font-size: 16px;">
                        <span>Чистая прибыль:</span> 
                        <strong><?php echo number_format($profit, 0, '.', ' '); ?> €</strong>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; color: #666; font-size: 16px;">
                        <span>Итог:</span> 
                        <strong>Вышли в ноль</strong>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldProfit Error: ' . $e->getMessage());
            return '<div class="text-danger">Ошибка вычисления прибыли</div>';
        }
    }

    public function getInput($value) {
        return '';
    }
    
    public function store($value, $is_submitted, $old_value = null) {
        return null;
    }
}
