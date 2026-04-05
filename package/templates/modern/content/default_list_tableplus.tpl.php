<?php
/**
 * Template Name: CRM
 * Template Type: content
 */

if (!function_exists('crm_days_plural')) {
    function crm_days_plural($n) {
        $n = abs($n);
        $n100 = $n % 100;
        $n10 = $n % 10;
        if ($n100 >= 11 && $n100 <= 19) return $n . ' дней';
        if ($n10 === 1) return $n . ' день';
        if ($n10 >= 2 && $n10 <= 4) return $n . ' дня';
        return $n . ' дней';
    }
}

if( $ctype['options']['list_show_filter'] ) {
    $this->renderAsset('ui/filter-panel', [
        'css_prefix'   => $ctype['name'],
        'page_url'     => $page_url,
        'fields'       => $fields,
        'props_fields' => $props_fields,
        'props'        => $props,
        'filters'      => $filters,
        'ext_hidden_params' => $ext_hidden_params,
        'is_expanded'  => $ctype['options']['list_expand_filter']
    ]);
}
?>
<?php if (!$items){ ?>
    <p class="alert alert-info mt-4 alert-list-empty">
        <?php if(!empty($ctype['labels']['many'])){ ?>
            <?php echo sprintf(LANG_TARGET_LIST_EMPTY, $ctype['labels']['many']); ?>
        <?php } else { ?>
            <?php echo LANG_LIST_EMPTY; ?>
        <?php } ?>
    </p>
<?php return; } ?>

<?php 
    $first_item = reset($items); 
    
    $grand_total_expenses = 0;
    $grand_total_profit = 0;
    $grand_users = []; 

    $excel_data = [
        'items' => []
    ];

    $grouped_fields = ['title', 'recordid', 'tuv', 'date_pub', 'verkaufdate', 'income', 'expenses'];
?>

