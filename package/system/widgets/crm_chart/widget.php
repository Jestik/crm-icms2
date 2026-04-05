<?php
class widgetCrmChart extends cmsWidget {

    public function run() {
        $period = $this->getOption('period', 'month');
        $ctype_name = $this->getOption('ctype_name', 'deals');
        $show_metrics = $this->getOption('show_metrics', array('income', 'expenses', 'profit', 'count'));
        $chart_style = $this->getOption('chart_style', 'bar');
        
        $field_income   = $this->getOption('field_income', 'income');
        $field_expenses = $this->getOption('field_expenses', 'expenses');
        $field_profit   = $this->getOption('field_profit', '');
        
        $field_date_pub  = $this->getOption('field_date_pub', 'date_pub');
        $field_date_done = $this->getOption('field_date_done', 'verkaufdate');

        $model = cmsCore::getModel('content');

        if (!$model->getContentTypeByName($ctype_name)) {
            return false;
        }

        $ctype_fields = $model->getContentFields($ctype_name);

        $sys_dates = ['date_pub', 'date_created']; 

        $actual_field_pub = (in_array($field_date_pub, $sys_dates) || isset($ctype_fields[$field_date_pub])) ? $field_date_pub : 'date_pub';
        $is_done_valid = in_array($field_date_done, $sys_dates) || isset($ctype_fields[$field_date_done]);

        $today = time();
        $group_by_month = false; 

        switch ($period) {
            case 'month':
                $start_time = strtotime('first day of this month 00:00:00');
                break;
            case 'quarter':
                $current_month = date('m');
                $quarter_start = floor(($current_month - 1) / 3) * 3 + 1;
                $start_time = strtotime(date('Y') . '-' . sprintf('%02d', $quarter_start) . '-01 00:00:00');
                break;
            case 'halfyear':
                $start_time = strtotime('-6 months');
                $group_by_month = true; 
                break;
            case 'year':
                $start_time = strtotime(date('Y') . '-01-01 00:00:00');
                $group_by_month = true;
                break;
            default:
                $start_time = strtotime('-30 days');
        }

        $start_date_sql = date('Y-m-d H:i:s', $start_time);
        $items = []; 

        $model->limit(false); 
        $model->filterEqual('is_pub', 1);
        $model->filterGtEqual($actual_field_pub, $start_date_sql);
        $items_opened = $model->getContentItems($ctype_name);
        if ($items_opened) {
            foreach ($items_opened as $item) {
                $items[$item['id']] = $item;
            }
        }

        if ($field_date_done && $field_date_done !== $actual_field_pub && $is_done_valid) {
            $model->resetFilters(); 
            $model->limit(false); 
            $model->filterEqual('is_pub', 1);
            $model->filterGtEqual($field_date_done, $start_date_sql);
            $items_closed = $model->getContentItems($ctype_name);
            if ($items_closed) {
                foreach ($items_closed as $item) {
                    $items[$item['id']] = $item; 
                }
            }
        }

        $chart_data = [];
        $current_time = $start_time;
        $format = $group_by_month ? 'm.Y' : 'd.m.Y';
        $step = $group_by_month ? '+1 month' : '+1 day';

        while ($current_time <= $today) {
            $key = date($format, $current_time);
            $chart_data[$key] = ['income' => 0, 'expenses' => 0, 'profit' => 0, 'count' => 0];
            $current_time = strtotime($step, $current_time);
        }

        if ($items) {
            foreach ($items as $item) {
                
                $raw_date_pub = isset($item[$actual_field_pub]) ? $item[$actual_field_pub] : null;
                $date_creation = $raw_date_pub ? date($format, strtotime($raw_date_pub)) : null;

                $raw_date_done = null;
                if ($is_done_valid && array_key_exists($field_date_done, $item)) {
                    $val = $item[$field_date_done];
                    if (!empty($val) && strpos($val, '0000') === false) {
                        $raw_date_done = $val;
                    }
                } else {
                    // Если поля нет, считаем дату закрытия равной дате старта
                    $raw_date_done = $raw_date_pub;
                }
                
                $date_income = $raw_date_done ? date($format, strtotime($raw_date_done)) : null;

                $raw_income = isset($item[$field_income]) ? (string)$item[$field_income] : '0';
                $income = (float)str_replace([' ', ','], ['', '.'], $raw_income);
                
                $expenses_json = isset($item[$field_expenses]) ? json_decode($item[$field_expenses], true) : [];
                $total_expenses = 0;
                if (is_array($expenses_json)) {
                    foreach ($expenses_json as $exp) {
                        $raw_cost = isset($exp['cost']) ? (string)$exp['cost'] : '0';
                        $total_expenses += (float)str_replace([' ', ','], ['', '.'], $raw_cost);
                    }
                }

                if ($field_profit && isset($item[$field_profit])) {
                    $raw_profit = (string)$item[$field_profit];
                    $profit = (float)str_replace([' ', ','], ['', '.'], $raw_profit);
                } else {
                    $profit = $income - $total_expenses;
                }

                // РАСПРЕДЕЛЯЕМ ПО ГРАФИКУ
                if ($date_creation && isset($chart_data[$date_creation])) {
                    $chart_data[$date_creation]['expenses'] += $total_expenses;
                    $chart_data[$date_creation]['count'] += 1;
                }

                if ($date_income && isset($chart_data[$date_income])) {
                    $chart_data[$date_income]['income'] += $income;
                    $chart_data[$date_income]['profit'] += $profit;
                }
            }
        }

        return array(
            'labels'       => array_keys($chart_data),
            'incomes'      => array_values(array_column($chart_data, 'income')),
            'expenses'     => array_values(array_column($chart_data, 'expenses')),
            'profits'      => array_values(array_column($chart_data, 'profit')),
            'counts'       => array_values(array_column($chart_data, 'count')),
            'period_title' => $this->getPeriodTitle($period),
            'show_metrics' => is_array($show_metrics) ? $show_metrics : array(),
            'chart_style'  => $chart_style
        );
    }

    private function getPeriodTitle($p) {
        $titles = [
            'month'    => 'Текущий месяц', 
            'quarter'  => 'Текущий квартал', 
            'halfyear' => 'Последние полгода', 
            'year'     => 'Текущий год'
        ];
        return isset($titles[$p]) ? $titles[$p] : 'Период';
    }
}
