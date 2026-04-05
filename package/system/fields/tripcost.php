<?php

class fieldTripcost extends cmsFormField {

    public $title = 'Калькулятор поездки';
    public $sql   = 'text';
    public $allow_index = false;

    public function getInput($value) {
        
        $users_model = cmsCore::getModel('users');
        $users = $users_model->get('users'); 
        
        $user_options = '<option value="">-- Выберите водителя --</option>';
        if (is_array($users)) {
            foreach($users as $u){
                $user_options .= '<option value="'.$u['id'].'">'.$u['nickname'].'</option>';
            }
        }

        $default_data = json_encode([
            'user_id' => '',
            'user_name' => '',
            'distance' => '',
            'fuel_price' => '',
            'fuel_cons' => '',
            'amort_rate' => '0.2', // Значение по умолчанию 0.2
            'trailer' => false
        ]);
        
        $current_data = htmlspecialchars((string)($value ? $value : $default_data), ENT_QUOTES, 'UTF-8');
        $field_name = $this->name;

        $html_template = <<<'HTML'
        <div id="trip-calculator-wrapper" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #fdfdfd; margin-bottom: 15px;">
            <h4 style="margin-top:0;">Данные маршрута</h4>
            
            <div id="trip-form" style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 4px; display: flex; flex-direction: column; gap: 15px;">
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Водитель</label>
                        <select id="tc-user" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                            {USER_OPTIONS}
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Дистанция (км)</label>
                        <input type="number" step="0.01" id="tc-distance" placeholder="Например: 150.5" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                    </div>
                </div>

                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Цена топлива (€/л)</label>
                        <input type="number" step="0.01" id="tc-fuel-price" placeholder="Например: 1.75" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Расход (л/100км)</label>
                        <input type="number" step="0.01" id="tc-fuel-cons" placeholder="Например: 8.5" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Амортизация (€/км)</label>
                        <input type="number" step="0.01" id="tc-amort" placeholder="В среднем 0.2" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                    <input type="checkbox" id="tc-trailer" style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="tc-trailer" style="font-weight: bold; cursor: pointer;">Поездка с прицепом (+40% к стоимости)</label>
                </div>

            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
            
            <h4 style="margin-top:0;">Итог поездки</h4>
            <div style="background: #eef9f0; padding: 15px; border-radius: 5px; font-size: 1.1em; border: 1px solid #c3e6cb;">
                <p style="margin: 0 0 10px 0;"><strong>Затраты на топливо:</strong> <span id="calc-fuel-cost">0.00</span> €</p>
                <p style="margin: 0 0 10px 0;"><strong>Износ авто (амортизация):</strong> <span id="calc-amort-cost">0.00</span> €</p>
                <p style="margin: 0; font-size: 1.2em; color: #155724;"><strong>Общая стоимость:</strong> <span id="calc-total-cost">0.00</span> €</p>
            </div>

            <input type="hidden" name="{FIELD_NAME}" id="trip-data" value="{CURRENT_DATA}">
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var dataInput = document.getElementById("trip-data");
                var trailerSurchargePercent = 40; 
                
                var elUser = document.getElementById("tc-user");
                var elDist = document.getElementById("tc-distance");
                var elFPrice = document.getElementById("tc-fuel-price");
                var elFCons = document.getElementById("tc-fuel-cons");
                var elAmort = document.getElementById("tc-amort");
                var elTrailer = document.getElementById("tc-trailer");

                var outFuel = document.getElementById("calc-fuel-cost");
                var outAmort = document.getElementById("calc-amort-cost");
                var outTotal = document.getElementById("calc-total-cost");

                try {
                    var rawData = dataInput.value.replace(/&quot;/g, '"');
                    var tripData = JSON.parse(rawData);
                    
                    if (tripData.user_id) elUser.value = tripData.user_id;
                    if (tripData.distance !== "") elDist.value = tripData.distance;
                    if (tripData.fuel_price !== "") elFPrice.value = tripData.fuel_price;
                    if (tripData.fuel_cons !== "") elFCons.value = tripData.fuel_cons;
                    if (tripData.amort_rate !== "") elAmort.value = tripData.amort_rate;
                    if (tripData.trailer) elTrailer.checked = true;
                } catch(e) {}

                function calculate() {
                    var dist = parseFloat(elDist.value.replace(',', '.')) || 0;
                    var fPrice = parseFloat(elFPrice.value.replace(',', '.')) || 0;
                    var fCons = parseFloat(elFCons.value.replace(',', '.')) || 0;
                    var aRate = parseFloat(elAmort.value.replace(',', '.')) || 0;
                    var hasTrailer = elTrailer.checked;

                    var fuelCost = (dist / 100) * fCons * fPrice;
                    var amortCost = dist * aRate;
                    
                    var total = fuelCost + amortCost;
                    
                    if (hasTrailer) {
                        total = total + (total * (trailerSurchargePercent / 100));
                    }

                    outFuel.innerText = fuelCost.toFixed(2);
                    outAmort.innerText = amortCost.toFixed(2);
                    outTotal.innerText = total.toFixed(2);

                    var saveData = {
                        user_id: elUser.value,
                        user_name: elUser.options[elUser.selectedIndex] ? elUser.options[elUser.selectedIndex].text : '',
                        distance: elDist.value,
                        fuel_price: elFPrice.value,
                        fuel_cons: elFCons.value,
                        amort_rate: elAmort.value,
                        trailer: hasTrailer
                    };
                    dataInput.value = JSON.stringify(saveData);
                }

                [elUser, elDist, elFPrice, elFCons, elAmort, elTrailer].forEach(function(el) {
                    el.addEventListener('input', calculate);
                    el.addEventListener('change', calculate);
                });

                calculate();
            });
        </script>
