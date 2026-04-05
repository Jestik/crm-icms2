<?php
class widgetCrmChart extends cmsWidget {

    public function run() {
        $period = $this->getOption('period', 'month');
        
        $model = cmsCore::getModel('content');
        $ctype_name = 'deals'; 

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

        $model->filterEqual('is_pub', 1);
        $model->filterGtEqual('date_pub', date('Y-m-d H:i:s', $start_time));
        $model->orderBy('date_pub', 'asc');
        $items = $model->getContentItems($ctype_name);

        $chart_data = [];
        $current_time = $start_time;
        $format = $group_by_month ? 'm.Y' : 'd.m.Y';
        $step = $group_by_month ? '+1 month' : '+1 day';

        while ($current_time <= $today) {
            $key = date($format, $current_time);
            $chart_data[$key] = ['income' => 0, 'expenses' => 0, 'profit' => 0];
            $current_time = strtotime($step, $current_time);
        }

        if ($items) {
            foreach ($items as $item) {
                $date_key = date($format, strtotime($item['date_pub']));
                if (!isset($chart_data[$date_key])) continue;

                $income = isset($item['income']) ? (float)$item['income'] : 0;
                $expenses_json = isset($item['expenses']) ? json_decode($item['expenses'], true) : [];
                $total_expenses = 0;

                if (is_array($expenses_json)) {
                    foreach ($expenses_json as $exp) {
                        $total_expenses += isset($exp['cost']) ? (float)$exp['cost'] : 0;
                    }
                }

                $profit = $income - $total_expenses;

                $chart_data[$date_key]['income'] += $income;
                $chart_data[$date_key]['expenses'] += $total_expenses;
                $chart_data[$date_key]['profit'] += $profit;
            }
        }

        return array(
            'labels'       => array_keys($chart_data),
            'incomes'      => array_values(array_column($chart_data, 'income')),
            'expenses'     => array_values(array_column($chart_data, 'expenses')),
            'profits'      => array_values(array_column($chart_data, 'profit')),
            'period_title' => $this->getPeriodTitle($period)
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
