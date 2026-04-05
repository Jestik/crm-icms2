<?php

class fieldMultifile extends fieldFile {

    public $title = 'Мультифайл (список файлов)';
    public $sql   = 'text';
    public $allow_index = false;

    public function getInputType() {
        return 'file'; 
    }

    public function getOptions() {
        $max_size = files_convert_bytes(ini_get('post_max_size')) / 1048576;
        return [
            new fieldString('extensions', [
                'title'   => LANG_PARSER_FILE_EXTS,
                'hint'    => 'Например: pdf,doc,docx,xls,xlsx,txt,zip,rar,png,jpg',
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
                'hint'    => 'Показывать кнопку "Скачать" для каждого файла',
                'default' => true
            ]),
            new fieldCheckbox('allow_zip', [
                'title'   => 'Разрешить скачивание всех файлов одним архивом',
                'hint'    => 'Появится кнопка "Скачать всё (ZIP)"',
                'default' => true
            ])
        ];
    }

    public function getRules() { return $this->rules; }

    public function parse($value) {
        $files = is_array($value) ? $value : cmsModel::yamlToArray($value);
        if (empty($files) || !is_array($files)) {
            return '';
        }

        $upload_host    = cmsConfig::getInstance()->upload_host;
        $upload_path    = cmsConfig::getInstance()->upload_path;
        $allow_view     = $this->getOption('allow_view');
        $allow_download = $this->getOption('allow_download');
        $allow_zip      = $this->getOption('allow_zip');
        
        $zip_hash = md5(serialize($files) . $this->name); 

        // Генерация ZIP на лету
        if ($allow_zip && isset($_GET['download_zip']) && isset($_GET['hash']) && $_GET['hash'] === $zip_hash) {
            $zip_name = 'archive_' . date('Y-m-d_H-i-s') . '.zip';
            $zip_tmp_path = $upload_path . 'archive_' . uniqid() . '.zip';
            
            $zip = new ZipArchive();
            if ($zip->open($zip_tmp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($files as $file) {
                    $file_server_path = $upload_path . $file['path'];
                    if (file_exists($file_server_path)) {
                        $ext = pathinfo($file['path'], PATHINFO_EXTENSION);
                        $name = !empty($file['custom_name']) ? $file['custom_name'] : $file['name'];
                        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== strtolower($ext)) {
                            $name .= '.' . $ext;
                        }
                        $zip->addFile($file_server_path, $name);
                    }
                }
                $zip->close();
                
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                header('Content-Length: ' . filesize($zip_tmp_path));
                header('Pragma: no-cache');
                readfile($zip_tmp_path);
                @unlink($zip_tmp_path); 
                exit;
            }
        }

        ob_start();
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
            <?php foreach ($files as $file): ?>
                <?php 
                    $src = $upload_host . '/' . $file['path'];
                    $raw_name = !empty($file['custom_name']) ? $file['custom_name'] : $file['name'];
                    $ext = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
                    
                    if (strtolower(pathinfo($raw_name, PATHINFO_EXTENSION)) !== $ext) {
                        $display_name = $raw_name . '.' . $ext;
                    } else {
                        $display_name = $raw_name;
                    }
                    $display_name = htmlspecialchars($display_name, ENT_QUOTES);

                    $icon = 'file';
                    if (in_array($ext, ['pdf'])) $icon = 'file-pdf';
                    elseif (in_array($ext, ['doc', 'docx'])) $icon = 'file-word';
                    elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) $icon = 'file-excel';
                    elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) $icon = 'file-archive';
                    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) $icon = 'file-image';
                    elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) $icon = 'file-audio';
                    elseif (in_array($ext, ['mp4', 'avi', 'mkv', 'webm'])) $icon = 'file-video';
                    elseif (in_array($ext, ['txt', 'md'])) $icon = 'file-alt';

                    $size = isset($file['size']) ? files_format_bytes($file['size']) : '';
                    $viewable_exts = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mp3', 'ogg', 'wav'];
                    $can_view = $allow_view && in_array($ext, $viewable_exts);
                ?>
                <li class="multifile-item">
                    <div class="multifile-icon">
                        <svg class="icms-svg-icon" width="24" height="24"><use href="/templates/modern/images/icons/solid.svg#<?php echo $icon; ?>"></use></svg>
                    </div>
                    <div class="multifile-info">
                        <span class="multifile-name" title="<?php echo $display_name; ?>"><?php echo $display_name; ?></span>
                        <?php if($size): ?><span class="multifile-meta"><?php echo $size; ?></span><?php endif; ?>
                    </div>
                    
                    <?php if ($can_view || $allow_download): ?>
                        <div class="multifile-actions">
                            <?php if ($can_view): ?>
                                <a href="<?php echo $src; ?>" target="_blank" class="multifile-btn multifile-btn-view">Смотреть</a>
                            <?php endif; ?>
                            <?php if ($allow_download): ?>
                                <a href="<?php echo $src; ?>" download="<?php echo $display_name; ?>" class="multifile-btn multifile-btn-download">Скачать</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($allow_zip && count($files) > 1): ?>
            <?php 
                $current_url = $_SERVER['REQUEST_URI'];
                $separator = (strpos($current_url, '?') !== false) ? '&' : '?';
                $zip_url = $current_url . $separator . 'download_zip=1&hash=' . $zip_hash;
            ?>
            <a href="<?php echo htmlspecialchars($zip_url); ?>" class="multifile-zip-btn">
                <svg class="icms-svg-icon" width="16" height="16" style="margin-right:6px; vertical-align:text-bottom;"><use href="/templates/modern/images/icons/solid.svg#file-archive"></use></svg>
                Скачать все архивом
            </a>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    public function getInput($value) {
        $files = $value ? (is_array($value) ? $value : cmsModel::yamlToArray($value)) : [];
        if (!is_array($files)) $files = [];
        $uid = uniqid();
        $name = $this->name;

        $svg_trash = '<svg class="icms-svg-icon" fill="currentColor" style="width:16px; height:16px; display:block;"><use href="/templates/modern/images/icons/solid.svg#times-circle"></use></svg>';

        $exts = $this->getOption('extensions');
        $max_size_mb = $this->getOption('max_size_mb') ?: 10;
        
        $accept = '';
        if ($exts) {
            $accept = '.' . str_replace(',', ',.', str_replace(' ', '', $exts));
        }

        ob_start();
        ?>
        <style>
            .btn-danger { color: #fff; background: #e66767; border-color: #e66767; transition: all 0.2s; }
            .btn-danger:hover { background: #e14646; border-color: #df3b3b; }
            .multifile-field-wrap .custom-del-btn { padding: 10px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .multifile-list-edit li { transition: transform 0.1s, box-shadow 0.1s; }
            .multifile-list-edit li.drag-over { border-color: #3b82f6 !important; }
            .multifile-info-text { margin-top: 10px; font-size: 13px; color: #64748b; line-height: 1.5; }
            .multifile-info-text strong { color: #475569; font-weight: 500; }
            @media (max-width: 600px) {
                .multifile-field-wrap li { flex-wrap: wrap; }
                .multifile-field-wrap li input[type="text"] { width: 100% !important; order: 3; margin-top: 10px; flex: none !important; }
                .multifile-field-wrap li .custom-del-btn { margin-left: auto; order: 2; padding: 12px; }
                .multifile-field-wrap li span { order: 1; }
            }
        </style>

        <div class="multifile-field-wrap" id="wrap_<?php echo $uid; ?>" style="border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; background: #f8fafc;">
            <input type="hidden" name="<?php echo $name; ?>[present]" value="1">

            <div class="upload-zone" style="margin-bottom: 15px;">
                <div id="inputs_<?php echo $uid; ?>">
                    <input type="file" id="trigger_<?php echo $uid; ?>" name="<?php echo $name; ?>_upload[]" multiple accept="<?php echo $accept; ?>" style="display:none;" onchange="handleFiles_<?php echo $uid; ?>(this)">
                </div>
                <button type="button" class="btn btn-primary" style="padding: 10px 20px; cursor: pointer; max-width: 100%; font-size: 15px;" onclick="document.getElementById('trigger_<?php echo $uid; ?>').click();">
                    Выбрать файлы
                </button>
                
                <div class="multifile-info-text">
                    <?php if ($exts): ?>
                        <div><strong>Поддерживаемые форматы:</strong> <?php echo htmlspecialchars(str_replace(',', ', ', $exts)); ?></div>
                    <?php endif; ?>
                    <div><strong>Максимальный размер файлов:</strong> <?php echo $max_size_mb; ?> Мб</div>
                </div>
            </div>

            <ul id="list_<?php echo $uid; ?>" class="multifile-list-edit" style="list-style: none; padding: 0; margin: 0;">
                <?php
                $counter = 0;
                foreach ($files as $f) {
                    $cname = htmlspecialchars($f['custom_name'] ?? $f['name'], ENT_QUOTES);
                    $oname = htmlspecialchars($f['name'], ENT_QUOTES);
                    $ext = strtolower(pathinfo($f['path'] ?? $f['name'], PATHINFO_EXTENSION));
                    
                    echo "<li draggable='true' style='padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;'>";
                    echo "<span class='drag-handle' style='color:#94a3b8; font-size:24px; cursor:grab; padding: 0 10px;' title='Потяните для сортировки'>☰</span>";
                    echo "<span style='background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#475569; text-transform:uppercase;'>{$ext}</span>";
                    
                    echo "<input type='hidden' name='{$name}_meta[{$counter}][id]' value='{$f['id']}'>";
                    echo "<input type='hidden' name='{$name}_meta[{$counter}][original]' value='{$oname}'>";
                    echo "<input type='text' name='{$name}_meta[{$counter}][name]' value='{$cname}' style='flex-grow:1; padding:8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px;' placeholder='Название файла'>";
                    echo "<button type='button' onclick='this.parentElement.remove()' class='btn-danger custom-del-btn' title='Удалить'>{$svg_trash}</button>";
                    
                    echo "</li>";
                    $counter++;
                }
                ?>
            </ul>

            <div id="deleted_<?php echo $uid; ?>" style="display:none;"></div>
        </div>

        <script>
            setTimeout(function(){
                var wrap = document.getElementById("wrap_<?php echo $uid; ?>");
                if (wrap && wrap.closest("form")) {
                    var form = wrap.closest("form");
                    form.setAttribute("enctype", "multipart/form-data");
                    form.classList.remove('ajax-form');
                }
            }, 500);

            var counter_<?php echo $uid; ?> = <?php echo $counter; ?>;
            var svgIcon = `<?php echo $svg_trash; ?>`;
            var list_<?php echo $uid; ?> = document.getElementById('list_<?php echo $uid; ?>');
            
            function handleFiles_<?php echo $uid; ?>(input) {
                if (!input.files || input.files.length === 0) return;
                
                for (var i = 0; i < input.files.length; i++) {
                    var file = input.files[i];
                    var li = document.createElement('li');
                    li.draggable = true;
                    li.style.cssText = 'padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;';
                    
                    var extMatch = file.name.match(/\.([^.]+)$/);
                    var ext = extMatch ? extMatch[1].toUpperCase() : 'FILE';
                    
                    li.innerHTML = `
                        <span class='drag-handle' style='color:#94a3b8; font-size:24px; cursor:grab; padding: 0 10px;' title='Потяните для сортировки'>☰</span>
                        <span style='background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#475569;'>${ext}</span>
                        <input type="hidden" name="<?php echo $name; ?>_meta[${counter_<?php echo $uid; ?>}][id]" value="">
                        <input type="hidden" name="<?php echo $name; ?>_meta[${counter_<?php echo $uid; ?>}][original]" value="${file.name}">
                        <input type="text" name="<?php echo $name; ?>_meta[${counter_<?php echo $uid; ?>}][name]" value="${file.name.replace(/\.[^/.]+$/, "")}" style="flex-grow:1; padding:8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px;" placeholder="Название файла">
                        <button type="button" onclick="removeNewFile_<?php echo $uid; ?>(this, '${file.name}')" class="btn-danger custom-del-btn" title="Удалить">${svgIcon}</button>
                    `;
                    list_<?php echo $uid; ?>.appendChild(li);
                    counter_<?php echo $uid; ?>++;
                }
                
                input.id = '';
                input.style.display = 'none';
                
                var newInp = document.createElement('input');
                newInp.type = 'file';
                newInp.name = '<?php echo $name; ?>_upload[]';
                newInp.multiple = true;
                newInp.accept = '<?php echo $accept; ?>';
                newInp.id = 'trigger_<?php echo $uid; ?>';
                newInp.style.display = 'none';
                newInp.onchange = function() { handleFiles_<?php echo $uid; ?>(this); };
                document.getElementById('inputs_<?php echo $uid; ?>').appendChild(newInp);
            }

            function removeNewFile_<?php echo $uid; ?>(btn, fileName) {
                btn.parentElement.remove();
                var deletedContainer = document.getElementById('deleted_<?php echo $uid; ?>');
                var hiddenDel = document.createElement('input');
                hiddenDel.type = 'hidden';
                hiddenDel.name = '<?php echo $name; ?>_deleted[]';
                hiddenDel.value = fileName;
                deletedContainer.appendChild(hiddenDel);
            }

            var dragEl_<?php echo $uid; ?> = null;

            list_<?php echo $uid; ?>.addEventListener('dragstart', function(e) {
                dragEl_<?php echo $uid; ?> = e.target.closest('li');
                if (!dragEl_<?php echo $uid; ?>) return;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', ''); // Фикс для Firefox
                setTimeout(function() { 
                    dragEl_<?php echo $uid; ?>.style.opacity = '0.4'; 
                    dragEl_<?php echo $uid; ?>.style.background = '#f8fafc'; 
                }, 0);
            });

            list_<?php echo $uid; ?>.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                var target = e.target.closest('li');
                if (target && target !== dragEl_<?php echo $uid; ?>) {
                    var rect = target.getBoundingClientRect();
                    var next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                    list_<?php echo $uid; ?>.insertBefore(dragEl_<?php echo $uid; ?>, next && target.nextSibling || target);
                }
            });

            list_<?php echo $uid; ?>.addEventListener('dragend', function(e) {
                if (dragEl_<?php echo $uid; ?>) {
                    dragEl_<?php echo $uid; ?>.style.opacity = '1';
                    dragEl_<?php echo $uid; ?>.style.background = '#fff';
                    dragEl_<?php echo $uid; ?> = null;
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function getFlatFiles($input_name) {
        $files = [];
        if (!isset($_FILES[$input_name]) || !is_array($_FILES[$input_name]['name'])) return $files;
        $f = $_FILES[$input_name];
        
        $flatten = function($array) use (&$flatten) {
            $result = [];
            if (!is_array($array)) return [$array];
            foreach ($array as $val) {
                if (is_array($val)) $result = array_merge($result, $flatten($val)); else $result[] = $val;
            }
            return $result;
        };

        $names = $flatten($f['name']); $types = $flatten($f['type']);
        $tmp_names = $flatten($f['tmp_name']); $errors = $flatten($f['error']); $sizes = $flatten($f['size']);

        for ($i = 0; $i < count($names); $i++) {
            if ($errors[$i] === UPLOAD_ERR_OK) {
                $files[$names[$i]][] = [
                    'name' => $names[$i], 'type' => $types[$i], 'tmp_name' => $tmp_names[$i],
                    'error' => $errors[$i], 'size' => $sizes[$i],
                ];
            }
        }
        return $files;
    }

    private function processCustomUpload($upload_data, $allowed_exts, $max_size_bytes) {
        $ext = pathinfo($upload_data['name'], PATHINFO_EXTENSION);
        if ($allowed_exts) {
            $allowed_arr = array_map('trim', explode(',', strtolower($allowed_exts)));
            if (!in_array(strtolower($ext), $allowed_arr)) return ['success' => false, 'error' => 'Недопустимое расширение файла'];
        }
        if ($max_size_bytes && $upload_data['size'] > $max_size_bytes) return ['success' => false, 'error' => 'Файл слишком большой'];
        if (!is_uploaded_file($upload_data['tmp_name'])) return ['success' => false, 'error' => 'Ошибка HTTP POST'];

        $upload_path = cmsConfig::getInstance()->upload_path; 
        $sub_dir = 'files/' . date('Y-m') . '/'; $full_dir = $upload_path . $sub_dir;
        
        if (!is_dir($full_dir)) @mkdir($full_dir, 0777, true);

        $filename = md5(time() . uniqid() . $upload_data['name']) . '.' . strtolower($ext);
        $destination = $full_dir . $filename;

        if (move_uploaded_file($upload_data['tmp_name'], $destination)) {
            return ['success' => true, 'url' => $sub_dir . $filename, 'name' => preg_replace('/[^\w\.\-]/u', '_', $upload_data['name']), 'size' => $upload_data['size']];
        } else {
            return ['success' => false, 'error' => 'Нет прав на запись в папку upload'];
        }
    }

    public function store($value, $is_submitted, $old_value = null) {
        if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            cmsUser::addSessionMessage('Сбой загрузки: превышен лимит "post_max_size" на сервере!', 'error'); return $old_value;
        }

        $core = cmsCore::getInstance(); $files_model = cmsCore::getModel('files');
        
        $saved_files = [];
        $old_files = $old_value ? (is_array($old_value) ? $old_value : cmsModel::yamlToArray($old_value)) : [];
        if (!is_array($old_files)) $old_files = [];

        $meta = $core->request->get($this->name . '_meta', []); $deleted = $core->request->get($this->name . '_deleted', []);
        if (!is_array($meta)) $meta = []; if (!is_array($deleted)) $deleted = [];

        $new_uploads = $this->getFlatFiles($this->name . '_upload');
        $allowed_exts = $this->getOption('extensions');
        $max_size_mb  = ($this->getOption('max_size_mb') ?: 10); $max_size_bytes = $max_size_mb * 1048576;

        foreach ($meta as $item) {
            if (!empty($item['id'])) {
                foreach ($old_files as $k => $of) {
                    if ($of['id'] == $item['id']) {
                        $of['custom_name'] = $item['name']; $saved_files[] = $of; unset($old_files[$k]); break;
                    }
                }
            } else if (!empty($item['original'])) {
                $orig = $item['original'];
                $del_index = array_search($orig, $deleted);
                if ($del_index !== false) { unset($deleted[$del_index]); continue; }
                
                if (!empty($new_uploads[$orig])) {
                    $upload_data = array_shift($new_uploads[$orig]);
                    $result = $this->processCustomUpload($upload_data, $allowed_exts, $max_size_bytes);

                    if ($result['success']) {
                        $context = $core->getUriData(); $upload_params = [];
                        if (isset($context['controller'])) $upload_params['target_controller'] = $context['controller'];
                        if (isset($context['action'])) $upload_params['target_subject'] = $context['action'];
                        if (strpos($core->uri, '/add/') === false && !empty($context['params'][0]) && is_numeric($context['params'][0])) {
                            $upload_params['target_id'] = $context['params'][0];
                        }
                        $file_id = $files_model->registerFile(array_merge($upload_params, [
                            'path' => $result['url'], 'name' => $result['name'], 'user_id' => cmsUser::get('id')
                        ]));
                        $saved_files[] = ['id' => $file_id, 'name' => $result['name'], 'custom_name' => !empty($item['name']) ? $item['name'] : $result['name'], 'size' => $result['size'], 'path' => $result['url']];
                    } else {
                        cmsUser::addSessionMessage('Ошибка загрузки файла "'.$orig.'": '.$result['error'], 'error');
                    }
                }
            }
        }
        foreach ($old_files as $of) { if (!empty($of['id'])) $files_model->deleteFile($of['id']); }
        return empty($saved_files) ? null : $saved_files;
    }

    public function delete($value) {
        if (empty($value)) return true;
        $files = is_array($value) ? $value : cmsModel::yamlToArray($value);
        if (!is_array($files)) return true;
        $files_model = cmsCore::getModel('files');
        foreach ($files as $f) { if (!empty($f['id'])) $files_model->deleteFile($f['id']); }
        return true;
    }

    public function getFiles($value) {
        if (empty($value)) return false;
        $files = is_array($value) ? $value : cmsModel::yamlToArray($value);
        $paths = [];
        if (is_array($files)) { foreach($files as $f) { if(!empty($f['path'])) $paths[] = $f['path']; } }
        return $paths;
    }
}
