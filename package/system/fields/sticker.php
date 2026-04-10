<?php
class fieldSticker extends cmsFormField {

    public $title       = 'Наклейка (Конструктор)';
    public $sql         = 'varchar(10)'; 
    public $allow_index = false;

    public function getOptions() {
        $options = [
            new fieldList('size', [
                'title' => 'Формат наклейки (203 DPI)',
                'default' => '320x240',
                'items' => [
                    '240x160' => '30 × 20 мм (240 × 160 px)',
                    '320x240' => '40 × 30 мм (320 × 240 px)',
                    '400x240' => '50 × 30 мм (400 × 240 px)',
                    '400x400' => '50 × 50 мм (400 × 400 px)'
                ]
            ]),
            new fieldList('font_family', [
                'title' => 'Шрифт (Sans-serif)',
                'default' => 'Arial Black',
                'items' => [
                    'Arial Black' => 'Arial Black (Массивный)',
                    'Roboto' => 'Roboto (Bold)',
                    'Courier New' => 'Courier New (Моноширинный)',
                    'Montserrat' => 'Montserrat (Bold)'
                ]
            ]),
            new fieldString('qr_field', [
                'title' => 'Поле для QR-кода',
                'hint' => 'Системное имя поля (например: url или article)'
            ]),
            new fieldNumber('qr_size', [
                'title' => 'Размер QR-кода (px)',
                'hint' => 'Оставьте пустым для авто-расчета размера',
                'default' => ''
            ]),
            new fieldString('button_text', [
                'title' => 'Текст на кнопке',
                'default' => 'Создать наклейку'
            ])
        ];

        for ($i = 1; $i <= 6; $i++) {
            $options[] = new fieldString("f{$i}_name", [
                'title' => "<b style='color:#34495e;'>--- СТРОКА #{$i} ---</b><br>Системное имя поля", 
                'hint'  => 'Например: title',
            ]);
            $options[] = new fieldNumber("f{$i}_size", [
                'title' => "Размер шрифта (pt) [Поле {$i}]", 
                'default' => ($i==1 ? 16 : 11)
            ]);
            $options[] = new fieldList("f{$i}_flow", [
                'title' => "Обтекание [Поле {$i}]",
                'default' => 'next',
                'items' => [
                    'next'  => 'На новую строку',
                    'right' => 'В ту же строку (справа)'
                ]
            ]);
        }

        return $options;
    }

    public function parse($value) {
        if (empty($this->item)) { return ''; }

        $size = $this->getOption('size', '320x240');
        $parts = explode('x', $size);
        $width  = (int)($parts[0] ?? 320);
        $height = (int)($parts[1] ?? 240);
        
        $font_family = $this->getOption('font_family', 'Arial Black');
        $btn_text = htmlspecialchars($this->getOption('button_text', 'Создать наклейку'), ENT_QUOTES);

        $layers = [];
        for ($i = 1; $i <= 6; $i++) {
            $fname = trim((string)$this->getOption("f{$i}_name"));
            if (!$fname) continue;

            $val = '';
            if ($fname === 'recordid') { 
                $val = 'ID: ' . ($this->item['id'] ?? ''); 
            } else { 
                $val = strip_tags((string)($this->item[$fname] ?? '')); 
            }
            
            if (!$val) continue;

            $layers[] = [
                'text' => $val,
                'size' => (int)$this->getOption("f{$i}_size", 12),
                'flow' => $this->getOption("f{$i}_flow", 'next')
            ];
        }

        $qr_f = trim((string)$this->getOption('qr_field'));
        $qr_val = '';
        if ($qr_f) {
            if ($qr_f === 'url') {
                $qr_val = function_exists('href_to_abs') ? href_to_abs($this->item['ctype']['name'], $this->item['slug'].'.html') : '';
            } else {
                $qr_val = strip_tags((string)($this->item[$qr_f] ?? ''));
            }
        }
        
        $qr_val = mb_substr($qr_val, 0, 500);
        $qr_custom_size = (int)$this->getOption('qr_size', 0);

        $json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
        $btn_id = 'st_' . bin2hex(random_bytes(6));
        $item_id = $this->item['id'] ?? time();
        
        cmsTemplate::getInstance()->addCSS('https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Roboto:wght@700&display=swap');
        cmsTemplate::getInstance()->addJS('https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js');

        ob_start(); 
        
        if (!defined('STICKER_CSS_LOADED')) { 
            define('STICKER_CSS_LOADED', true); 
        ?>
        <style>
            .sticker-btn-icon { margin-right: 8px; width: 16px; height: 16px; fill: currentColor; vertical-align: text-bottom; }
            .sticker-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background-color: rgba(0,0,0,0.85); z-index: 100000; display: flex;
                flex-direction: column; justify-content: center; align-items: center; backdrop-filter: blur(4px);
            }
        </style>
        <?php } ?>

