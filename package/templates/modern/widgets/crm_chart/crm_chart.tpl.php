<?php
    $this->addJS('https://cdn.jsdelivr.net/npm/chart.js');
    $widget_id = 'chart_' . md5(uniqid());

    $active_metrics = (isset($show_metrics) && is_array($show_metrics)) ? $show_metrics : ['income', 'expenses', 'profit', 'count'];
    $style = isset($chart_style) ? $chart_style : 'bar';

    $datasets = [];

    if (in_array('profit', $active_metrics)) {
        $datasets[] = [
            'label'            => 'Чистая прибыль',
            'type'             => 'line',
            'data'             => $profits,
            'borderColor'      => '#28a745',
            'backgroundColor'  => '#28a745',
            'borderWidth'      => 2,
            'fill'             => false,
            'tension'          => 0.3,
            'pointRadius'      => 4,
            'pointHoverRadius' => 6,
            'yAxisID'          => 'y' // Основная левая ось
        ];
    }

    if (in_array('income', $active_metrics)) {
        $datasets[] = [
            'label'           => 'Приход',
            'type'            => $style,
            'data'            => $incomes,
            'backgroundColor' => ($style == 'line') ? 'rgba(54, 162, 235, 0.1)' : 'rgba(54, 162, 235, 0.7)',
            'borderColor'     => 'rgba(54, 162, 235, 1)',
            'borderWidth'     => ($style == 'line') ? 2 : 1,
            'borderRadius'    => ($style == 'bar') ? 4 : 0,
            'fill'            => ($style == 'line'),
            'tension'         => 0.3,
            'yAxisID'         => 'y'
        ];
    }

    if (in_array('expenses', $active_metrics)) {
        $datasets[] = [
            'label'           => 'Расходы',
            'type'            => $style,
            'data'            => $expenses,
            'backgroundColor' => ($style == 'line') ? 'rgba(255, 99, 132, 0.1)' : 'rgba(255, 99, 132, 0.7)',
            'borderColor'     => 'rgba(255, 99, 132, 1)',
            'borderWidth'     => ($style == 'line') ? 2 : 1,
            'borderRadius'    => ($style == 'bar') ? 4 : 0,
            'fill'            => ($style == 'line'),
            'tension'         => 0.3,
            'yAxisID'         => 'y'
        ];
    }

    if (in_array('count', $active_metrics)) {
        $datasets[] = [
            'label'           => 'Количество сделок',
            'type'            => $style,
            'data'            => $counts,
            'backgroundColor' => ($style == 'line') ? 'rgba(153, 102, 255, 0.1)' : 'rgba(153, 102, 255, 0.7)',
            'borderColor'     => 'rgba(153, 102, 255, 1)',
            'borderWidth'     => ($style == 'line') ? 2 : 1,
            'borderRadius'    => ($style == 'bar') ? 4 : 0,
            'fill'            => ($style == 'line'),
            'tension'         => 0.3,
            'yAxisID'         => 'y_count' 
        ];
    }
?>

<div class="crm-widget-chart" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
    <h4 style="margin-top: 0; color: #333; margin-bottom: 20px;">
        Финансовая динамика <span style="color: #6c757d; font-size: 0.85em;">(<?php echo htmlspecialchars($period_title); ?>)</span>
    </h4>
    
    <div style="position: relative; height: 350px; width: 100%;">
        <canvas id="<?php echo $widget_id; ?>"></canvas>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById('<?php echo $widget_id; ?>').getContext('2d');
    
    var chart = new Chart(ctx, {
        type: '<?php echo $style; ?>', 
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: <?php echo json_encode($datasets); ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false, 
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) { 
                                // Добавляем пробелы тысячам для красоты
                                label += context.parsed.y.toLocaleString(); 
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    type: 'linear',
                    display: <?php echo (in_array('income', $active_metrics) || in_array('expenses', $active_metrics) || in_array('profit', $active_metrics)) ? 'true' : 'false'; ?>,
                    position: 'left',
                    beginAtZero: true,
                    grid: { color: '#f0f0f0' },
                    title: {
                        display: true,
                        text: 'Сумма'
                    }
                }
                <?php if (in_array('count', $active_metrics)): ?>
                , y_count: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { display: false },
                    title: {
                        display: true,
                        text: 'Кол-во шт.'
                    },
                    ticks: {
                        stepSize: 1 
                    }
                }
                <?php endif; ?>
            }
        }
    });
});
</script>
