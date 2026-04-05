<?php
declare(strict_types=1);

class fieldTripcost extends cmsFormField {

    public $title = 'Калькулятор поездки';
    public $sql   = 'text';
    public $allow_index = false;

    private const TRAILER_SURCHARGE = 0.40;
    private const MAX_INPUT_LENGTH  = 10000;
    private const MAX_DISTANCE      = 100000;

    public function getOptions() {
        return [
            new fieldNumber('max_users', [
                'title'   => 'Лимит пользователей в списке, если нужно больше - есть кнопка поиска',
                'default' => 500
            ])
        ];
    }

    public function store($value, $is_submitted, $old_value = null) {
        if (!$value || !is_string($value) || strlen($value) > self::MAX_INPUT_LENGTH) { return null; }
        try {
            $data = json_decode($value, true, 5, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { return null; }
            return [
                'user_id'    => (int)($data['user_id'] ?? 0),
                'distance'   => $this->sanitizeFloat($data['distance'] ?? 0, self::MAX_DISTANCE),
                'fuel_price' => $this->sanitizeFloat($data['fuel_price'] ?? 0, 1000),
                'fuel_cons'  => $this->sanitizeFloat($data['fuel_cons'] ?? 0, 500),
                'amort_rate' => $this->sanitizeFloat($data['amort_rate'] ?? 0, 1000),
                'trailer'    => !empty($data['trailer'])
            ];
        } catch (\Throwable $e) { return null; }
    }

    public function getInput($value) {
        $users_model = cmsCore::getModel('users');
        $max_users = (int)$this->getOption('max_users', 500);
        $current_data = $this->getSafeYamlData($value);
        $users = $users_model->limit($max_users)->get('users'); 

        if (!empty($current_data['user_id'])) {
            $selected_id = (int)$current_data['user_id'];
            if (!isset($users[$selected_id])) {
                $selected_user = $users_model->getUser($selected_id);
                if ($selected_user) { $users[$selected_id] = $selected_user; }
            }
        }

        $uid = bin2hex(random_bytes(8));
        $js_data = json_encode($current_data ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);

        ob_start();
        try {
            ?>
            <div id="tc_<?php echo $uid; ?>" class="trip-calculator-wrapper mt-3">
                
                <label for="content">
                    <?php echo $this->title; ?>
                </label>

                <div class="card card-body bg-light mb-3 shadow-sm border-0">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="content">Водитель</label>
                                <button type="button" class="btn btn-light btn-sm border tc-search-toggle" style="font-size: 0.75rem; padding: 2px 8px;">
                                    🔍 Поиск
                                </button>
                            </div>
                            
                            <div class="tc-search-wrap" style="display: none;">
                                <input type="text" id="search_<?php echo $uid; ?>" class="tc-search form-control form-control-sm mb-2" 
                                       placeholder="Поиск по имени..." style="box-shadow: none;">
                            </div>

                            <select id="user_<?php echo $uid; ?>" class="tc-user input form-control">
                                <option value="">-- Выберите из списка --</option>
                                <?php foreach(($users ?: []) as $u){ ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars((string)$u['nickname'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="content">Дистанция (км)</label>
                            <input type="number" step="0.01" id="dist_<?php echo $uid; ?>" class="tc-dist input form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="content">Цена топлива (€/л)</label>
                            <input type="number" step="0.01" id="fprice_<?php echo $uid; ?>" class="tc-fprice input form-control" placeholder="1.80">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="content">Расход (л/100км)</label>
                            <input type="number" step="0.01" id="fcons_<?php echo $uid; ?>" class="tc-fcons input form-control" placeholder="8.0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="content"">Амортизация (€/км)</label>
                            <input type="number" step="0.01" id="amort_<?php echo $uid; ?>" class="tc-amort input form-control" placeholder="0.20">
                        </div>
                    </div>

                    <div class="mt-2">
                        <div class="custom-control custom-checkbox d-flex align-items-center">
                            <input type="checkbox" class="tc-trailer custom-control-input" id="trailer_<?php echo $uid; ?>" style="width: 18px; height: 18px; margin: 0;">
                            <label for="trailer_<?php echo $uid; ?>" class="custom-control-label font-weight-bold ml-2 text-primary m-0" style="cursor:pointer;">
                                Поездка с прицепом (+40% к стоимости)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info border-0 shadow-sm mb-0 py-3">
                    <div class="row text-center font-weight-bold">
                        <div class="col-md-4">Топливо: <span class="calc-fuel-cost">0.00</span> €</div>
                        <div class="col-md-4 border-left border-right">Износ: <span class="calc-amort-cost">0.00</span> €</div>
                        <div class="col-md-4 text-success font-weight-bolder">Итого: <span class="calc-total-cost">0.00</span> €</div>
                    </div>
                </div>

                <input type="hidden" name="<?php echo htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8'); ?>" class="tc-final">
            </div>

            <script>
                (function(){
                    const root = document.getElementById('tc_<?php echo $uid; ?>');
                    const UI = {
                        dist: root.querySelector('.tc-dist'),
                        fprice: root.querySelector('.tc-fprice'),
                        fcons: root.querySelector('.tc-fcons'),
                        amort: root.querySelector('.tc-amort'),
                        trailer: root.querySelector('.tc-trailer'),
                        user: root.querySelector('.tc-user'),
                        search: root.querySelector('.tc-search'),
                        searchWrap: root.querySelector('.tc-search-wrap'),
                        searchBtn: root.querySelector('.tc-search-toggle'),
                        final: root.querySelector('.tc-final'),
                        outFuel: root.querySelector('.calc-fuel-cost'),
                        outAmort: root.querySelector('.calc-amort-cost'),
                        outTotal: root.querySelector('.calc-total-cost')
                    };
                    
                    const data = <?php echo $js_data; ?>;

                    if(data.distance) {
                        UI.user.value = data.user_id || '';
                        UI.dist.value = data.distance || '';
                        UI.fprice.value = data.fuel_price || '';
                        UI.fcons.value = data.fuel_cons || '';
                        UI.amort.value = data.amort_rate || '';
                        UI.trailer.checked = !!data.trailer;
                    }

                    UI.searchBtn.onclick = (e) => {
                        e.preventDefault();
                        const isHidden = UI.searchWrap.style.display === 'none';
                        UI.searchWrap.style.display = isHidden ? 'block' : 'none';
                        UI.searchBtn.innerHTML = isHidden ? '&times; Закрыть' : '🔍 Поиск';
                        if(isHidden) UI.search.focus();
                    };

                    UI.search.oninput = () => {
                        const val = UI.search.value.toLowerCase();
                        Array.from(UI.user.options).forEach(opt => {
                            if(!opt.value) return;
                            opt.style.display = opt.text.toLowerCase().includes(val) ? '' : 'none';
                        });
                    };

                    const calculate = () => {
                        const d = parseFloat(UI.dist.value) || 0;
                        const fp = parseFloat(UI.fprice.value) || 0;
                        const fc = parseFloat(UI.fcons.value) || 0;
                        const ar = parseFloat(UI.amort.value) || 0;
                        
                        const fuel = (d / 100) * fc * fp;
                        const amort = d * ar;
                        let total = fuel + amort;
                        if(UI.trailer.checked) total *= 1.4;

                        UI.outFuel.textContent = fuel.toFixed(2);
                        UI.outAmort.textContent = amort.toFixed(2);
                        UI.outTotal.textContent = total.toFixed(2);

                        UI.final.value = JSON.stringify({
                            user_id: UI.user.value,
                            distance: UI.dist.value,
                            fuel_price: UI.fprice.value,
                            fuel_cons: UI.fcons.value,
                            amort_rate: UI.amort.value,
                            trailer: UI.trailer.checked
                        });
                    };

                    root.addEventListener('input', calculate);
                    calculate();
                })();
            </script>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) { ob_end_clean(); return ''; }
    }

    public function parse($value) {
        $data = $this->getSafeYamlData($value);
        if (!$data || empty($data['distance'])) { return ''; }

        $user = cmsCore::getModel('users')->getUser((int)$data['user_id']);
        $user_name = $user ? htmlspecialchars((string)$user['nickname'], ENT_QUOTES, 'UTF-8') : '—';

        $dist    = (float)$data['distance'];
        $f_price = (float)($data['fuel_price'] ?? 0);
        $f_cons  = (float)($data['fuel_cons'] ?? 0);
        $a_rate  = (float)($data['amort_rate'] ?? 0);
        
        $fuel_cost  = ($dist / 100) * $f_cons * $f_price;
        $amort_cost = $dist * $a_rate;
        $total_cost = ($fuel_cost + $amort_cost);
        if (!empty($data['trailer'])) { $total_cost *= 1.4; }

        ob_start();
        try {
            ?>
            <div class="field-tripcost-view crm-trip-view" style="background: #fdfdfd; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <ul style="list-style: none; padding: 0; margin: 0 0 15px 0; line-height: 1.8; color: #333;">
                    <li><strong>Водитель:</strong> <?php echo $user_name; ?></li>
                    <li><strong>Пройденный путь:</strong> <?php echo number_format($dist, 0, '.', ' '); ?> км</li>
                    <li>
                        <strong>Стоимость топлива:</strong> <?php echo number_format($fuel_cost, 2, '.', ' '); ?> € 
                        <span style="color:#777; font-size:0.9em;">(Расход: <?php echo $f_cons; ?> л/100км, Цена: <?php echo $f_price; ?> €/л)</span>
                    </li>
                    <li>
                        <strong>Амортизация:</strong> <?php echo number_format($amort_cost, 2, '.', ' '); ?> € 
                        <span style="color:#777; font-size:0.9em;">(Ставка: <?php echo $a_rate; ?> €/км)</span>
                    </li>
                    <li><strong>С прицепом:</strong> <?php echo !empty($data['trailer']) ? 'Да' : 'Нет'; ?></li>
                </ul>
                <div style="background: #eef9f0; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; font-size: 1.1em; color: #155724;">
                    <strong>Итоговая стоимость поездки:</strong> <?php echo number_format($total_cost, 2, '.', ' '); ?> €
                </div>
            </div>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) { ob_end_clean(); return ''; }
    }

    public function parseTeaser($value) {
        return $this->parse($value);
    }

    private function sanitizeFloat($val, $max): float {
        $f = (float)str_replace(',', '.', (string)$val);
        return (float)max(0, min($f, $max));
    }

    public function getSafeYamlData($value): array {
        if (empty($value)) { return []; }
        if (is_array($value)) { return $value; }
        $decoded = json_decode((string)$value, true);
        if (is_string($decoded)) { $decoded = json_decode($decoded, true); }
        if (is_array($decoded)) { return $decoded; }
        try { return cmsModel::yamlToArray($value) ?: []; } catch (\Throwable $e) { return []; }
    }
}