<div class="content_list table <?php echo $ctype['name']; ?>_list table-responsive-md mt-3 mt-md-4">

    <table class="table table-hover" style="margin-bottom: 0;">
        <thead>
            <tr>
                <th>Детали сделки</th>
                <th>Финансы</th>
                
                <?php foreach($first_item['fields_names'] as $field){ 
                    if (in_array($field['name'], $grouped_fields)) continue;
                ?>
                    <th <?php if ($field['label_pos'] === 'none') { ?>class="d-none d-lg-table-cell"<?php } ?>>
                        <?php echo string_replace_svg_icons($field['title']); ?>
                    </th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($items as $item){ ?>
            
            <?php 
                $item_title = isset($item['title']) ? strip_tags($item['title']) : 'Запись без названия';
                $excel_item = [
                    'title' => $item_title,
                    'expenses_list' => [],
                    'payouts' => []
                ];

                $d_today = new DateTime();
                $d_today->setTime(0, 0, 0);

                $raw_date_pub = (!empty($item['date_pub']) && strpos($item['date_pub'], '0000') === false) ? $item['date_pub'] : null;
                $raw_verkaufdate = (!empty($item['verkaufdate']) && strpos($item['verkaufdate'], '0000') === false) ? $item['verkaufdate'] : null;

                $f_date_pub = $raw_date_pub ? date('d.m.Y', strtotime($raw_date_pub)) : '';
                $f_verkaufdate = $raw_verkaufdate ? date('d.m.Y', strtotime($raw_verkaufdate)) : '';

                $days_in_work_str = '';
                if ($raw_date_pub) {
                    $d1 = new DateTime($raw_date_pub);
                    $d1->setTime(0, 0, 0);
                    $d2 = $raw_verkaufdate ? new DateTime($raw_verkaufdate) : clone $d_today;
                    $d2->setTime(0, 0, 0);
                    
                    $diff_work = $d1->diff($d2);
                    $w_months = ($diff_work->y * 12) + $diff_work->m;
                    $w_days = $diff_work->d;

                    if ($w_months > 0) {
                        $days_in_work_str = $w_months . ' мес.';
                        if ($w_days > 0) {
                            $days_in_work_str .= ' ' . $w_days . ' дн.';
                        }
                    } else {
                        $days_in_work_str = crm_days_plural($w_days);
                    }
                }

                $raw_tuv = (!empty($item['tuv']) && strpos($item['tuv'], '0000') === false) ? $item['tuv'] : null;
                $f_tuv_date = $raw_tuv ? date('d.m.Y', strtotime($raw_tuv)) : '';
                $tuv_days_str = '';
                
                if ($raw_tuv) {
                    $d_tuv = new DateTime($raw_tuv);
                    $d_tuv->setTime(0, 0, 0);
                    
                    $diff_tuv = $d_today->diff($d_tuv);
                    $diff_days = (int)$diff_tuv->format('%R%a');
                    $total_months = ($diff_tuv->y * 12) + $diff_tuv->m;
                    
                    if ($diff_days > 0) {
                        if ($total_months > 0) {
                            $tuv_days_str = '' . $total_months . ' мес.';
                        } else {
                            $tuv_days_str = 'Менее 1 мес.';
                        }
                    } elseif ($diff_days === 0) {
                        $tuv_days_str = 'Истекает сегодня';
                    } else {
                        if ($total_months > 0) {
                            $tuv_days_str = 'Просрочено на ' . $total_months . ' мес.';
                        } else {
                            $tuv_days_str = 'Просрочено менее 1 мес.';
                        }
                    }
                } elseif (isset($item['fields']['tuv'])) {
                    $f_tuv_date = strip_tags($item['fields']['tuv']['html']);
                }

                $f_recordid = isset($item['fields']['recordid']) ? strip_tags($item['fields']['recordid']['html']) : ($item['recordid'] ?? '');
                $f_income = isset($item['fields']['income']) ? strip_tags($item['fields']['income']['html']) : ($item['income'] ?? '0');
                
                $excel_item['recordid'] = $f_recordid;
            ?>
            
            <tr>
                <td class="align-middle">
                    
                    <?php if ($ctype['options']['item_on']){ ?>
                        <h3 class="h5 m-0 mb-1" style="line-height: 1.2;">
                        <?php if ($item['parent_id']){ ?>
                            <a class="parent_title text-muted" href="<?php echo rel_to_href($item['parent_url']); ?>">
                                <?php html($item['parent_title']); ?>
                            </a> &rarr;
                        <?php } ?>
                        <a href="<?php echo href_to($ctype['name'], $item['slug'].'.html'); ?>">
                            <?php html($item['title']); ?>
                        </a>
                        <?php if ($f_recordid) { ?>
                            <span class="text-muted" style="margin-left: 8px; font-weight: normal;">(<?php echo $f_recordid; ?>)</span>
                        <?php } ?>
                        </h3>
                    <?php } else { ?>
                        <h3 class="h5 m-0 mb-1">
                            <?php html($item['title']); ?>
                            <?php if ($f_recordid) { ?>
                                <span class="text-muted" style="margin-left: 8px; font-weight: normal;">(<?php echo $f_recordid; ?>)</span>
                            <?php } ?>
                        </h3>
                    <?php } ?>

                    <?php if ($f_date_pub || $f_verkaufdate || $f_tuv_date) { ?>
                        <div style="font-size: 0.85em; background: #f8f9fa; padding: 6px 10px; border-radius: 4px; border: 1px solid #e9ecef; display: inline-block; margin-top: 5px;">
                            
                            <?php if ($f_date_pub || $f_verkaufdate) { ?>
                                <span style="margin-right: 12px; white-space: nowrap;">
                                    <?php if ($f_date_pub) { echo $f_date_pub; } ?>
                                    <?php if ($f_date_pub && $f_verkaufdate) { echo '<span style="color: #adb5bd; margin: 0 4px;">/</span>'; } ?>
                                    <?php if ($f_verkaufdate) { echo $f_verkaufdate; } ?>
                                </span>
                            <?php } ?>

                            <?php if ($f_tuv_date) { ?>
                                <span style="margin-right: 12px; white-space: nowrap;">
                                    <span style="color: #6c757d;">TüV:</span> <?php echo $f_tuv_date; ?>
                                    <?php if ($tuv_days_str) { 
                                        $tuv_color = (strpos($tuv_days_str, 'Просрочено') !== false) ? '#dc3545' : '#28a745';
                                        if ($tuv_days_str === 'Истекает сегодня') $tuv_color = '#fd7e14';
                                    ?>
                                        <span style="color: <?php echo $tuv_color; ?>; margin-left: 4px;">(<?php echo $tuv_days_str; ?>)</span>
                                    <?php } ?>
                                </span>
                            <?php } ?>
                            
                            <?php if ($days_in_work_str !== '') { ?>
                                <span style="white-space: nowrap; color: #495057;">
                                    В работе: <?php echo $days_in_work_str; ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </td>

                <td class="align-middle">
                    <?php
                        $expenses_data = !empty($item['expenses']) ? json_decode($item['expenses'], true) : [];
                        $income = isset($item['income']) ? (float)$item['income'] : 0;
                        
                        $total_expenses = 0;
                        $users_map = [];

                        if (is_array($expenses_data)) {
                            foreach($expenses_data as $exp){
                                $cost = isset($exp['cost']) ? (float)$exp['cost'] : 0;
                                $uid = isset($exp['user_id']) ? $exp['user_id'] : 0;
                                $uname = isset($exp['user_name']) ? $exp['user_name'] : 'Неизвестный';
                                
                                $total_expenses += $cost;
                                if ($uid) {
                                    if (!isset($users_map[$uid])) {
                                        $users_map[$uid] = ['name' => $uname, 'spent' => 0];
                                    }
                                    $users_map[$uid]['spent'] += $cost;
                                }
                                
                                $excel_item['expenses_list'][] = [
                                    'title' => isset($exp['title']) ? $exp['title'] : 'Без названия',
                                    'cost' => $cost,
                                    'user' => $uname
                                ];
                            }
                        }

                        $net_profit = $income - $total_expenses;
                        $p_count = count($users_map);
                        $profit_per_person = $p_count > 0 ? ($net_profit / $p_count) : 0;
                        
                        $grand_total_expenses += $total_expenses;
                        $grand_total_profit += $net_profit;
                        
                        if ($p_count > 0) {
                            foreach ($users_map as $uid => $u) {
                                $payout = $profit_per_person + $u['spent'];
                                
                                if (!isset($grand_users[$uid])) {
                                    $grand_users[$uid] = ['name' => $u['name'], 'spent' => 0, 'payout' => 0, 'pure_profit' => 0];
                                }
                                $grand_users[$uid]['spent'] += $u['spent'];
                                $grand_users[$uid]['payout'] += $payout;
                                $grand_users[$uid]['pure_profit'] += $profit_per_person;
                                
                                $excel_item['payouts'][] = [
                                    'name' => $u['name'],
                                    'total' => round($payout, 2),
                                    'pure' => round($profit_per_person, 2)
                                ];
                            }
                        }
                        
                        $excel_item['income'] = $income;
                        $excel_item['total_expenses'] = $total_expenses;
                        $excel_item['net_profit'] = $net_profit;
                        $excel_data['items'][] = $excel_item;
                    ?>
                    
                    <div style="font-size: 0.85em; background: #f8f9fa; padding: 8px 10px; border-radius: 4px; border: 1px solid #e9ecef; min-width: 140px; white-space: nowrap;">
                        <div style="margin-bottom: 3px;">
                            <span class="text-muted">Продажа:</span> <?php echo $f_income; ?>
                        </div>
                        <div style="margin-bottom: 5px;">
                            <span class="text-muted">Расходы:</span> <?php echo $total_expenses; ?>
                        </div>
                        <div style="border-top: 1px dashed #ccc; padding-top: 5px;">
                            <?php if ($net_profit < 0) { ?>
                                <span class="text-muted">Убыток:</span> <span style="color: #dc3545;"><?php echo $net_profit; ?></span>
                            <?php } else { ?>
                                <span class="text-muted">Прибыль:</span> <span style="color: #28a745;"><?php echo $net_profit; ?></span>
                            <?php } ?>
                        </div>
                    </div>
                </td>

                <?php foreach($item['fields_names'] as $_field){ 
                    if (in_array($_field['name'], $grouped_fields)) continue;
                ?>
                    <td class="align-middle field ft_<?php echo $_field['type']; ?> f_<?php echo $_field['name']; ?><?php if ($_field['label_pos'] === 'none') { ?> d-none d-lg-table-cell<?php } ?>">
                        <?php if (isset($item['fields'][$_field['name']])) { ?>
                            <?php echo $item['fields'][$_field['name']]['html']; ?>
                        <?php } ?>
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <?php
        $exp_strings = [];
        $pure_strings = [];
        $payout_strings = [];
        
        $excel_data['summary'] = [
            'total_expenses' => $grand_total_expenses,
            'total_profit' => $grand_total_profit,
            'users' => []
        ];
        
        foreach ($grand_users as $gu) {
            if ($gu['spent'] > 0) {
                $exp_strings[] = htmlspecialchars($gu['name']) . ': ' . $gu['spent'];
            }
            $pure_strings[] = htmlspecialchars($gu['name']) . ': ' . round($gu['pure_profit'], 2);
            $payout_strings[] = htmlspecialchars($gu['name']) . ': <span style="color: #155724;">' . round($gu['payout'], 2) . '</span>';
            
            $excel_data['summary']['users'][] = [
                'name' => $gu['name'],
                'spent' => $gu['spent'],
                'payout' => round($gu['payout'], 2),
                'pure_profit' => round($gu['pure_profit'], 2)
            ];
        }
        
        $exp_str = implode(', ', $exp_strings);
        $pure_str = implode(', ', $pure_strings);
        $payout_str = implode(' <span style="color:#ccc;">|</span> ', $payout_strings);
    ?>

    <div class="crm-summary-widget mt-3" style="background: #eef9f0; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
            <h4 style="margin-top: 0; color: #155724; margin-bottom: 15px;">Итого по отфильтрованному списку</h4>
            
            <button onclick="downloadExcel()" class="btn btn-success btn-sm" style="margin-bottom: 15px; white-space: nowrap;">
                <svg style="width:16px; height:16px; margin-right:5px; vertical-align:text-bottom;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                Скачать в Excel (.xlsx)
            </button>
        </div>
        
        <div style="font-size: 1.1em; margin-bottom: 10px;">
            <span style="margin-right: 20px; display: inline-block; margin-bottom: 5px;">
                <span style="color: #155724;">Всего расходов:</span> <?php echo $grand_total_expenses; ?>
                <?php if($exp_str) { ?>
                    <span style="color: #6c757d; font-size: 0.9em;">(<?php echo $exp_str; ?>)</span>
                <?php } ?>
            </span>
            <span style="display: inline-block;">
                <?php if ($grand_total_profit < 0) { ?>
                    <span style="color: #155724;">Всего убыток:</span> <span style="color: #dc3545;"><?php echo $grand_total_profit; ?></span>
                <?php } else { ?>
                    <span style="color: #155724;">Всего прибыль:</span> <span style="color: #28a745;"><?php echo $grand_total_profit; ?></span>
                <?php } ?>
            </span>
        </div>
        
        <?php if ($grand_total_profit >= 0) { ?>
            <div style="border-top: 1px solid #c3e6cb; padding-top: 10px;">
                <span style="color: #155724; font-size: 1.05em;">
                    Всего выплаты участникам 
                    <?php if($pure_str) { ?>
                        <span style="color: #6c757d; font-size: 0.9em;">(из них чистыми: <?php echo $pure_str; ?>)</span>
                    <?php } ?>:
                </span>
                
                <?php if (!empty($grand_users)) { ?>
                    <div style="margin-top: 5px; font-size: 1.1em;">
                        <?php echo $payout_str; ?>
                    </div>
                <?php } else { ?>
                    <span style="color: gray; margin-left: 10px;">Нет данных о выплатах</span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
function downloadExcel() {
    var data = <?php echo json_encode($excel_data, JSON_UNESCAPED_UNICODE); ?>;
    var wb = XLSX.utils.book_new();

    var summaryData = [
        ["ИТОГО ПО ОТФИЛЬТРОВАННОМУ СПИСКУ"],
        ["Общие расходы всех сделок:", data.summary.total_expenses],
        ["Общая чистая прибыль:", data.summary.total_profit],
        [""],
        ["ИТОГОВЫЕ ВЫПЛАТЫ УЧАСТНИКАМ"],
        ["Участник", "Понес расходов", "К выплате (итого)", "Из них чистыми"]
    ];
    
    data.summary.users.forEach(function(u) {
        summaryData.push([u.name, u.spent, u.payout, u.pure_profit]);
    });

    summaryData.push([""]);
    summaryData.push(["КРАТКИЙ СПИСОК СДЕЛОК"]);
    summaryData.push(["Название сделки", "Артикул", "Приход", "Расходы", "Чистая прибыль"]);
    
    data.items.forEach(function(item) {
        summaryData.push([item.title, item.recordid || "-", item.income, item.total_expenses, item.net_profit]);
    });

    var wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
    wsSummary['!cols'] = [{wch: 35}, {wch: 15}, {wch: 20}, {wch: 20}, {wch: 20}];
    XLSX.utils.book_append_sheet(wb, wsSummary, "Сводка");

    data.items.forEach(function(item, index) {
        var wsData = [
            ["ДЕТАЛИ СДЕЛКИ"],
            ["Название:", item.title],
            ["Артикул:", item.recordid || "Не указан"],
            ["Сумма продажи (Приход):", item.income],
            ["Общие расходы:", item.total_expenses],
            ["Чистая прибыль:", item.net_profit],
            [""],
            ["СПИСОК ПОНЕСЕННЫХ РАСХОДОВ"],
            ["Название расхода", "Сумма", "Кто платил"]
        ];

        if(item.expenses_list && item.expenses_list.length > 0) {
            item.expenses_list.forEach(function(exp) {
                wsData.push([exp.title, exp.cost, exp.user]);
            });
        } else {
            wsData.push(["Нет расходов", "", ""]);
        }

        wsData.push([""]);
        wsData.push(["РАСПРЕДЕЛЕНИЕ ПРИБЫЛИ ПО ЭТОЙ СДЕЛКЕ"]);
        wsData.push(["Участник", "К выплате (Доля + Возврат)", "Из них чистыми"]);
        
        if(item.payouts && item.payouts.length > 0) {
            item.payouts.forEach(function(p) {
                wsData.push([p.name, p.total, p.pure]);
            });
        } else {
            wsData.push(["Нет участников", "", ""]);
        }

        var wsItem = XLSX.utils.aoa_to_sheet(wsData);
        wsItem['!cols'] = [{wch: 35}, {wch: 25}, {wch: 20}];
        
        var safeTitle = item.title.replace(/[\[\]\/\*\?\:\\\|]/g, '').substring(0, 20);
        var sheetName = (index + 1) + ". " + safeTitle;
        
        try {
            XLSX.utils.book_append_sheet(wb, wsItem, sheetName);
        } catch(e) {
            XLSX.utils.book_append_sheet(wb, wsItem, "Сделка " + (index+1));
        }
    });

    var dateStr = new Date().toISOString().slice(0,10);
    XLSX.writeFile(wb, "crm_detailed_export_" + dateStr + ".xlsx");
}
</script>

<?php echo html_pagebar($page, $perpage, $total, $page_url, $filter_query); ?>
