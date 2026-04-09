<?php
class fieldSticker extends cmsFormField {

    public $title       = 'Наклейка для принтера';
    public $sql         = 'varchar(10)'; 
    public $allow_index = false;
    public $var_type    = 'string';

    public function getOptions() {
        return [
            new fieldList('size', [
                'title' => 'Формат наклейки',
                'default' => '320x240',
                'items' => [
                    '240x160' => '30 × 20 мм (240 × 160 px)',
                    '320x240' => '40 × 30 мм (320 × 240 px)',
                    '400x240' => '50 × 30 мм (400 × 240 px)',
                    '400x400' => '50 × 50 мм (400 × 400 px)'
                ]
            ]),
            new fieldString('fields_to_print', [
                'title' => 'Поля для текста и фото (через запятую)',
                'hint' => 'Например: title, recordid, price, images. Скрипт сам найдет фото и выведет его сбоку.'
            ]),
            new fieldString('qr_field', [
                'title' => 'Поле для QR-кода',
                'hint' => 'Например: url или id. Оставьте пустым, если не нужно.'
            ]),
            new fieldString('button_text', [
                'title' => 'Текст на кнопке',
                'default' => 'Показать наклейку'
            ])
        ];
    }

    public function parse($value) {
        if (empty($this->item)) { return ''; }

        $size = $this->getOption('size', '320x240');
        list($width, $height) = explode('x', $size);
        $button_text = htmlspecialchars($this->getOption('button_text', 'Показать наклейку'), ENT_QUOTES);

        $fields_str = $this->getOption('fields_to_print', '');
        $print_data = [];
        $print_image_url = '';

        if ($fields_str) {
            $f_names = array_map('trim', explode(',', $fields_str));
            foreach ($f_names as $fn) {
                if ($fn === 'recordid' || $fn === 'id') {
                    $print_data[$fn] = 'ID: ' . ($this->item['id'] ?? '');
                    continue;
                }

                if (isset($this->item[$fn])) {
                    $raw_val = $this->item[$fn];

                    if (is_string($raw_val) && strpos($raw_val, '---') === 0) {
                        $arr = cmsModel::yamlToArray($raw_val);
                        if (is_array($arr) && !empty($arr)) {
                            $first_photo = reset($arr);
                            if (is_array($first_photo)) {
                                $preset = isset($first_photo['small']) ? 'small' : key($first_photo);
                                $print_image_url = cmsConfig::get('upload_host') . '/' . $first_photo[$preset];
                            }
                        }
                    } else if (is_scalar($raw_val)) {
                        $clean = trim(strip_tags((string)$raw_val));
                        if ($clean) {
                            $print_data[$fn] = $clean;
                        }
                    }
                }
            }
        }

        $qr_field = $this->getOption('qr_field', '');
        $qr_data = '';
        if ($qr_field) {
            if ($qr_field == 'url') {
                $qr_data = href_to_abs($this->item['ctype']['name'], $this->item['slug'] . '.html');
            } else if ($qr_field == 'recordid' || $qr_field == 'id') {
                $qr_data = $this->item['id'] ?? '';
            } else if (isset($this->item[$qr_field])) {
                $qr_data = strip_tags((string)$this->item[$qr_field]);
            }
        }

        $btn_id = 'sticker_btn_' . uniqid();
        cmsTemplate::getInstance()->addJS('https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js');

        $json_print_data = json_encode($print_data, JSON_UNESCAPED_UNICODE);
        $json_qr_data = json_encode($qr_data, JSON_UNESCAPED_UNICODE);
        $json_img_url = json_encode($print_image_url, JSON_UNESCAPED_UNICODE);

        $html = <<<HTML
        <div class="sticker-field-wrap" style="margin: 15px 0;">
            <button type="button" class="button" id="{$btn_id}">
                {$button_text}
            </button>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var btn = document.getElementById('{$btn_id}');
            if (!btn) return;
            
            btn.addEventListener('click', async function() {
                var width = {$width};
                var height = {$height};
                var printData = {$json_print_data};
                var qrData = {$json_qr_data};
                var imageUrl = {$json_img_url};
                
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');

                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, width, height);

                var rightBlockSize = 0;
                var qrSize = qrData ? Math.min(80, height / 2 - 10) : 0;
                var imgSize = imageUrl ? Math.min(80, height / 2 - 10) : 0;
                if (qrSize > 0 || imgSize > 0) {
                    rightBlockSize = Math.max(qrSize, imgSize) + 15;
                }

                function loadStickerImage(url) {
                    return new Promise(function(resolve) {
                        var img = new Image();
                        img.crossOrigin = 'Anonymous';
                        img.onload = function() { resolve(img); };
                        img.onerror = function() { resolve(null); };
                        img.src = url;
                    });
                }

                if (imageUrl) {
                    var loadedImg = await loadStickerImage(imageUrl);
                    if (loadedImg) {
                        ctx.drawImage(loadedImg, width - imgSize - 10, 10, imgSize, imgSize);
                    }
                }

                ctx.fillStyle = '#000000';
                ctx.textBaseline = 'top';
                var currentY = 15;
                var paddingX = 15;
                var maxTextWidth = width - paddingX * 2 - rightBlockSize;

                function wrapText(context, text, x, y, maxWidth, lineHeight) {
                    var words = String(text).split(' ');
                    var line = '';
                    for(var n = 0; n < words.length; n++) {
                        var testLine = line + words[n] + ' ';
                        var metrics = context.measureText(testLine);
                        if (metrics.width > maxWidth && n > 0) {
                            context.fillText(line, x, y);
                            line = words[n] + ' ';
                            y += lineHeight;
                        } else {
                            line = testLine;
                        }
                    }
                    context.fillText(line, x, y);
                    return y + lineHeight;
                }

                var isFirst = true;
                for (var key in printData) {
                    var text = printData[key];
                    if (!text) continue;

                    if (isFirst) {
                        ctx.font = 'bold 18px Arial Black, sans-serif';
                        currentY = wrapText(ctx, text, paddingX, currentY, maxTextWidth, 22);
                        currentY += 10;
                        isFirst = false;
                    } else {
                        ctx.font = 'bold 14px Courier New, monospace';
                        currentY = wrapText(ctx, text, paddingX, currentY, maxTextWidth, 18);
                    }
                }

                if (qrData && typeof QRious !== 'undefined') {
                    var qrCanvas = document.createElement('canvas');
                    new QRious({ element: qrCanvas, value: String(qrData), size: qrSize, level: 'M' });
                    ctx.drawImage(qrCanvas, width - qrSize - 10, height - qrSize - 10);
                }

                var dataUrl = canvas.toDataURL('image/png');
                
                var overlay = document.createElement('div');
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.backgroundColor = 'rgba(0,0,0,0.8)';
                overlay.style.zIndex = '99999';
                overlay.style.display = 'flex';
                overlay.style.flexDirection = 'column';
                overlay.style.justifyContent = 'center';
                overlay.style.alignItems = 'center';
                overlay.style.backdropFilter = 'blur(3px)';
                
                var img = document.createElement('img');
                img.src = dataUrl;
                img.style.border = '3px solid white';
                img.style.borderRadius = '4px';
                img.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)';
                img.style.maxWidth = '90%';
                img.style.maxHeight = '70%';
                img.style.imageRendering = 'pixelated'; // Чтобы пиксели не мылились
                
                var downloadBtn = document.createElement('a');
                downloadBtn.href = dataUrl;
                downloadBtn.download = 'sticker_' + Math.floor(Date.now() / 1000) + '.png';
                downloadBtn.innerHTML = 'Скачать картинку (.png)';
                downloadBtn.style.marginTop = '20px';
                downloadBtn.style.padding = '10px 20px';
                downloadBtn.style.backgroundColor = '#4CAF50';
                downloadBtn.style.color = 'white';
                downloadBtn.style.textDecoration = 'none';
                downloadBtn.style.borderRadius = '3px';
                downloadBtn.style.fontFamily = 'sans-serif';
                downloadBtn.style.fontWeight = 'bold';
                
                var hintText = document.createElement('div');
                hintText.innerHTML = 'Кликните на темный фон, чтобы закрыть';
                hintText.style.color = '#ccc';
                hintText.style.marginTop = '15px';
                hintText.style.fontFamily = 'sans-serif';
                hintText.style.fontSize = '12px';
                
                overlay.appendChild(img);
                overlay.appendChild(downloadBtn);
                overlay.appendChild(hintText);
                
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay || e.target === hintText) {
                        document.body.removeChild(overlay);
                    }
                });
                
                document.body.appendChild(overlay);
            });
        });
        </script>
HTML;

        return $html;
    }

    public function getInput($value) {
        return ;
    }
}
