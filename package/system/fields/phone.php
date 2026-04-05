<?php
declare(strict_types=1);

class fieldPhone extends cmsFormField {

    public $title       = 'Телефон с мессенджерами';
    public $sql         = 'text NULL DEFAULT NULL';
    public $filter_type = 'str';
    public $var_type    = 'array'; 
    public $type        = 'phone';

    public function getOptions() {
        return [
            new fieldString('placeholder', [
                'title' => LANG_PARSER_PLACEHOLDER,
                'can_multilanguage' => true
            ])
        ];
    }

    public function getRules() {
        return $this->rules;
    }

    private function getSafeYamlData($value): array {
        if (is_array($value)) return $value;
        $val_str = (string)$value;
        if ($val_str === '' || $val_str === '[]') return [];
        try {
            $data = cmsModel::yamlToArray($val_str);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function parseTeaser($value) {
        if (empty($value)) { 
            return ''; 
        }

        if (!empty($this->item['is_private_item']) && isset($this->item['private_item_hint'])) {
            $hint_esc = htmlspecialchars((string)$this->item['private_item_hint'], ENT_QUOTES, 'UTF-8');
            return '<p class="private_field_hint text-muted">' . $hint_esc . '</p>';
        }

        return $this->parse($value);
    }

    public function parse($value) {
        $files = $this->getSafeYamlData($value);
        if (empty($files)) { 
            return ''; 
        }

        $btn_style = "display: inline-flex; align-items: center; justify-content: center; color: #fff; padding: 0 10px; border-radius: 4px; font-size: 12px; text-decoration: none; margin-right: 8px; height: 24px; line-height: 24px; border: none; white-space: nowrap;";

        $svg_wa = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:block; margin-right: 6px;"><path d="M12.031 0C5.385 0 0 5.385 0 12.031c0 2.128.552 4.137 1.536 5.918L.041 24l6.237-1.488A11.956 11.956 0 0012.031 24c6.646 0 12.03-5.385 12.03-12.031S18.677 0 12.031 0z"/></svg>';
        $svg_tg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:block; margin-right: 6px;"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.4 8.7l-1.8 8.6c-.1.6-.5.8-1 .5l-2.8-2-1.4 1.3c-.1.2-.3.3-.6.3l.2-2.8 5.2-4.7c.2-.2 0-.3-.1l-6.4 4-2.8-.9c-.6-.2-.6-.6.1-.9l11.1-4.2c.5-.2 1 .1.8.9z"/></svg>';
        $svg_vi = '<svg width="14" height="14" viewBox="0 0 512 512" fill="currentColor" style="display:block; margin-right: 6px;"><path d="M444 49.9C431.3 38.2 379.9 0 265.3 0c-93.1 0-143.3 14.7-170.9 30.5C30.4 68.1 0 135.8 0 203.9c0 43.2 13.3 80.2 39.3 110.1 20.4 23.5 19 24.1 12.6 52.1-3.1 13.6-15.6 59.8-13.4 66.5 2.5 7.7 4.2 10.3 12.6 10.3s31.7-5.5 54.4-18.1c34.1-18.9 33.7-19.1 55.4-14.8 28.3 5.6 57.3 8.6 86.8 8.6 149.2 0 254.3-80.1 254.3-214.8-.1-74.8-22.3-125.6-51-154.7zM389.5 321.4c-11.8 11.8-31.1 12.5-43.2 1.5l-33-30.2c-10.4-9.5-12.3-24.8-4.6-36.6l15.1-23c-1.2-1.1-2.4-2.2-3.6-3.3-21.7-19.5-45.2-35.4-69.8-47.3l-13.3 19.3c-7.7 11.2-22.1 15.6-34.5 10.7l-41-16.2c-13.5-5.3-20.2-20.5-15.3-34.1l14.8-41.2c5.3-14.7 20-23.7 35.4-21.6 30.2 4.1 61.3 14.5 91 30.5 32.1 17.3 60.1 40.5 83.1 68.7 19.8 24.1 34.6 51.5 44 81.3 4.8 15.3-.4 31.9-15.1 40.5l-25.1 10z"/></svg>';
        $svg_si = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:block; margin-right: 6px;"><path d="M12 0a12 12 0 1 0 12 12A12.013 12.013 0 0 0 12 0zm0 18a6 6 0 1 1 6-6 6.007 6.007 0 0 1-6 6zm-2-6a2 2 0 1 1 4 0 2.002 2.002 0 0 1-4 0z"/></svg>';
        $svg_max = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:block; margin-right: 6px;"><path d="M12 0C5.373 0 0 5.373 0 12c0 2.22.61 4.3 1.67 6.07L.2 23.8l6.06-1.55A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 16.5c-2.485 0-4.5-2.015-4.5-4.5S9.515 7.5 12 7.5s4.5 2.015 4.5 4.5-2.015 4.5-4.5 4.5z"/></svg>';

        $html = '<div class="field-phone-list" style="margin-top:5px;">';
        
        foreach ($files as $item) {
            if (!is_array($item) || empty($item['phone'])) continue;

            $phone_raw = (string)$item['phone'];
            $clean_phone = preg_replace('/[^\d\+]/', '', $phone_raw);
            
            if (empty($clean_phone)) continue;

            $phone_esc = htmlspecialchars($phone_raw, ENT_QUOTES, 'UTF-8');
            
            $phone_with_plus = ($clean_phone[0] === '+') ? $clean_phone : '+' . $clean_phone;
            $phone_no_plus = str_replace('+', '', $phone_with_plus);

            $phone_with_plus_esc = htmlspecialchars($phone_with_plus, ENT_QUOTES, 'UTF-8');
            $phone_no_plus_esc   = htmlspecialchars($phone_no_plus, ENT_QUOTES, 'UTF-8');

            $html .= '<div style="display: flex; align-items: center; flex-wrap: wrap; margin-bottom: 12px;">';
            $html .= '<a href="tel:' . $phone_with_plus_esc . '" style="font-size: 1.15em; font-weight: bold; text-decoration: none; margin-right: 15px; color: inherit; line-height: 24px;">' . $phone_esc . '</a>';

            if (!empty($item['wa'])) {
                $html .= '<a href="https://wa.me/' . $phone_no_plus_esc . '" target="_blank" rel="noopener noreferrer" title="WhatsApp" style="'.$btn_style.' background: #25D366;">' . $svg_wa . '<span style="display:inline-block; margin-top:-1px;">WhatsApp</span></a>';
            }
            if (!empty($item['tg'])) {
                $html .= '<a href="https://t.me/' . $phone_with_plus_esc . '" target="_blank" rel="noopener noreferrer" title="Telegram" style="'.$btn_style.' background: #0088cc;">' . $svg_tg . '<span style="display:inline-block; margin-top:-1px;">Telegram</span></a>';
            }
            if (!empty($item['vi'])) {
                $html .= '<a href="viber://chat?number=' . $phone_with_plus_esc . '" target="_blank" rel="noopener noreferrer" title="Viber" style="'.$btn_style.' background: #7360f2;">' . $svg_vi . '<span style="display:inline-block; margin-top:-1px;">Viber</span></a>';
            }
            if (!empty($item['si'])) {
                $html .= '<a href="https://signal.me/#p/' . $phone_with_plus_esc . '" target="_blank" rel="noopener noreferrer" title="Signal" style="'.$btn_style.' background: #3a76f0;">' . $svg_si . '<span style="display:inline-block; margin-top:-1px;">Signal</span></a>';
            }
            if (!empty($item['max'])) {
                $html .= '<a href="https://max.ru/phone/' . $phone_with_plus_esc . '" target="_blank" rel="noopener noreferrer" title="MAX" style="'.$btn_style.' background: #5A3DF5;">' . $svg_max . '<span style="display:inline-block; margin-top:-1px;">MAX</span></a>';
            }
            
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public function store($value, $is_submitted, $old_value = null) {
        if (!is_array($value)) { 
            return null; 
        }
        
        if (count($value) > 30) {
            $value = array_slice($value, 0, 30, true);
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) continue;

            $phone = trim(strip_tags((string)($item['phone'] ?? '')));
            $phone = mb_substr($phone, 0, 50); 
            
            if ($phone === '') continue;
            
            $result[] = [
                'phone' => $phone,
                'wa'    => !empty($item['wa']),
                'tg'    => !empty($item['tg']),
                'vi'    => !empty($item['vi']),
                'si'    => !empty($item['si']),
                'max'   => !empty($item['max']),
            ];
        }
        return empty($result) ? null : $result;
    }

    public function getStringValue($value) {
        $files = $this->getSafeYamlData($value);
        if (empty($files)) return '';
        
        $phones = [];
        foreach ($files as $item) {
            if (is_array($item) && !empty($item['phone'])) { 
                $phones[] = htmlspecialchars((string)$item['phone'], ENT_QUOTES, 'UTF-8'); 
            }
        }
        return implode(', ', $phones);
    }

    public function applyFilter($model, $value) {
        $safe_value = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], (string)$value);
        return $model->filterLike($this->name, '%' . $safe_value . '%');
    }

    public function getInput($value) {
        $files = $this->getSafeYamlData($value);
        
        $placeholder = isset($this->data['attributes']['placeholder']) 
            ? (string)$this->data['attributes']['placeholder'] 
            : (string)$this->getOption('placeholder', '');

        if (empty($files)) {
            $files = [['phone' => '', 'wa' => 0, 'tg' => 0, 'vi' => 0, 'si' => 0, 'max' => 0]];
        }

        $uid = bin2hex(random_bytes(8));
        $name_esc = htmlspecialchars((string)$this->name, ENT_QUOTES, 'UTF-8');

        ob_start();
        try {
            ?>
            <div class="field-phone-wrapper" id="wrap_<?php echo $uid; ?>">
                <div class="phone-items">
                    <?php 
                    $counter = 0;
                    foreach ($files as $i => $item) { 
                        if (!is_array($item)) continue;
                        $safe_phone = htmlspecialchars((string)($item['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="phone-item" style="padding-bottom: 15px; <?php if($counter > 0) echo 'border-bottom: 1px dashed #e4e4e4;'; ?>">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <input type="text"
                                       name="<?php echo $name_esc; ?>[<?php echo $i; ?>][phone]"
                                       value="<?php echo $safe_phone; ?>"
                                       class="input form-control phone-input-element"
                                       style="width: 100%;"
                                       maxlength="50"
                                       placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>" />
                                
                                <?php if ($counter > 0) { ?>
                                    <a href="#" class="btn-remove-phone" style="color: #dc3545; font-size: 22px; margin-left: 12px; text-decoration: none; line-height: 1; opacity: 0.7;" title="Удалить номер">&times;</a>
                                <?php } ?>
                            </div>
                            
                            <div class="messengers-block" style="font-size: 13px; color: #555;">
                                <span style="margin-right: 15px; font-weight: bold;">Доступен в:</span>
                                <label style="margin-right:12px; cursor:pointer;"><input type="checkbox" name="<?php echo $name_esc; ?>[<?php echo $i; ?>][wa]" value="1" <?php if(!empty($item['wa'])) echo 'checked'; ?>> WhatsApp</label>
                                <label style="margin-right:12px; cursor:pointer;"><input type="checkbox" name="<?php echo $name_esc; ?>[<?php echo $i; ?>][tg]" value="1" <?php if(!empty($item['tg'])) echo 'checked'; ?>> Telegram</label>
                                <label style="margin-right:12px; cursor:pointer;"><input type="checkbox" name="<?php echo $name_esc; ?>[<?php echo $i; ?>][vi]" value="1" <?php if(!empty($item['vi'])) echo 'checked'; ?>> Viber</label>
                                <label style="margin-right:12px; cursor:pointer;"><input type="checkbox" name="<?php echo $name_esc; ?>[<?php echo $i; ?>][si]" value="1" <?php if(!empty($item['si'])) echo 'checked'; ?>> Signal</label>
                                <label style="cursor:pointer;"><input type="checkbox" name="<?php echo $name_esc; ?>[<?php echo $i; ?>][max]" value="1" <?php if(!empty($item['max'])) echo 'checked'; ?>> MAX</label>
                            </div>
                        </div>
                    <?php 
                        $counter++;
                    } ?>
                </div>
                <a href="#" class="button btn-add-phone" style="font-size: 12px; padding: 4px 10px; text-decoration: none;">+ Добавить еще один номер</a>
            </div>

            <script>
                (function() {
                    const uid = <?php echo json_encode($uid, JSON_THROW_ON_ERROR); ?>;
                    const fieldName = <?php echo json_encode($this->name, JSON_THROW_ON_ERROR); ?>;
                    const placeholderText = <?php echo json_encode($placeholder, JSON_THROW_ON_ERROR); ?>;
                    
                    const wrapper = document.getElementById('wrap_' + uid);
                    if (!wrapper) return;
                    
                    const container = wrapper.querySelector('.phone-items');
                    const btnAdd = wrapper.querySelector('.btn-add-phone');

                    container.addEventListener('mouseover', function(e) {
                        if (e.target.classList.contains('btn-remove-phone')) {
                            e.target.style.opacity = '1';
                        }
                    });
                    
                    container.addEventListener('mouseout', function(e) {
                        if (e.target.classList.contains('btn-remove-phone')) {
                            e.target.style.opacity = '0.7';
                        }
                    });

                    container.addEventListener('click', function(e) {
                        if (e.target.classList.contains('btn-remove-phone')) {
                            e.preventDefault();
                            e.target.closest('.phone-item').remove();
                        }
                    });

                    btnAdd.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const ts = Date.now();
                        const li = document.createElement('div');
                        li.className = 'phone-item';
                        li.style.cssText = 'margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #e4e4e4;';

                        const row = document.createElement('div');
                        row.style.cssText = 'display: flex; align-items: center; margin-bottom: 8px;';

                        const input = document.createElement('input');
                        input.type = 'text';
                        input.name = fieldName + '[' + ts + '][phone]';
                        input.className = 'input form-control phone-input-element';
                        input.style.width = '100%';
                        input.maxLength = 50;
                        input.placeholder = placeholderText;
                        row.appendChild(input);

                        const delBtn = document.createElement('a');
                        delBtn.href = '#';
                        delBtn.className = 'btn-remove-phone';
                        delBtn.style.cssText = 'color: #dc3545; font-size: 22px; margin-left: 12px; text-decoration: none; line-height: 1; opacity: 0.7;';
                        delBtn.title = 'Удалить номер';
                        delBtn.textContent = '\u00D7'; 
                        row.appendChild(delBtn);

                        li.appendChild(row);

                        const messengers = document.createElement('div');
                        messengers.className = 'messengers-block';
                        messengers.style.cssText = 'font-size: 13px; color: #555;';

                        const labelSpan = document.createElement('span');
                        labelSpan.style.cssText = 'margin-right: 15px; font-weight: bold;';
                        labelSpan.textContent = 'Доступен в:';
                        messengers.appendChild(labelSpan);

                        const mList = [
                            { key: 'wa', label: 'WhatsApp' },
                            { key: 'tg', label: 'Telegram' },
                            { key: 'vi', label: 'Viber' },
                            { key: 'si', label: 'Signal' },
                            { key: 'max', label: 'MAX' }
                        ];

                        mList.forEach(function(m) {
                            const lbl = document.createElement('label');
                            lbl.style.cssText = 'margin-right:12px; cursor:pointer;';

                            const chk = document.createElement('input');
                            chk.type = 'checkbox';
                            chk.name = fieldName + '[' + ts + '][' + m.key + ']';
                            chk.value = '1';

                            lbl.appendChild(chk);
                            lbl.appendChild(document.createTextNode(' ' + m.label));
                            messengers.appendChild(lbl);
                        });

                        li.appendChild(messengers);
                        container.appendChild(li);
                    });
                })();
            </script>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldPhone::getInput render error: ' . $e->getMessage());
            return '';
        }
    }
}
