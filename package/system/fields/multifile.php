<?php
declare(strict_types=1);

class fieldMultifile extends fieldFile {

    public $title = 'Мультифайл (список файлов)';
    public $sql   = 'text';
    public $allow_index = false;

    public function getInputType() {
        return 'file'; 
    }

    public function getOptions() {
        $max_size = (int)(files_convert_bytes(ini_get('post_max_size')) / 1048576);
        return [
            new fieldString('extensions', [
                'title'   => LANG_PARSER_FILE_EXTS,
                'hint'    => 'Без пробелов. Например: pdf,doc,docx,xls,xlsx,txt,zip,rar,png,jpg',
                'default' => 'pdf,doc,docx,xls,xlsx,txt,zip,rar,png,jpg'
            ]),
            new fieldNumber('max_size_mb', [
                'title'   => LANG_PARSER_FILE_MAX_SIZE,
                'hint'    => sprintf(LANG_PARSER_FILE_MAX_SIZE_PHP, $max_size),
                'default' => 10
            ]),
            new fieldCheckbox('allow_view', [
                'title'   => 'Показывать кнопку "Смотреть"',
                'hint'    => 'Для форматов, поддерживаемых браузером (pdf, txt, картинки)',
                'default' => true
            ]),
            new fieldCheckbox('allow_download', [
                'title'   => 'Разрешить скачивание файлов',
                'hint'    => 'Показывать кнопку "Скачать"',
                'default' => true
            ]),
            new fieldCheckbox('allow_zip', [
                'title'   => 'Разрешить скачивание всех файлов одним архивом',
                'hint'    => 'Появится кнопка "Скачать всё (ZIP)"',
                'default' => true
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

    public function parse($value) {
        $files = $this->getSafeYamlData($value);
        if (empty($files)) {
            return '';
        }

        $config = cmsConfig::getInstance();
        $upload_host    = $config->upload_host;
        $upload_path    = realpath($config->upload_path);
        
        if ($upload_path === false) {
            return ''; 
        }

        $allow_view     = (bool)$this->getOption('allow_view');
        $allow_download = (bool)$this->getOption('allow_download');
        $allow_zip      = (bool)$this->getOption('allow_zip');
        
        $item_id = $this->item['id'] ?? 0;
        $secret_key = $config->db_pass . ':' . $config->db_base . ':' . $config->root;
        
        try {
            $files_json = json_encode($files, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '';
        }
        
        $zip_hash = hash_hmac('sha256', $this->name . ':' . $files_json . ':' . $item_id, $secret_key); 

        if ($allow_zip && isset($_GET['download_zip'], $_GET['hash']) && hash_equals($zip_hash, (string)$_GET['hash'])) {
            
            if (!cmsUser::isLogged()) {
                cmsCore::error404();
            }

            $zip_name = 'archive_' . date('Y-m-d_H-i-s') . '.zip';
            $zip_tmp_path = $upload_path . DIRECTORY_SEPARATOR . 'archive_' . bin2hex(random_bytes(16)) . '.zip';
            
            register_shutdown_function(static function() use ($zip_tmp_path) {
                if (file_exists($zip_tmp_path)) {
                    if (!unlink($zip_tmp_path)) {
                        error_log('fieldMultifile: не удалось удалить временный архив ZIP: ' . $zip_tmp_path);
                    }
                }
            });
            
            $zip = new ZipArchive();
            if ($zip->open($zip_tmp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                
                foreach ($files as $file) {
                    if (empty($file['path'])) continue;
                    
                    $file_real_path = realpath($upload_path . DIRECTORY_SEPARATOR . ltrim((string)$file['path'], '/\\'));
                    
                    if ($file_real_path !== false && str_starts_with($file_real_path, $upload_path) && is_file($file_real_path)) {
                        $ext = pathinfo($file_real_path, PATHINFO_EXTENSION);
                        $name = !empty($file['custom_name']) ? (string)$file['custom_name'] : (string)$file['name'];
                        
                        $name = str_replace(['/', '\\', "\0"], '_', $name); 

                        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== strtolower($ext)) {
                            $name .= '.' . $ext;
                        }
                        $zip->addFile($file_real_path, $name);
                    }
                }
                $zip->close();
                
                while (ob_get_level()) { ob_end_clean(); }
                
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                header('Content-Length: ' . filesize($zip_tmp_path));
                header('Pragma: no-cache');
                
                readfile($zip_tmp_path);
                
                if (file_exists($zip_tmp_path) && !unlink($zip_tmp_path)) {
                    error_log('fieldMultifile: не удалось удалить временный архив после отдачи: ' . $zip_tmp_path);
                }
                exit;
            }
        }

        ob_start();
        try {
            ?>
            <style>
                .multifile-list { list-style: none; padding: 0; margin: 15px 0; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
                .multifile-item { display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; background: #fff; transition: background 0.2s; }
                .multifile-item:last-child { border-bottom: none; }
                .multifile-item:hover { background: #f8fafc; }
                .multifile-icon { margin-right: 12px; color: #64748b; display: flex; align-items: center; justify-content: center; }
                .multifile-info { flex-grow: 1; min-width: 0; }
                .multifile-name { font-weight: 500; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
                .multifile-meta { font-size: 12px; color: #94a3b8; margin-top: 2px; display: block; }
                .multifile-actions { display: flex; gap: 8px; margin-left: 15px; flex-shrink: 0; }
                .multifile-btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; text-decoration: none; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
                .multifile-btn-view { background: #e0f2fe; color: #0284c7; }
                .multifile-btn-view:hover { background: #bae6fd; color: #0369a1; text-decoration: none; }
                .multifile-btn-download { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
                .multifile-btn-download:hover { background: #e2e8f0; color: #334155; text-decoration: none; }
                .multifile-zip-btn { display: inline-block; margin-top: 10px; padding: 8px 16px; background: #3b82f6; color: #fff; border-radius: 4px; font-weight: 500; text-decoration: none; font-size: 14px; transition: background 0.2s; }
                .multifile-zip-btn:hover { background: #2563eb; color: #fff; text-decoration: none; }
                
                @media (max-width: 600px) {
                    .multifile-item { flex-wrap: wrap; }
                    .multifile-actions { margin-left: 0; margin-top: 10px; width: 100%; justify-content: flex-start; }
                    .multifile-meta { display: inline-block; margin-left: 10px; }
                }
            </style>

            <ul class="multifile-list">
                <?php foreach ($files as $file): 
                    if (!is_array($file) || empty($file['path'])) continue;
                    
                    $safe_path = str_replace(['../', '..\\', "\0"], '', ltrim((string)$file['path'], '/\\'));
                    $src = $upload_host . '/' . $safe_path;
                    
                    $raw_name = !empty($file['custom_name']) ? (string)$file['custom_name'] : (string)($file['name'] ?? '');
                    $ext = strtolower(pathinfo($safe_path, PATHINFO_EXTENSION));
                    
                    if (strtolower(pathinfo($raw_name, PATHINFO_EXTENSION)) !== $ext) {
                        $display_name = $raw_name . '.' . $ext;
                    } else {
                        $display_name = $raw_name;
                    }
                    
                    $display_name_esc = htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8');
                    $src_esc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

                    $icon = 'file';
                    if (in_array($ext, ['pdf'], true)) $icon = 'file-pdf';
                    elseif (in_array($ext, ['doc', 'docx'], true)) $icon = 'file-word';
                    elseif (in_array($ext, ['xls', 'xlsx', 'csv'], true)) $icon = 'file-excel';
                    elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true)) $icon = 'file-archive';
                    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) $icon = 'file-image';
                    elseif (in_array($ext, ['mp3', 'wav', 'ogg'], true)) $icon = 'file-audio';
                    elseif (in_array($ext, ['mp4', 'avi', 'mkv', 'webm'], true)) $icon = 'file-video';
                    elseif (in_array($ext, ['txt', 'md'], true)) $icon = 'file-alt';

                    $icon_esc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');

                    $size_str = isset($file['size']) ? files_format_bytes((int)$file['size']) : '';
                    $size_esc = htmlspecialchars((string)$size_str, ENT_QUOTES, 'UTF-8');
                    
                    $viewable_exts = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mp3', 'ogg', 'wav'];
                    $can_view = $allow_view && in_array($ext, $viewable_exts, true);
                ?>
                    <li class="multifile-item">
                        <div class="multifile-icon">
                            <svg class="icms-svg-icon" width="24" height="24"><use href="/templates/modern/images/icons/solid.svg#<?php echo $icon_esc; ?>"></use></svg>
                        </div>
                        <div class="multifile-info">
                            <span class="multifile-name" title="<?php echo $display_name_esc; ?>"><?php echo $display_name_esc; ?></span>
                            <?php if($size_esc): ?><span class="multifile-meta"><?php echo $size_esc; ?></span><?php endif; ?>
                        </div>
                        
                        <?php if ($can_view || $allow_download): ?>
                            <div class="multifile-actions">
                                <?php if ($can_view): ?>
                                    <a href="<?php echo $src_esc; ?>" target="_blank" rel="noopener noreferrer" class="multifile-btn multifile-btn-view">Смотреть</a>
                                <?php endif; ?>
                                <?php if ($allow_download): ?>
                                    <a href="<?php echo $src_esc; ?>" download="<?php echo $display_name_esc; ?>" class="multifile-btn multifile-btn-download">Скачать</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($allow_zip && count($files) > 1): 
                $current_url = $_SERVER['REQUEST_URI'] ?? '/';
                
                $current_url = explode('#', $current_url, 2)[0];
                
                if (!str_starts_with($current_url, '/')) {
                    $current_url = '/';
                }
                $separator = str_contains($current_url, '?') ? '&' : '?';
                $zip_url = $current_url . $separator . 'download_zip=1&hash=' . urlencode($zip_hash);
            ?>
                <a href="<?php echo htmlspecialchars($zip_url, ENT_QUOTES, 'UTF-8'); ?>" class="multifile-zip-btn">
                    <svg class="icms-svg-icon" width="16" height="16" style="margin-right:6px; vertical-align:text-bottom;"><use href="/templates/modern/images/icons/solid.svg#file-archive"></use></svg>
                    Скачать все архивом
                </a>
            <?php endif; ?>

            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldMultifile::parse render error: ' . $e->getMessage());
            return '';
        }
    }

    public function getInput($value) {
        $files = $this->getSafeYamlData($value);
        
        $uid = bin2hex(random_bytes(8)); 
        $name_esc = htmlspecialchars((string)$this->name, ENT_QUOTES, 'UTF-8');

        $exts = (string)$this->getOption('extensions');
        $max_size_mb = (float)($this->getOption('max_size_mb') ?: 10);
        
        $accept = '';
        if ($exts) {
            $accept = '.' . str_replace(',', ',.', str_replace(' ', '', htmlspecialchars($exts, ENT_QUOTES, 'UTF-8')));
        }

        ob_start();
        try {
            ?>
            <style>
                .btn-danger { color: #fff; background: #e66767; border-color: #e66767; transition: all 0.2s; }
                .btn-danger:hover { background: #e14646; border-color: #df3b3b; }
                .multifile-field-wrap .custom-del-btn { padding: 10px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
                .multifile-list-edit li { transition: transform 0.1s, box-shadow 0.1s; }
                .multifile-list-edit li.drag-over { border-color: #3b82f6 !important; }
                .multifile-info-text { margin-top: 10px; font-size: 13px; color: #64748b; line-height: 1.5; }
                .multifile-info-text strong { color: #475569; font-weight: 500; }
                .multifile-drag-handle { color:#94a3b8; font-size:24px; cursor:grab; padding: 0 10px; }
                .multifile-ext-badge { background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#475569; text-transform:uppercase; }
                .multifile-name-input { flex-grow:1; padding:8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; }
                @media (max-width: 600px) {
                    .multifile-field-wrap li { flex-wrap: wrap; }
                    .multifile-field-wrap li input[type="text"] { width: 100% !important; order: 3; margin-top: 10px; flex: none !important; }
                    .multifile-field-wrap li .custom-del-btn { margin-left: auto; order: 2; padding: 12px; }
                    .multifile-field-wrap li .multifile-ext-badge { order: 1; }
                }
            </style>

            <div class="multifile-field-wrap" id="wrap_<?php echo $uid; ?>" style="border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; background: #f8fafc;">
                <input type="hidden" name="<?php echo $name_esc; ?>[present]" value="1">

                <div class="upload-zone" style="margin-bottom: 15px;">
                    <div id="inputs_<?php echo $uid; ?>">
                        <input type="file" id="trigger_<?php echo $uid; ?>" name="<?php echo $name_esc; ?>_upload[]" multiple accept="<?php echo $accept; ?>" style="display:none;">
                    </div>
                    <button type="button" class="btn btn-primary" style="padding: 10px 20px; cursor: pointer; max-width: 100%; font-size: 15px;" onclick="document.getElementById('trigger_<?php echo $uid; ?>').click();">
                        Выбрать файлы
                    </button>
                    
                    <div class="multifile-info-text">
                        <?php if ($exts): ?>
                            <div><strong>Поддерживаемые форматы:</strong> <?php echo htmlspecialchars(str_replace(',', ', ', $exts), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div><strong>Максимальный размер файлов:</strong> <?php echo $max_size_mb; ?> Мб</div>
                    </div>
                </div>

                <ul id="list_<?php echo $uid; ?>" class="multifile-list-edit" style="list-style: none; padding: 0; margin: 0;">
                    <?php
                    $counter = 0;
                    foreach ($files as $f):
                        if (!is_array($f)) continue;
                        $fid = htmlspecialchars((string)($f['id'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $cname = htmlspecialchars((string)($f['custom_name'] ?? $f['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $oname = htmlspecialchars((string)($f['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $ext = htmlspecialchars(strtolower(pathinfo((string)($f['path'] ?? $f['name'] ?? ''), PATHINFO_EXTENSION)), ENT_QUOTES, 'UTF-8');
                    ?>
                        <li draggable="true" style="padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;">
                            <span class="multifile-drag-handle" title="Потяните для сортировки">☰</span>
                            <span class="multifile-ext-badge"><?php echo $ext; ?></span>
                            
                            <input type="hidden" name="<?php echo $name_esc; ?>_meta[<?php echo $counter; ?>][id]" value="<?php echo $fid; ?>">
                            <input type="hidden" name="<?php echo $name_esc; ?>_meta[<?php echo $counter; ?>][original]" value="<?php echo $oname; ?>">
                            <input type="text" name="<?php echo $name_esc; ?>_meta[<?php echo $counter; ?>][name]" value="<?php echo $cname; ?>" class="multifile-name-input" placeholder="Название файла" maxlength="255">
                            
                            <button type="button" class="btn-danger custom-del-btn exist-del-btn" title="Удалить">
                                <svg class="icms-svg-icon" fill="currentColor" style="width:16px; height:16px; display:block;"><use href="/templates/modern/images/icons/solid.svg#times-circle"></use></svg>
                            </button>
                        </li>
                    <?php 
                        $counter++;
                    endforeach; 
                    ?>
                </ul>

                <div id="deleted_<?php echo $uid; ?>" style="display:none;"></div>
            </div>

            <script>
                (function() {
                    const fieldName = <?php echo json_encode($this->name, JSON_THROW_ON_ERROR); ?>;
                    const uidStr = <?php echo json_encode($uid, JSON_THROW_ON_ERROR); ?>;
                    const acceptAttr = <?php echo json_encode($accept, JSON_THROW_ON_ERROR); ?>;
                    let fileCounter = <?php echo $counter; ?>;
                    
                    const wrap = document.getElementById("wrap_" + uidStr);
                    const list = document.getElementById("list_" + uidStr);
                    const inputsContainer = document.getElementById("inputs_" + uidStr);
                    const deletedContainer = document.getElementById("deleted_" + uidStr);
                    let initialTrigger = document.getElementById("trigger_" + uidStr);

                    setTimeout(function(){
                        if (wrap && wrap.closest("form")) {
                            const form = wrap.closest("form");
                            form.setAttribute("enctype", "multipart/form-data");
                            form.classList.remove('ajax-form');
                        }
                    }, 100);

                    list.addEventListener('click', function(e) {
                        const btn = e.target.closest('.exist-del-btn');
                        if (btn) {
                            btn.closest('li').remove();
                        }
                    });

                    function handleFiles(input) {
                        if (!input.files || input.files.length === 0) return;
                        
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            const li = document.createElement('li');
                            li.draggable = true;
                            li.style.cssText = 'padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;';
                            
                            const extMatch = file.name.match(/\.([^.]+)$/);
                            const ext = extMatch ? extMatch[1].toUpperCase() : 'FILE';
                            
                            const dragHandle = document.createElement('span');
                            dragHandle.className = 'multifile-drag-handle';
                            dragHandle.title = 'Потяните для сортировки';
                            dragHandle.textContent = '☰';
                            li.appendChild(dragHandle);

                            const extBadge = document.createElement('span');
                            extBadge.className = 'multifile-ext-badge';
                            extBadge.textContent = ext;
                            li.appendChild(extBadge);

                            const hiddenId = document.createElement('input');
                            hiddenId.type = 'hidden';
                            hiddenId.name = fieldName + '_meta[' + fileCounter + '][id]';
                            hiddenId.value = '';
                            li.appendChild(hiddenId);

                            const hiddenOrig = document.createElement('input');
                            hiddenOrig.type = 'hidden';
                            hiddenOrig.name = fieldName + '_meta[' + fileCounter + '][original]';
                            hiddenOrig.value = file.name; 
                            li.appendChild(hiddenOrig);

                            const nameInput = document.createElement('input');
                            nameInput.type = 'text';
                            nameInput.name = fieldName + '_meta[' + fileCounter + '][name]';
                            nameInput.value = file.name.replace(/\.[^/.]+$/, "");
                            nameInput.className = 'multifile-name-input';
                            nameInput.placeholder = 'Название файла';
                            nameInput.maxLength = 255;
                            li.appendChild(nameInput);

                            const delBtn = document.createElement('button');
                            delBtn.type = 'button';
                            delBtn.className = 'btn-danger custom-del-btn';
                            delBtn.title = 'Удалить';
                            
                            const svgNS = "http://www.w3.org/2000/svg";
                            const svg = document.createElementNS(svgNS, "svg");
                            svg.setAttribute("class", "icms-svg-icon");
                            svg.setAttribute("fill", "currentColor");
                            svg.setAttribute("style", "width:16px; height:16px; display:block;");
                            
                            const use = document.createElementNS(svgNS, "use");
                            use.setAttribute("href", "/templates/modern/images/icons/solid.svg#times-circle");
                            
                            svg.appendChild(use);
                            delBtn.appendChild(svg);
                            
                            delBtn.addEventListener('click', function() {
                                li.remove();
                                const hiddenDel = document.createElement('input');
                                hiddenDel.type = 'hidden';
                                hiddenDel.name = fieldName + '_deleted[]';
                                hiddenDel.value = file.name;
                                deletedContainer.appendChild(hiddenDel);
                            });
                            li.appendChild(delBtn);

                            list.appendChild(li);
                            fileCounter++;
                        }
                        
                        input.id = '';
                        input.style.display = 'none';
                        
                        const newInp = document.createElement('input');
                        newInp.type = 'file';
                        newInp.name = fieldName + '_upload[]';
                        newInp.multiple = true;
                        newInp.accept = acceptAttr;
                        newInp.id = 'trigger_' + uidStr;
                        newInp.style.display = 'none';
                        newInp.addEventListener('change', function() { handleFiles(this); });
                        inputsContainer.appendChild(newInp);
                    }

                    if (initialTrigger) {
                        initialTrigger.addEventListener('change', function() { handleFiles(this); });
                    }

                    let dragEl = null;

                    list.addEventListener('dragstart', function(e) {
                        dragEl = e.target.closest('li');
                        if (!dragEl) return;
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', ''); 
                        setTimeout(function() { 
                            dragEl.style.opacity = '0.4'; 
                            dragEl.style.background = '#f8fafc'; 
                        }, 0);
                    });

                    list.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        
                        const target = e.target.closest('li');
                        if (target && target !== dragEl) {
                            const rect = target.getBoundingClientRect();
                            const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                            list.insertBefore(dragEl, next && target.nextSibling || target);
                        }
                    });

                    list.addEventListener('dragend', function(e) {
                        if (dragEl) {
                            dragEl.style.opacity = '1';
                            dragEl.style.background = '#fff';
                            dragEl = null;
                        }
                    });
                })();
            </script>
            <?php
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('fieldMultifile::getInput render error: ' . $e->getMessage());
            return '';
        }
    }

    private function getFlatFiles($input_name): array {
        $files = [];
        if (!isset($_FILES[$input_name]) || !is_array($_FILES[$input_name]['name'])) return $files;
        $f = $_FILES[$input_name];
        
        $flatten = function($array, int $depth = 0) use (&$flatten) {
            $result = [];
            if ($depth > 5) return $result; 
            
            if (!is_array($array)) return [$array];
            foreach ($array as $val) {
                if (is_array($val)) {
                    $result = array_merge($result, $flatten($val, $depth + 1)); 
                } else {
                    $result[] = $val;
                }
            }
            return $result;
        };

        $names = $flatten($f['name']); 
        $types = $flatten($f['type']);
        $tmp_names = $flatten($f['tmp_name']); 
        $errors = $flatten($f['error']); 
        $sizes = $flatten($f['size']);

        $cnt = min(count($names), count($types), count($tmp_names), count($errors), count($sizes));

        for ($i = 0; $i < $cnt; $i++) {
            if ($errors[$i] === UPLOAD_ERR_OK) {
                $uniq_key = $i . '_' . md5((string)$names[$i]);
                $files[$uniq_key] = [
                    'name'     => (string)$names[$i], 
                    'type'     => (string)$types[$i], 
                    'tmp_name' => (string)$tmp_names[$i],
                    'error'    => (int)$errors[$i], 
                    'size'     => (int)$sizes[$i],
                    'original' => (string)$names[$i]
                ];
            }
        }
        return $files;
    }

    private function processCustomUpload(array $upload_data, string $allowed_exts, int $max_size_bytes): array {
        $ext = strtolower(pathinfo((string)$upload_data['name'], PATHINFO_EXTENSION));
        
        $blacklist = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'exe', 'sh', 'cgi', 'pl', 'py', 'svg', 'html', 'htm', 'js'];
        if (in_array($ext, $blacklist, true)) {
            return ['success' => false, 'error' => 'Загрузка исполняемых или активных файлов запрещена политикой безопасности'];
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $upload_data['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = [
                    'pdf'  => ['application/pdf'],
                    'jpg'  => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'png'  => ['image/png', 'image/x-png'],
                    'gif'  => ['image/gif'],
                    'webp' => ['image/webp'],
                    'zip'  => ['application/zip', 'application/x-zip-compressed'],
                    'rar'  => ['application/x-rar-compressed', 'application/vnd.rar'],
                    '7z'   => ['application/x-7z-compressed'],
                    'tar'  => ['application/x-tar'],
                    'gz'   => ['application/gzip', 'application/x-gzip'],
                    'doc'  => ['application/msword'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    'xls'  => ['application/vnd.ms-excel'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    'csv'  => ['text/csv', 'text/plain'],
                    'txt'  => ['text/plain'],
                    'md'   => ['text/plain', 'text/markdown'],
                    'mp3'  => ['audio/mpeg'],
                    'wav'  => ['audio/wav', 'audio/x-wav'],
                    'ogg'  => ['audio/ogg', 'video/ogg', 'application/ogg'],
                    'mp4'  => ['video/mp4'],
                    'avi'  => ['video/x-msvideo'],
                    'mkv'  => ['video/x-matroska'],
                    'webm' => ['video/webm']
                ];

                if (isset($allowed_mimes[$ext]) && !in_array($mime, $allowed_mimes[$ext], true)) {
                    error_log('fieldMultifile: Ошибка загрузки (Polyglot/MIME mismatch). Заявлен: ' . $ext . ', Реальный MIME: ' . $mime);
                    return ['success' => false, 'error' => 'MIME-тип не соответствует расширению'];
                }
            }
        }

        if ($allowed_exts) {
            $allowed_arr = array_map('trim', explode(',', strtolower($allowed_exts)));
            if (!in_array($ext, $allowed_arr, true)) {
                return ['success' => false, 'error' => 'Недопустимое расширение файла'];
            }
        }

        if ($max_size_bytes > 0 && $upload_data['size'] > $max_size_bytes) {
            return ['success' => false, 'error' => 'Файл слишком большой'];
        }
        if (!is_uploaded_file($upload_data['tmp_name'])) {
            return ['success' => false, 'error' => 'Ошибка HTTP POST'];
        }

        $config = cmsConfig::getInstance();
        $upload_path = $config->upload_path; 
        $sub_dir = 'files/' . date('Y-m') . '/'; 
        $full_dir = rtrim($upload_path, '/\\') . '/' . ltrim($sub_dir, '/\\');
        
        if (!is_dir($full_dir) && !mkdir($full_dir, 0755, true) && !is_dir($full_dir)) {
            return ['success' => false, 'error' => 'Ошибка создания директории на сервере'];
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $full_dir . $filename;

        if (move_uploaded_file($upload_data['tmp_name'], $destination)) {
            $clean_name = preg_replace('/[^\w\.\-\p{L}\p{N}\s]/u', '_', $upload_data['name']);
            return [
                'success' => true, 
                'url'     => $sub_dir . $filename, 
                'name'    => $clean_name, 
                'size'    => (int)$upload_data['size']
            ];
        }

        return ['success' => false, 'error' => 'Нет прав на запись в папку upload'];
    }

    public function store($value, $is_submitted, $old_value = null) {
        $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (empty($_POST) && empty($_FILES) && $content_length > 0) {
            cmsUser::addSessionMessage('Сбой загрузки: превышен лимит "post_max_size" на сервере!', 'error'); 
            return $old_value;
        }

        $core = cmsCore::getInstance(); 
        $files_model = cmsCore::getModel('files');
        
        $saved_files = [];
        $old_files = $this->getSafeYamlData($old_value);

        $meta = $core->request->get($this->name . '_meta', []); 
        $deleted = $core->request->get($this->name . '_deleted', []);
        
        if (!is_array($meta)) $meta = []; 
        if (!is_array($deleted)) $deleted = [];

        if (count($meta) > 500) $meta = array_slice($meta, 0, 500);

        $new_uploads = $this->getFlatFiles($this->name . '_upload');
        
        if (count($new_uploads) > 50) {
            cmsUser::addSessionMessage('Слишком много файлов загружается за один раз (максимум 50)', 'error');
            $new_uploads = array_slice($new_uploads, 0, 50, true);
        }

        $allowed_exts = (string)$this->getOption('extensions');
        $max_size_mb  = (float)($this->getOption('max_size_mb') ?: 10); 
        $max_size_bytes = (int)($max_size_mb * 1048576);

        foreach ($meta as $item) {
            if (!is_array($item)) continue;

            if (!empty($item['id'])) {
                foreach ($old_files as $k => $of) {
                    if (isset($of['id']) && (int)$of['id'] === (int)$item['id']) {
                        $of['custom_name'] = mb_substr((string)($item['name'] ?? ''), 0, 255); 
                        $saved_files[] = $of; 
                        unset($old_files[$k]); 
                        break;
                    }
                }
            } elseif (!empty($item['original'])) {
                $orig = (string)$item['original'];
                
                $del_index = array_search($orig, $deleted, true);
                if ($del_index !== false) { 
                    unset($deleted[$del_index]); 
                    continue; 
                }
                
                $upload_key = null;
                foreach ($new_uploads as $key => $up) {
                    if ($up['original'] === $orig) {
                        $upload_key = $key;
                        break;
                    }
                }

                if ($upload_key !== null) {
                    $upload_data = $new_uploads[$upload_key];
                    unset($new_uploads[$upload_key]); 

                    $result = $this->processCustomUpload($upload_data, $allowed_exts, $max_size_bytes);

                    if ($result['success']) {
                        $context = $core->getUriData(); 
                        $upload_params = [];
                        
                        if (isset($context['controller'])) $upload_params['target_controller'] = $context['controller'];
                        if (isset($context['action'])) $upload_params['target_subject'] = $context['action'];
                        
                        if (!str_contains($core->uri, '/add/') && !empty($context['params'][0]) && is_numeric($context['params'][0])) {
                            $upload_params['target_id'] = (int)$context['params'][0];
                        }
                        
                        $file_id = $files_model->registerFile(array_merge($upload_params, [
                            'path'    => $result['url'], 
                            'name'    => $result['name'], 
                            'user_id' => cmsUser::get('id')
                        ]));
                        
                        $custom_name = !empty($item['name']) ? mb_substr((string)$item['name'], 0, 255) : $result['name'];
                        
                        $saved_files[] = [
                            'id'          => $file_id, 
                            'name'        => $result['name'], 
                            'custom_name' => $custom_name, 
                            'size'        => $result['size'], 
                            'path'        => $result['url']
                        ];
                    } else {
                        $safe_orig = htmlspecialchars($orig, ENT_QUOTES, 'UTF-8');
                        cmsUser::addSessionMessage('Ошибка загрузки файла "'.$safe_orig.'": '.$result['error'], 'error');
                    }
                }
            }
        }
        
        foreach ($old_files as $of) { 
            if (!empty($of['id'])) {
                $files_model->deleteFile($of['id']); 
            }
        }
        
        return empty($saved_files) ? null : $saved_files;
    }

    public function delete($value) {
        if (empty($value)) return true;
        $files = $this->getSafeYamlData($value);
        if (empty($files)) return true;
        
        $files_model = cmsCore::getModel('files');
        foreach ($files as $f) { 
            if (!empty($f['id'])) {
                $files_model->deleteFile($f['id']); 
            }
        }
        return true;
    }

    public function getFiles($value) {
        if (empty($value)) return false;
        $files = $this->getSafeYamlData($value);
        $paths = [];
        
        $config = cmsConfig::getInstance();
        $upload_path = realpath($config->upload_path);

        if ($upload_path === false) {
            return false;
        }

        foreach($files as $f) { 
            if(!empty($f['path'])) {
                $file_real_path = realpath($upload_path . DIRECTORY_SEPARATOR . ltrim((string)$f['path'], '/\\'));
                if ($file_real_path !== false && str_starts_with($file_real_path, $upload_path)) {
                    $paths[] = str_replace(['../', '..\\', "\0"], '', ltrim((string)$f['path'], '/\\'));
                }
            } 
        } 
        return empty($paths) ? false : $paths;
    }
}
