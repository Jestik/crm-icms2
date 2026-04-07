<?php

class fieldPhone extends cmsFormField {

    public $title       = 'Телефон с мессенджерами';
    public $sql         = 'text NULL DEFAULT NULL'; 
    public $filter_type = 'str';
    public $var_type    = 'array'; 
    public $type        = 'phone';

    private static $assets_loaded_parse = false;
    private static $assets_loaded_input = false;

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

    public function parseTeaser($value) {
        if (empty($value)) { return ''; }

        if (!empty($this->item['is_private_item'])) {
            return '<p class="private_field_hint text-muted">' . $this->item['private_item_hint'] . '</p>';
        }

        return $this->parse($value);
    }

    public function parse($value) {
        if (is_string($value)) {
            $value = cmsModel::yamlToArray($value);
        }

        if (empty($value) || !is_array($value)) { return ''; }

        if (!self::$assets_loaded_parse) {
            cmsTemplate::getInstance()->addHead($this->getParseStyles());
            self::$assets_loaded_parse = true;
        }

        $html = '<div class="field-phone-list">';
        
        foreach ($value as $item) {
            if (empty($item['phone'])) continue;

            $phone = htmlspecialchars((string)$item['phone'], ENT_QUOTES);
            $clean_phone = preg_replace('/[^\d\+]/', '', $phone);
            
            if ($clean_phone === '') continue;

            $phone_with_plus = ($clean_phone[0] === '+') ? $clean_phone : '+' . $clean_phone;
            $wa_phone = preg_replace('/\D/', '', $clean_phone); 
            
            $person = !empty($item['person']) ? htmlspecialchars((string)$item['person'], ENT_QUOTES) : '';

            $html .= '<div class="field-phone-item">';
            
            if ($person) {
                $html .= '<div class="field-phone-person">Контактное лицо: <strong>' . $person . '</strong></div>';
            }

            $html .= '<div class="field-phone-contacts">';
            $html .= '<a href="tel:' . htmlspecialchars($phone_with_plus, ENT_QUOTES) . '" class="field-phone-number">' . $phone . '</a>';

            if (!empty($item['wa']))  $html .= $this->renderMessengerLink('wa', 'https://wa.me/' . $wa_phone, 'WhatsApp');
            if (!empty($item['tg']))  $html .= $this->renderMessengerLink('tg', 'https://t.me/' . $phone_with_plus, 'Telegram');
            if (!empty($item['vi']))  $html .= $this->renderMessengerLink('vi', 'viber://chat?number=' . $phone_with_plus, 'Viber');
            if (!empty($item['si']))  $html .= $this->renderMessengerLink('si', 'https://signal.me/#p/' . $phone_with_plus, 'Signal');
            if (!empty($item['max'])) $html .= $this->renderMessengerLink('max', 'https://max.ru/phone/' . $phone_with_plus, 'MAX');
            
            $html .= '</div></div>';
        }
        $html .= '</div>';

        return $html;
    }

    public function store($value, $is_submitted, $old_value = null) {
        if (!is_array($value)) { return null; }
        
        $result = [];
        
        foreach ($value as $item) {
            $phone  = trim(strip_tags((string)($item['phone'] ?? '')));
            $person = trim(strip_tags((string)($item['person'] ?? '')));
            
            $clean_phone = preg_replace('/[^\d\+]/', '', $phone);
            
            if (!preg_match('/^\+?[\d]{5,15}$/', $clean_phone)) {
                continue;
            }
            
            $result[] = [
                'phone'  => $clean_phone,
                'person' => mb_substr($person, 0, 100),
                'wa'     => !empty($item['wa']),
                'tg'     => !empty($item['tg']),
                'vi'     => !empty($item['vi']),
                'si'     => !empty($item['si']),
                'max'    => !empty($item['max']),
            ];
        }
        
        return $result ?: null;
    }

    public function getStringValue($value) {
        if (is_string($value)) { $value = cmsModel::yamlToArray($value); }
        if (empty($value) || !is_array($value)) return '';
        
        $phones = [];
        foreach ($value as $item) {
            if (!empty($item['phone'])) {
                $phones[] = !empty($item['person']) 
                    ? $item['person'] . ': ' . $item['phone'] 
                    : $item['phone'];
            }
        }
        
        return implode(', ', $phones);
    }

