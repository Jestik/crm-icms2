<?php
declare(strict_types=1);

class fieldExpenses extends cmsFormField {

    public $title = 'CRM Калькулятор';
    public $sql   = 'text';
    public $allow_index = false;

    public function getOptions(): array {
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
        
        $val_str = ($value !== null && $value !== '') ? (string)$value : '[]';
        if (strlen($val_str) > 100000) {
            $val_str = '[]';
        }

        $users_model = cmsCore::getModel('users');
        $users_model->limit(500); 
        $users_model->selectOnly('id')->select('nickname');
        $users = $users_model->get('users'); 
        
        $users_array = [];
        if (is_array($users)) {
            foreach ($users as $u) {
                $users_array[] = [
                    'id'   => (int)$u['id'],
                    'name' => (string)$u['nickname']
                ];
            }
        }

        $current_data = htmlspecialchars($val_str, ENT_QUOTES, 'UTF-8');
        $field_name   = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $label        = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
        $currency     = htmlspecialchars($this->getOption('currency', '€'), ENT_QUOTES, 'UTF-8');

        ob_start();
        try {
            ?>
            <style>
                .crm-edit-container { background: #fff; border: 1px solid #ccc; border-radius: 6px; padding: 15px; margin-top: 5px; }
                .crm-row { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; }
                .crm-row .form-control { margin: 0 !important; height: 34px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
                .custom-del-btn { padding: 8px; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
                .crm-footer-summary { margin-top: 15px; padding: 10px 15px; background: #f8f9fa; border: 1px solid #eee; border-radius: 4px; display: flex; align-items: center; font-weight: bold; }
                .add-btn-wrapper { margin-top: 10px; border-top: 1px solid #eee; padding-top: 12px; }
            </style>

            <div class="field">
                <label for="<?php echo $field_name; ?>"><?php echo $label; ?></label>
                <div class="crm-edit-container">
                    <div id="expenses-list_<?php echo $field_name; ?>"></div>
                    <div class="add-btn-wrapper">
                        <button type="button" class="button btn btn-primary btn-sm" id="btn-add-expense_<?php echo $field_name; ?>">
                            <i class="fa fa-plus"></i> Добавить расход
                        </button>
                    </div>
                    <div class="crm-footer-summary">
                        <span style="margin-right: 10px;">Итоговая сумма расходов:</span>
                        <span id="calc-total_<?php echo $field_name; ?>" class="text-danger">0</span>&nbsp;<span class="text-danger"><?php echo $currency; ?></span>
                    </div>
                    <input type="hidden" name="<?php echo $field_name; ?>" id="expenses-data_<?php echo $field_name; ?>" value="<?php echo $current_data; ?>">
                </div>
            </div>

            <script>
                (function() {
                    const fieldName = <?php echo json_encode($this->name, JSON_THROW_ON_ERROR); ?>;
                    const usersList = <?php echo json_encode($users_array, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR); ?>;
                    
                    const container = document.getElementById("expenses-list_" + fieldName);
                    const dataInput = document.getElementById("expenses-data_" + fieldName);
                    const btnAdd    = document.getElementById("btn-add-expense_" + fieldName);
                    
                    let data = [];
                    try {
                        data = JSON.parse(dataInput.value || "[]");
                        if (!Array.isArray(data)) data = [];
                    } catch(e) {
                        data = [];
                    }
                    
                    if (data.length > 500) {
                        data = data.slice(0, 500);
                    }

                    function saveAndCalc() {
                        dataInput.value = JSON.stringify(data);
                        let totalCents = 0; 
                        
                        data.forEach(function(item) { 
                            let cost = Number(item.cost);
                            if (isFinite(cost) && cost > 0) {
                                totalCents += Math.round(cost * 100);
                            }
                        });
                        
                        let total = totalCents / 100;
                        document.getElementById("calc-total_" + fieldName).innerText = total.toLocaleString();
                    }

                    function render() {
                        container.textContent = ""; 
                        
                        if (data.length === 0) {
                            const emptyMsg = document.createElement("div");
                            emptyMsg.style.padding = "10px";
                            emptyMsg.style.color = "#999";
                            emptyMsg.textContent = "Список расходов пуст";
                            container.appendChild(emptyMsg);
                            saveAndCalc();
                            return;
                        }

                        data.forEach(function(item, idx) {
                            const row = document.createElement("div");
                            row.className = "crm-row";

                            const inputTitle = document.createElement("input");
                            inputTitle.type = "text";
                            inputTitle.className = "form-control";
                            inputTitle.placeholder = "На что потрачено";
                            inputTitle.maxLength = 255;
                            inputTitle.value = item.title || "";
                            inputTitle.style.flex = "3";
                            inputTitle.addEventListener("change", function() {
                                item.title = String(this.value).substring(0, 255);
                                saveAndCalc();
                            });
                            row.appendChild(inputTitle);

                            const inputCost = document.createElement("input");
                            inputCost.type = "number";
                            inputCost.min = "0"; 
                            inputCost.className = "form-control";
                            inputCost.placeholder = "Сумма";
                            inputCost.value = item.cost || "";
                            inputCost.style.flex = "1";
                            inputCost.addEventListener("input", function() {
                                let val = Number(this.value);
                                item.cost = (isFinite(val) && val > 0) ? val : 0;
                                saveAndCalc();
                            });
                            row.appendChild(inputCost);

                            const selectUser = document.createElement("select");
                            selectUser.className = "form-control";
                            selectUser.style.flex = "2";
                            
                            const defaultOpt = document.createElement("option");
                            defaultOpt.value = "";
                            defaultOpt.text = "-- Участник --";
                            selectUser.appendChild(defaultOpt);
                            
                            usersList.forEach(function(u) {
                                const opt = document.createElement("option");
                                opt.value = u.id;
                                opt.text = u.name;
                                if (item.user_id == u.id) opt.selected = true;
                                selectUser.appendChild(opt);
                            });
                            
                            selectUser.addEventListener("change", function() {
                                item.user_id = parseInt(this.value, 10) || 0;
                                saveAndCalc();
                            });
                            row.appendChild(selectUser);

                            const btnDel = document.createElement("button");
                            btnDel.type = "button";
                            btnDel.className = "btn-danger custom-del-btn";
                            btnDel.title = "Удалить";
                            
                            const svgNS = "http://www.w3.org/2000/svg";
                            const svg = document.createElementNS(svgNS, "svg");
                            svg.setAttribute("class", "icms-svg-icon");
                            svg.setAttribute("fill", "currentColor");
                            svg.setAttribute("style", "width:16px; height:16px; display:block;");
                            
                            const use = document.createElementNS(svgNS, "use");
                            use.setAttribute("href", "/templates/modern/images/icons/solid.svg#times-circle");
                            
                            svg.appendChild(use);
                            btnDel.appendChild(svg);
                            
                            btnDel.addEventListener("click", function() {
                                data.splice(idx, 1);
                                render();
                            });
                            row.appendChild(btnDel);

                            container.appendChild(row);
                        });
                        saveAndCalc();
                    }

                    btnAdd.addEventListener("click", function() {
                        if (data.length >= 500) return;
                        data.push({ title: "", cost: 0, user_id: 0 });
                        render();
                    });

                    render();
                })();
            </script>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldExpenses::getInput render error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function parse($value) {
        
        $val_str = ($value !== null && $value !== '') ? (string)$value : '[]';
        if ($val_str === '[]' || strlen($val_str) > 100000) {
            return '';
        }

        try {
            $data = json_decode($val_str, true, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '';
        }

        if (!is_array($data)) {
            return '';
        }
        
        if (count($data) > 500) {
            $data = array_slice($data, 0, 500);
        }

        $total_expenses_cents = 0; 
        $user_ids = [];
        
        $normalized_data = [];
        foreach ($data as $item) {
            if (!is_array($item)) continue;
            
            $title   = isset($item['title']) ? trim((string)$item['title']) : '';
            $title   = mb_substr($title, 0, 255); 
            
            $cost    = isset($item['cost']) ? (float)$item['cost'] : 0.0;
            $cost    = max(0.0, $cost); 
            
            $user_id = isset($item['user_id']) ? (int)$item['user_id'] : 0;
            $user_id = max(0, $user_id);
            
            if ($title === '' && $cost === 0.0 && $user_id === 0) continue;

            $normalized_data[] = [
                'title'   => $title,
                'cost'    => $cost,
                'user_id' => $user_id
            ];

            if ($user_id > 0) {
                $user_ids[] = $user_id;
            }
        }
        
        $data = $normalized_data;
        if (empty($data)) return '';

        $user_ids = array_unique($user_ids);
        $real_users = [];
        if (!empty($user_ids)) {
            $users_model = cmsCore::getModel('users');
            $users_model->selectOnly('id')->select('nickname');
            $users_model->filterIn('id', $user_ids);
            $fetched_users = $users_model->get('users');
            if ($fetched_users) {
                foreach ($fetched_users as $fu) {
                    $real_users[$fu['id']] = $fu['nickname'];
                }
            }
        }

        $users_map = [];

        foreach ($data as $item) {
            $cost_cents = (int)round($item['cost'] * 100);
            $total_expenses_cents += $cost_cents;
            $user_id = $item['user_id'];
            
            if ($user_id > 0) {
                if (!isset($users_map[$user_id])) {
                    $users_map[$user_id] = [
                        'name'  => $real_users[$user_id] ?? '—', 
                        'spent_cents' => 0
                    ];
                }
                $users_map[$user_id]['spent_cents'] += $cost_cents;
            }
        }

        $income_field = $this->getOption('income_field_name', 'income');
        $currency = htmlspecialchars($this->getOption('currency', '€'), ENT_QUOTES, 'UTF-8');

        $income = 0.0;
        if (is_array($this->item) && array_key_exists($income_field, $this->item)) {
            $income = max(0.0, (float)$this->item[$income_field]);
        }
        
        $income_cents = (int)round($income * 100);
        $net_profit_cents = $income_cents - $total_expenses_cents;
        $participants_count = count($users_map);
        
        $profit_per_person_cents = ($income_cents > 0 && $participants_count > 0) 
            ? (int)round($net_profit_cents / $participants_count) 
            : 0;

        $total_expenses = $total_expenses_cents / 100;
        $net_profit = $net_profit_cents / 100;
        $profit_per_person = $profit_per_person_cents / 100;

        ob_start();
        try {
            ?>
            <div class="field_expenses_view" style="margin-top: 10px;">
                <table class="datagrid" style="width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; margin: 0;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; text-align: left;">Описание расхода</th>
                            <th style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; width: 150px; text-align: left;">Сумма</th>
                            <th style="padding: 12px; border-bottom: 1px solid #dee2e6; text-align: left;">Кто платил</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data as $item): 
                        $uid = $item['user_id'];
                        $trusted_name = $uid > 0 ? ($real_users[$uid] ?? '—') : '—';
                    ?>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">
                                <?php echo number_format($item['cost'], 0, '.', ' ') . ' ' . $currency; ?>
                            </td>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($trusted_name, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr style="background: #fcfcfc;">
                            <td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Общий приход:</strong></td>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><?php echo number_format($income, 0, '.', ' ') . ' ' . $currency; ?></td>
                            <td style="border-bottom: 1px solid #dee2e6;"></td>
                        </tr>
                        <tr style="background: #fcfcfc;">
                            <td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Общие расходы:</strong></td>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: #d33;">-<?php echo number_format($total_expenses, 0, '.', ' ') . ' ' . $currency; ?></td>
                            <td style="border-bottom: 1px solid #dee2e6;"></td>
                        </tr>
                        <tr style="background: #f0fff4;">
                            <td style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong>Чистая прибыль:</strong></td>
                            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6;"><strong style="color: <?php echo $net_profit < 0 ? '#d33' : '#28a745'; ?>;"><?php echo number_format($net_profit, 0, '.', ' ') . ' ' . $currency; ?></strong></td>
                            <td style="border-bottom: 1px solid #dee2e6;"></td>
                        </tr>
                        
                        <?php if ($participants_count > 0): ?>
                        <tr>
                            <td colspan="3" style="padding: 15px; background: #fff;">
                                <div style="margin-bottom: 8px; font-weight: bold; color: #555;">
                                    <?php echo ($income > 0) ? 'Расчет выплат (Доля прибыли + Возврат):' : 'Средства к возврату (Приход отсутствует):'; ?>
                                </div>
                                <?php foreach ($users_map as $user): 
                                    $payout_cents = $profit_per_person_cents + $user['spent_cents'];
                                    $payout = $payout_cents / 100;
                                    $spent = $user['spent_cents'] / 100;
                                ?>
                                    <div style="margin-top:4px;">
                                        🔹 <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>: <b style="color: <?php echo $payout < 0 ? '#d33' : '#000'; ?>;"><?php echo number_format($payout, 2, '.', ' ') . ' ' . $currency; ?></b> 
                                        <?php if ($income > 0): ?>
                                            <span style="color:#888;">(чистая доля <?php echo number_format($profit_per_person, 0, '.', ' ') . ' ' . $currency; ?> + возврат потраченных <?php echo number_format($spent, 0, '.', ' ') . ' ' . $currency; ?>)</span>
                                        <?php else: ?>
                                            <span style="color:#888;">(только возврат потраченных средств)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldExpenses::parse render error: ' . $e->getMessage());
            return ''; 
        }
    }
}