HTML;

        return str_replace(
            ['{FIELD_NAME}', '{CURRENT_DATA}', '{USER_OPTIONS}'],
            [$field_name, $current_data, $user_options],
            $html_template
        );
    }

    public function parse($value){
        if (!$value) return 'Нет данных о поездке';
        
        $item = json_decode($value, true);
        if (!is_array($item)) return '';

        $user_name = !empty($item['user_name']) ? htmlspecialchars($item['user_name']) : 'Не указан';
        $dist = isset($item['distance']) && $item['distance'] !== '' ? (float)str_replace(',', '.', $item['distance']) : 0;
        $f_price = isset($item['fuel_price']) && $item['fuel_price'] !== '' ? (float)str_replace(',', '.', $item['fuel_price']) : 0;
        $f_cons = isset($item['fuel_cons']) && $item['fuel_cons'] !== '' ? (float)str_replace(',', '.', $item['fuel_cons']) : 0;
        $a_rate = isset($item['amort_rate']) && $item['amort_rate'] !== '' ? (float)str_replace(',', '.', $item['amort_rate']) : 0;
        $has_trailer = !empty($item['trailer']);
        $trailer_surcharge = 40; // Процент наценки за прицеп

        $fuel_cost = ($dist / 100) * $f_cons * $f_price;
        $amort_cost = $dist * $a_rate;
        $total_cost = $fuel_cost + $amort_cost;

        if ($has_trailer) {
            $total_cost += ($total_cost * ($trailer_surcharge / 100));
        }

        $html = '<div class="crm-trip-view" style="background: #fdfdfd; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        $html .= '<h4 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Детали поездки</h4>';
        
        $html .= '<ul style="list-style: none; padding: 0; margin: 0 0 15px 0; line-height: 1.8;">';
        $html .= '<li><strong>Водитель:</strong> '.$user_name.'</li>';
        $html .= '<li><strong>Пройденный путь:</strong> '.$dist.' км</li>';
        $html .= '<li><strong>Стоимость топлива:</strong> '.round($fuel_cost, 2).' € <span style="color:#777; font-size:0.9em;">(Расход: '.$f_cons.' л/100км, Цена: '.$f_price.' €/л)</span></li>';
        $html .= '<li><strong>Амортизация:</strong> '.round($amort_cost, 2).' € <span style="color:#777; font-size:0.9em;">(Ставка: '.$a_rate.' €/км)</span></li>';
        
        if ($has_trailer) {
            $html .= '<li><strong>С прицепом (Лафета):</strong> Да <span style="color:#d9534f; font-size:0.9em;">(+'.$trailer_surcharge.'% к расходам)</span></li>';
        } else {
            $html .= '<li><strong>С прицепом:</strong> Нет</li>';
        }
        $html .= '</ul>';
        
        $html .= '<div style="background: #eef9f0; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; font-size: 1.1em; color: #155724;">';
        $html .= '<strong>Итоговая стоимость поездки:</strong> ' . round($total_cost, 2) . ' €';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