    public function applyFilter($model, $value) {
        $value = str_replace(['%', '_'], ['\%', '\_'], $value);
        return $model->filterLike($this->name, '%' . $value . '%');
    }

    public function getInput($value) {
        if (is_string($value)) { $value = cmsModel::yamlToArray($value); }
        
        $placeholder = isset($this->data['attributes']['placeholder']) 
            ? (string)$this->data['attributes']['placeholder'] 
            : (string)$this->getOption('placeholder', '');

        if (empty($value) || !is_array($value)) {
            $value = [['phone' => '', 'person' => '', 'wa' => 0, 'tg' => 0, 'vi' => 0, 'si' => 0, 'max' => 0]];
        }

        if (!self::$assets_loaded_input) {
            $template = cmsTemplate::getInstance();
            $template->addHead($this->getInputStyles());
            $template->addHead($this->getInputScript());
            self::$assets_loaded_input = true;
        }

        ob_start();
        ?>
        <div class="field-phone-wrapper" id="wrap-<?php echo $this->id; ?>" 
             data-name="<?php echo htmlspecialchars($this->name, ENT_QUOTES); ?>" 
             data-placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES); ?>">
            
            <div class="phone-items">
                <?php 
                $counter = 0;
                $messengers = ['wa' => 'WhatsApp', 'tg' => 'Telegram', 'vi' => 'Viber', 'si' => 'Signal', 'max' => 'MAX'];

                foreach ($value as $i => $item) { 
                    $safe_phone  = htmlspecialchars((string)($item['phone'] ?? ''), ENT_QUOTES);
                    $safe_person = htmlspecialchars((string)($item['person'] ?? ''), ENT_QUOTES);
                ?>
                    <div class="phone-item">
                        <div class="phone-item-row">
                            <div class="phone-item-col">
                                <input type="text" name="<?php echo $this->name; ?>[<?php echo $i; ?>][person]" value="<?php echo $safe_person; ?>" class="input form-control" placeholder="Контактное лицо (необязательно)" maxlength="100" />
                            </div>
                            <div class="phone-item-col">
                                <input type="tel" name="<?php echo $this->name; ?>[<?php echo $i; ?>][phone]" value="<?php echo $safe_phone; ?>" class="input form-control phone-input-element" placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES); ?>" maxlength="20" pattern="^\+?[0-9]{5,15}$" title="Допускаются только цифры и знак + в начале (от 5 до 15 цифр)" />
                            </div>
                            
                            <a href="#" class="field-phone-btn-remove" title="Удалить" <?php if($counter === 0) echo 'style="display:none;"'; ?>>&times;</a>
                        </div>
                        
                        <div class="messengers-block">
                            <span class="messengers-label">Доступен в:</span>
                            <?php foreach ($messengers as $key => $label) { ?>
                                <label class="messenger-checkbox">
                                    <input type="checkbox" name="<?php echo $this->name; ?>[<?php echo $i; ?>][<?php echo $key; ?>]" value="1" <?php if(!empty($item[$key])) echo 'checked'; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                <?php 
                    $counter++;
                } ?>
            </div>
            
