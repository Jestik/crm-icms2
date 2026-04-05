<?php

class fieldExpenses extends cmsFormField {

    public $title = 'CRM Калькулятор';
    public $sql   = 'text';
    public $allow_index = false;

    public function getOptions(){
        return array(
            new fieldString('income_field_name', array(
                'title'   => 'Системное имя поля "Приход"',
                'hint'    => 'Укажите системное имя числового поля (по умолчанию: income)',
                'default' => 'income'
            )),
            new fieldString('currency', array(
                'title'   => 'Валюта',
                'hint'    => 'Например: руб., $, €',
                'default' => '€'
            ))
        );
    }

    public function getInput($value) {
        
        $users_model = cmsCore::getModel('users');
        $users = $users_model->get('users'); 
        
        $user_options = '<option value="">-- Участник --</option>';
        if (is_array($users)) {
            foreach($users as $u){
                $user_options .= '<option value="'.$u['id'].'">'.htmlspecialchars($u['nickname']).'</option>';
            }
        }

        $current_data = htmlspecialchars((string)($value ? $value : '[]'));
        $field_name = $this->name;
        $label = $this->title;
        
        $currency = $this->getOption('currency', '€');

        $html_template = <<<'HTML'
        <style>
            .crm-edit-container { background: #fff; border: 1px solid #ccc; border-radius: 6px; padding: 15px; margin-top: 5px; }
            .crm-row { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; }
            .crm-row .form-control { margin: 0 !important; height: 34px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            .custom-del-btn { 
                padding: 8px; border: none; border-radius: 4px; cursor: pointer; 
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .crm-footer-summary { 
                margin-top: 15px; padding: 10px 15px; background: #f8f9fa; 
                border: 1px solid #eee; border-radius: 4px; display: flex; align-items: center; font-weight: bold;
            }
            .add-btn-wrapper { margin-top: 10px; border-top: 1px solid #eee; padding-top: 12px; }
        </style>

        <div class="field">
            <label for="{FIELD_NAME}">{FIELD_TITLE}</label>
            <div class="crm-edit-container">
                <div id="expenses-list"></div>

                <div class="add-btn-wrapper">
                    <button type="button" class="button btn btn-primary btn-sm" id="btn-add-expense">
                        <i class="fa fa-plus"></i> Добавить расход
                    </button>
                </div>
                
                <div class="crm-footer-summary">
                    <span style="margin-right: 10px;">Итоговая сумма расходов:</span>
                    <span id="calc-total" class="text-danger">0</span>&nbsp;<span class="text-danger">{CURRENCY}</span>
                </div>

                <input type="hidden" name="{FIELD_NAME}" id="expenses-data" value="{CURRENT_DATA}">
            </div>
        </div>

        <script>
            (function() {
                var container = document.getElementById("expenses-list");
                var dataInput = document.getElementById("expenses-data");
                var btnAdd = document.getElementById("btn-add-expense");
                
                var data = JSON.parse(dataInput.value || "[]");
                var userOpts = `{USER_OPTIONS}`;

                function render() {
                    container.innerHTML = "";
                    if(data.length === 0) {
                        container.innerHTML = '<div style="padding:10px; color:#999;">Список расходов пуст</div>';
                    }

                    data.forEach(function(item, idx) {
                        var row = document.createElement("div");
                        row.className = "crm-row";
                        row.innerHTML = `
                            <input type="text" class="form-control" placeholder="На что потрачено" value="${item.title || ""}" onchange="upd(${idx}, 'title', this.value)" style="flex: 3;">
                            <input type="number" class="form-control" placeholder="Сумма" value="${item.cost || ""}" oninput="upd(${idx}, 'cost', this.value)" style="flex: 1;">
                            <select class="form-control" onchange="upd(${idx}, 'user_id', this.value); upd(${idx}, 'user_name', this.options[this.selectedIndex].text);" style="flex: 2;">
                                ${userOpts}
                            </select>
                            <button type="button" onclick="del(${idx})" class="btn-danger custom-del-btn" title="Удалить">
                                <svg class="icms-svg-icon" fill="currentColor" style="width:16px; height:16px; display:block;">
                                    <use href="/templates/modern/images/icons/solid.svg#times-circle"></use>
                                </svg>
                            </button>
                        `;
                        container.appendChild(row);
                        if(item.user_id) row.querySelector("select").value = item.user_id;
                    });
                    calc();
                }

                window.upd = function(idx, key, val) {
                    if(key === 'cost') val = parseFloat(val) || 0;
                    data[idx][key] = val;
                    dataInput.value = JSON.stringify(data);
                    calc();
                };

                window.del = function(idx) {
                    data.splice(idx, 1);
                    dataInput.value = JSON.stringify(data);
                    render();
                };

                btnAdd.addEventListener("click", function() {
                    data.push({ title: "", cost: 0, user_id: "", user_name: "" });
                    render();
                });

                function calc() {
                    var total = 0;
                    data.forEach(function(i) { total += parseFloat(i.cost || 0); });
                    document.getElementById("calc-total").innerText = total.toLocaleString();
                }

                render();
            })();
        </script>
HTML;

        return str_replace(
            ['{FIELD_NAME}', '{CURRENT_DATA}', '{USER_OPTIONS}', '{FIELD_TITLE}', '{CURRENCY}'],
            [$field_name, $current_data, $user_options, $label, $currency],
            $html_template
        );
    }

    public function parse($value){
        if (!$value || $value == '[]') return '';
        
        $data = json_decode($value, true);
        $total_expenses = 0;
        $users_map = [];

        foreach($data as $item){
            $cost = (float)($item['cost'] ?? 0);
            $total_expenses += $cost;
            if ($item['user_id'] ?? false) {
                if (!isset($users_map[$item['user_id']])) {
                    $users_map[$item['user_id']] = ['name' => $item['user_name'], 'spent' => 0];
                }
                $users_map[$item['user_id']]['spent'] += $cost;
            }
        }

        $income_field = $this->getOption('income_field_name', 'income');
        $currency     = $this->getOption('currency', 'руб.');

        $income = (float)($this->item[$income_field] ?? 0);
        $net_profit = $income - $total_expenses;
        $participants_count = count($users_map);
        
        $profit_per_person = ($income > 0 && $participants_count > 0) ? ($net_profit / $participants_count) : 0;

        $html = '<div class="field_expenses_view" style="margin-top: 10px;">';
        
        $html .= '<table class="datagrid" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; margin: 0;">';
        
        $html .= '<thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; text-align: left;">Описание расхода</th>
                        <th style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; width: 150px; text-align: left;">Сумма</th>
                        <th style="padding: 12px; border-bottom: 1px solid #dee2e6; text-align: left;">Кто платил</th>
                    </tr>
                  </thead>';
        
        $html .= '<tbody>';
        
        foreach($data as $item){
            $html .= '<tr>';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">'.htmlspecialchars($item['title']).'</td>';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">'.number_format($item['cost'], 0, '.', ' ') . ' ' . $currency . '</td>';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6;">'.htmlspecialchars($item['user_name']).'</td>';
            $html .= '</tr>';
        }

        $html .= '<tr style="background: #fcfcfc;">';
        $html .= '<td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Общий приход:</strong></td>';
        $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">' . number_format($income, 0, '.', ' ') . ' ' . $currency . '</td>';
        $html .= '<td style="border-bottom: 1px solid #dee2e6;"></td>';
        $html .= '</tr>';
        
        $html .= '<tr style="background: #fcfcfc;">';
        $html .= '<td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Общие расходы:</strong></td>';
        $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: #d33;">-' . number_format($total_expenses, 0, '.', ' ') . ' ' . $currency . '</td>';
        $html .= '<td style="border-bottom: 1px solid #dee2e6;"></td>';
        $html .= '</tr>';

        $html .= '<tr style="background: #f0fff4;">';
        $html .= '<td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Чистая прибыль:</strong></td>';
        $html .= '<td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong style="color: #28a745;">' . number_format($net_profit, 0, '.', ' ') . ' ' . $currency . '</strong></td>';
        $html .= '<td style="border-bottom: 1px solid #dee2e6;"></td>';
        $html .= '</tr>';

        if ($participants_count > 0) {
            $html .= '<tr><td colspan="3" style="padding: 15px; background: #fff;">';
            
            if ($income > 0) {
                $html .= '<div style="margin-bottom: 8px; font-weight: bold; color: #555;">Расчет выплат (Доля прибыли + Возврат):</div>';
            } else {
                $html .= '<div style="margin-bottom: 8px; font-weight: bold; color: #555;">Средства к возврату (Приход отсутствует):</div>';
            }

            foreach ($users_map as $user) {
                $payout = $profit_per_person + $user['spent'];
                $html .= '<div style="margin-top:4px;">';
                $html .= '🔹 ' . htmlspecialchars($user['name']) . ': <b>' . number_format($payout, 2, '.', ' ') . ' ' . $currency . '</b> ';
                
                if ($income > 0) {
                    $html .= '<span style="color:#888;">(чистая доля ' . number_format($profit_per_person, 0, '.', ' ') . ' ' . $currency . ' + возврат потраченных ' . number_format($user['spent'], 0, '.', ' ') . ' ' . $currency . ')</span>';
                } else {
                    $html .= '<span style="color:#888;">(только возврат потраченных средств)</span>';
                }
                
                $html .= '</div>';
            }
            $html .= '</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>'; 

        return $html;
    }
}