        <button type="button" class="btn btn-outline-primary" id="<?php echo $btn_id; ?>">
            <svg class="sticker-btn-icon" viewBox="0 0 512 512"><path d="M448 192V77.25c0-8.49-3.37-16.62-9.37-22.63L393.37 9.37c-6-6-14.14-9.37-22.63-9.37H96C78.33 0 64 14.33 64 32v160c-35.35 0-64 28.65-64 64v128c0 35.35 28.65 64 64 64h384c35.35 0 64-28.65 64-64V256c0-35.35-28.65-64-64-64zm-320-128h224v64H128V64zm320 320H64V256h384v128zM128 448h256v64H128z"/></svg>
            <?php echo $btn_text; ?>
        </button>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var btn = document.getElementById('<?php echo $btn_id; ?>');
            if (!btn) return;

            btn.onclick = function() {
                if (document.getElementById('sticker-overlay')) return;

                document.fonts.ready.then(function() {
                    var canvas = document.createElement('canvas');
                    
                    var scale = 2;
                    var baseW = <?php echo $width; ?>;
                    var baseH = <?php echo $height; ?>;
                    
                    canvas.width = baseW * scale; 
                    canvas.height = baseH * scale;
                    var ctx = canvas.getContext('2d');
                    
                    ctx.scale(scale, scale);
                    ctx.imageSmoothingEnabled = false; // Отключаем размытие

                    ctx.fillStyle = '#FFFFFF'; 
                    ctx.fillRect(0, 0, baseW, baseH);
                    ctx.fillStyle = '#000000'; 
                    ctx.textBaseline = 'top';

                    var curX = 15; 
                    var curY = 15; 
                    var rowMaxHeight = 0;
                    
                    var layers = <?php echo json_encode($layers, $json_flags); ?>;
                    var fontName = <?php echo json_encode($font_family, $json_flags); ?>;

                    layers.forEach(function(l) {
                        ctx.font = 'bold ' + l.size + 'pt "' + fontName + '", sans-serif';
                        var metrics = ctx.measureText(l.text);
                        var tWidth = metrics.width;
                        var tHeight = l.size * 1.4;
                        
                        var maxWidth = baseW - curX - 15;
                        if (maxWidth > 10) {
                            ctx.fillText(l.text, Math.floor(curX), Math.floor(curY), Math.floor(maxWidth));
                        }

                        if (l.flow === 'next') {
                            curY += Math.max(tHeight, rowMaxHeight) + 5;
                            curX = 15; 
                            rowMaxHeight = 0;
                        } else {
                            curX += Math.min(tWidth, maxWidth) + 15;
                            rowMaxHeight = Math.max(rowMaxHeight, tHeight);
                        }
                    });

                    var qrVal = <?php echo json_encode($qr_val, $json_flags); ?>;
                    var customQrSize = <?php echo $qr_custom_size; ?>;
                    
                    if (qrVal && typeof QRious !== 'undefined') {
                        var qrSizeLog = customQrSize > 0 
                            ? Math.min(customQrSize, baseW - 20, baseH - 20) 
                            : Math.min(85, Math.floor(baseH / 2));
                            
                        var qrTemp = document.createElement('canvas');
                        new QRious({element: qrTemp, value: qrVal, size: qrSizeLog * scale, level: 'M'});
                        
                        ctx.drawImage(qrTemp, Math.floor(baseW - qrSizeLog - 10), Math.floor(baseH - qrSizeLog - 10), qrSizeLog, qrSizeLog);
                    }

                    var dataUrl = canvas.toDataURL('image/png');
                    var overlay = document.createElement('div');
                    overlay.id = 'sticker-overlay'; 
                    overlay.className = 'sticker-overlay';
                    
                    var imgId = 'st_img_' + Math.round(Math.random()*1000000);
                    var linkId = 'st_link_' + Math.round(Math.random()*1000000);

                    overlay.innerHTML = 
                        '<div style="background:#fff; padding:10px; border-radius:4px; box-shadow:0 15px 35px rgba(0,0,0,0.5)">' +
                            '<img id="' + imgId + '" style="display:block; border:1px solid #ddd; width:'+baseW+'px; height:'+baseH+'px">' +
                        '</div>' +
                        '<div style="margin-top:25px; display:flex; gap:15px">' +
                            '<a id="' + linkId + '" download="sticker_<?php echo $item_id; ?>.png" class="btn btn-success">Скачать PNG</a>' +
                            '<button type="button" class="btn btn-danger close-st-btn">Закрыть</button>' +
                        '</div>';
                        
                    document.body.appendChild(overlay);

                    document.getElementById(imgId).src = dataUrl;
                    document.getElementById(linkId).href = dataUrl;

                    var closeFn = function() {
                        if (document.body.contains(overlay)) {
                            document.body.removeChild(overlay);
                        }
                        document.removeEventListener('keydown', keyHandler);
                    };

                    var keyHandler = function(e) {
                        if (e.key === 'Escape') closeFn();
                    };
                    document.addEventListener('keydown', keyHandler);

                    overlay.querySelector('.close-st-btn').onclick = closeFn;
                    overlay.onclick = function(e) {
                        if (e.target === overlay) closeFn();
                    };
                });
            };
        });
        </script>
        <?php 
        $html = ob_get_clean();
        return $html !== false ? $html : '';
    }

    public function getInput($value) { return ; }
}