            <button class="button field-phone-btn-add">+ Добавить номер</button>
        </div>
        <?php
        return ob_get_clean();
    }


    private function renderMessengerLink($type, $url, $title) {
        $safe_url   = htmlspecialchars($url, ENT_QUOTES);
        $safe_title = htmlspecialchars($title, ENT_QUOTES);
        
        return '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer" title="' . $safe_title . '" class="field-phone-messenger msgr-' . $type . '">' 
               . $this->getSvgIcon($type) . '<span>' . $safe_title . '</span></a>';
    }

    private function getParseStyles() {
        return '<style>
            .field-phone-list { display: flex; flex-direction: column; gap: 8px; margin-top: 5px; }
            .field-phone-item { background: #fdfdfd; padding: 8px 12px; border-radius: 6px; border: 1px solid #eaeaea; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
            .field-phone-person { font-size: 0.85em; color: #666; margin-bottom: 4px; }
            .field-phone-person strong { color: #222; font-weight: 600; }
            .field-phone-contacts { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
            .field-phone-number { font-size: 1.1em; font-weight: bold; text-decoration: none; color: #111; margin-right: 8px; letter-spacing: 0.3px; }
            .field-phone-number:hover { color: #0056b3; }
            .field-phone-messenger { display: inline-flex; align-items: center; justify-content: center; color: #fff !important; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; text-decoration: none; border: none; white-space: nowrap; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .field-phone-messenger:hover { transform: translateY(-1px); box-shadow: 0 3px 6px rgba(0,0,0,0.15); text-decoration: none; }
            .field-phone-messenger svg { display: block; margin-right: 4px; width: 12px; height: 12px; fill: currentColor; }
            .msgr-wa { background: linear-gradient(135deg, #25D366, #128C7E); }
            .msgr-tg { background: linear-gradient(135deg, #0088cc, #00aaff); }
            .msgr-vi { background: linear-gradient(135deg, #7360f2, #5946d2); }
            .msgr-si { background: linear-gradient(135deg, #3a76f0, #2c5bc0); }
            .msgr-max { background: linear-gradient(135deg, #5A3DF5, #4422dd); }
        </style>';
    }

    private function getInputStyles() {
        return '<style>
            .field-phone-wrapper { background: #fafafa; padding: 10px; border-radius: 6px; border: 1px solid #ddd; }
            .field-phone-wrapper .phone-items { display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px; }
            .field-phone-wrapper .phone-item { background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #eee; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
            .field-phone-wrapper .phone-item-row { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; }
            .field-phone-wrapper .phone-item-col { flex: 1; }
            .field-phone-wrapper .phone-item-col input { margin: 0; }
            .field-phone-wrapper .field-phone-btn-remove { color: #dc3545; font-size: 20px; text-decoration: none; line-height: 1; opacity: 0.6; transition: 0.2s; cursor: pointer; padding: 0 4px; }
            .field-phone-wrapper .field-phone-btn-remove:hover { opacity: 1; color: #a71d2a; }
            .field-phone-wrapper .messengers-block { font-size: 12px; color: #444; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; background: #f8f9fa; padding: 6px 10px; border-radius: 4px; }
            .field-phone-wrapper .messengers-label { font-weight: 600; color: #333; margin-right: 4px; }
            .field-phone-wrapper .messenger-checkbox { display: inline-flex; align-items: center; cursor: pointer; user-select: none; margin: 0; }
            .field-phone-wrapper .messenger-checkbox input { margin-right: 4px; margin-top: 0; }
            .field-phone-wrapper .field-phone-btn-add { font-size: 12px; font-weight: 500; background: #fff; border: 1px dashed #ccc; color: #555; padding: 6px 12px; border-radius: 4px; cursor: pointer; transition: all 0.2s; width: 100%; text-align: center; }
            .field-phone-wrapper .field-phone-btn-add:hover { background: #f1f1f1; border-color: #bbb; color: #333; }
        </style>';
    }

    private function getInputScript() {
        return '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (window.fieldPhoneInitialized) return;
                window.fieldPhoneInitialized = true;

                document.body.addEventListener("input", function(e) {
                    if (e.target.classList.contains("phone-input-element")) {
                        let input = e.target;
                        let val = input.value;
                        
                        let cleanVal = val.replace(/[^\d+]/g, "");
                        
                        cleanVal = cleanVal.replace(/(?!^)\+/g, "");
                        
                        if (val !== cleanVal) {
                            input.value = cleanVal;
                        }
                    }
                });

                document.body.addEventListener("click", function(e) {
                    
                    if (e.target.closest(".field-phone-btn-remove")) {
                        e.preventDefault();
                        e.target.closest(".phone-item").remove();
                        return;
                    }

                    let addBtn = e.target.closest(".field-phone-btn-add");
                    if (addBtn) {
                        e.preventDefault();
                        let wrapper = addBtn.closest(".field-phone-wrapper");
                        if (!wrapper) return;

                        let container = wrapper.querySelector(".phone-items");
                        let fieldName = wrapper.getAttribute("data-name");
                        let placeholderText = wrapper.getAttribute("data-placeholder");
                        
                        let newIndex = Date.now() + "_" + Math.floor(Math.random() * 1000);
                        
                        let html = `<div class="phone-item">
                            <div class="phone-item-row">
                                <div class="phone-item-col">
                                    <input type="text" name="${fieldName}[${newIndex}][person]" value="" class="input form-control" placeholder="Контактное лицо (необязательно)" maxlength="100" />
                                </div>
                                <div class="phone-item-col">
                                    <input type="tel" name="${fieldName}[${newIndex}][phone]" value="" class="input form-control phone-input-element" placeholder="${placeholderText}" maxlength="20" pattern="^\\\\+?[0-9]{5,15}$" title="Допускаются только цифры и знак + в начале (от 5 до 15 цифр)" />
                                </div>
                                <a href="#" class="field-phone-btn-remove" title="Удалить">&times;</a>
                            </div>
                            <div class="messengers-block">
                                <span class="messengers-label">Доступен в:</span>
                                <label class="messenger-checkbox"><input type="checkbox" name="${fieldName}[${newIndex}][wa]" value="1"> <span>WhatsApp</span></label>
                                <label class="messenger-checkbox"><input type="checkbox" name="${fieldName}[${newIndex}][tg]" value="1"> <span>Telegram</span></label>
                                <label class="messenger-checkbox"><input type="checkbox" name="${fieldName}[${newIndex}][vi]" value="1"> <span>Viber</span></label>
                                <label class="messenger-checkbox"><input type="checkbox" name="${fieldName}[${newIndex}][si]" value="1"> <span>Signal</span></label>
                                <label class="messenger-checkbox"><input type="checkbox" name="${fieldName}[${newIndex}][max]" value="1"> <span>MAX</span></label>
                            </div>
                        </div>`;
                        
                        container.insertAdjacentHTML("beforeend", html);
                    }
                });
            });
        </script>';
    }

    private function getSvgIcon($type) {
        $icons = [
            'wa' => '<svg viewBox="0 0 24 24"><path d="M12.031 0C5.385 0 0 5.385 0 12.031c0 2.128.552 4.137 1.536 5.918L.041 24l6.237-1.488A11.956 11.956 0 0012.031 24c6.646 0 12.03-5.385 12.03-12.031S18.677 0 12.031 0z"/></svg>',
            'tg' => '<svg viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.4 8.7l-1.8 8.6c-.1.6-.5.8-1 .5l-2.8-2-1.4 1.3c-.1.2-.3.3-.6.3l.2-2.8 5.2-4.7c.2-.2 0-.3-.3-.1l-6.4 4-2.8-.9c-.6-.2-.6-.6.1-.9l11.1-4.2c.5-.2 1 .1.8.9z"/></svg>',
            'vi' => '<svg viewBox="0 0 512 512"><path d="M444 49.9C431.3 38.2 379.9 0 265.3 0c-93.1 0-143.3 14.7-170.9 30.5C30.4 68.1 0 135.8 0 203.9c0 43.2 13.3 80.2 39.3 110.1 20.4 23.5 19 24.1 12.6 52.1-3.1 13.6-15.6 59.8-13.4 66.5 2.5 7.7 4.2 10.3 12.6 10.3s31.7-5.5 54.4-18.1c34.1-18.9 33.7-19.1 55.4-14.8 28.3 5.6 57.3 8.6 86.8 8.6 149.2 0 254.3-80.1 254.3-214.8-.1-74.8-22.3-125.6-51-154.7zM389.5 321.4c-11.8 11.8-31.1 12.5-43.2 1.5l-33-30.2c-10.4-9.5-12.3-24.8-4.6-36.6l15.1-23c-1.2-1.1-2.4-2.2-3.6-3.3-21.7-19.5-45.2-35.4-69.8-47.3l-13.3 19.3c-7.7 11.2-22.1 15.6-34.5 10.7l-41-16.2c-13.5-5.3-20.2-20.5-15.3-34.1l14.8-41.2c5.3-14.7 20-23.7 35.4-21.6 30.2 4.1 61.3 14.5 91 30.5 32.1 17.3 60.1 40.5 83.1 68.7 19.8 24.1 34.6 51.5 44 81.3 4.8 15.3-.4 31.9-15.1 40.5l-25.1 10z"/></svg>',
            'si' => '<svg viewBox="0 0 24 24"><path d="M12 0a12 12 0 1 0 12 12A12.013 12.013 0 0 0 12 0zm0 18a6 6 0 1 1 6-6 6.007 6.007 0 0 1-6 6zm-2-6a2 2 0 1 1 4 0 2.002 2.002 0 0 1-4 0z"/></svg>',
            'max'=> '<svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12c0 2.22.61 4.3 1.67 6.07L.2 23.8l6.06-1.55A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 16.5c-2.485 0-4.5-2.015-4.5-4.5S9.515 7.5 12 7.5s4.5 2.015 4.5 4.5-2.015 4.5-4.5 4.5z"/></svg>',
        ];
        return $icons[$type] ?? '';
    }
}
