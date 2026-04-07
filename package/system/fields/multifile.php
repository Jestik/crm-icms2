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
            new fieldNumber('max_files', [
                'title'   => 'Максимальное количество файлов',
                'hint'    => 'Укажите лимит (0 или пусто — без ограничений)',
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

        $core           = cmsCore::getInstance();
        $config         = cmsConfig::getInstance();
        $upload_host    = $config->upload_host;
        $upload_path    = $config->upload_path;
        
        $allow_view     = $this->getOption('allow_view');
        $allow_download = $this->getOption('allow_download');
        $allow_zip      = $this->getOption('allow_zip');
        
        $app_secret = md5($config->root_path . $this->name . 'multifile_secret_salt');
        $zip_hash = hash_hmac('sha256', serialize($files), $app_secret); 

        if ($allow_zip && $core->request->get('download_zip') && $core->request->get('hash') === $zip_hash) {
            $this->processZipDownload($files, $upload_path);
        }

        ob_start();
        
        static $css_loaded = false;
        if (!$css_loaded) {
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
                .custom-del-btn { padding: 4px; border: none; background: transparent; color: #ef4444; cursor: pointer; transition: color 0.2s; display: flex; align-items: center; }
                .custom-del-btn:hover { color: #b91c1c; }
                @media (max-width: 600px) {
                    .multifile-item { flex-wrap: wrap; }
                    .multifile-actions { margin-left: 0; margin-top: 10px; width: 100%; justify-content: flex-start; }
                }
            </style>
            <?php
            $css_loaded = true;
        }
        ?>

        <ul class="multifile-list">
            <?php foreach ($files as $file): ?>
                <?php 
                    $src = $upload_host . '/' . $file['path'];
                    $raw_name = !empty($file['custom_name']) ? $file['custom_name'] : $file['name'];
                    $ext = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
                    
                    $display_name = $raw_name;
                    if (strtolower(pathinfo($raw_name, PATHINFO_EXTENSION)) !== $ext) {
                        $display_name .= '.' . $ext;
                    }
                    $display_name = htmlspecialchars($display_name, ENT_QUOTES);

                    $icon = 'file';
                    if (in_array($ext, ['pdf'])) $icon = 'file-pdf';
                    elseif (in_array($ext, ['doc', 'docx'])) $icon = 'file-word';
                    elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) $icon = 'file-excel';
                    elseif (in_array($ext, ['zip', 'rar', '7z'])) $icon = 'file-archive';
                    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $icon = 'file-image';

                    $size = isset($file['size']) ? files_format_bytes($file['size']) : '';
                    $viewable_exts = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
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
                    <div class="multifile-actions">
                        <?php if ($can_view): ?>
                            <a href="<?php echo $src; ?>" target="_blank" class="multifile-btn multifile-btn-view">Смотреть</a>
                        <?php endif; ?>
                        <?php if ($allow_download): ?>
                            <a href="<?php echo $src; ?>" download="<?php echo $display_name; ?>" class="multifile-btn multifile-btn-download">Скачать</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($allow_zip && count($files) > 1): ?>
            <?php 
                $query_params = array_merge($core->request->getAll(), ['download_zip' => 1, 'hash' => $zip_hash]);
                $current_path = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
                $zip_url = $current_path . '?' . http_build_query($query_params);
            ?>
            <a href="<?php echo htmlspecialchars($zip_url); ?>" class="multifile-zip-btn">
                <svg class="icms-svg-icon" width="16" height="16" style="margin-right:6px; vertical-align:text-bottom;"><use href="/templates/modern/images/icons/solid.svg#file-archive"></use></svg>
                Скачать все архивом
            </a>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    private function processZipDownload($files, $upload_path) {
        while (ob_get_level()) ob_end_clean();

        $zip_name = 'archive_' . date('Y-m-d_H-i-s') . '.zip';
        $zip_tmp_path = $upload_path . 'archive_' . uniqid() . '.zip';
        $real_upload_path = realpath($upload_path);

        $zip = new ZipArchive();
        if ($zip->open($zip_tmp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                $file_server_path = realpath($upload_path . $file['path']);
                
                if ($file_server_path && strpos($file_server_path, $real_upload_path) === 0 && file_exists($file_server_path)) {
                    $ext = pathinfo($file['path'], PATHINFO_EXTENSION);
                    $name = !empty($file['custom_name']) ? $file['custom_name'] : $file['name'];
                    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== strtolower($ext)) { 
                        $name .= '.' . $ext; 
                    }
                    $zip->addFile($file_server_path, $name);
                }
            }
            $zip->close();
            
            register_shutdown_function(function() use ($zip_tmp_path) {
                if (file_exists($zip_tmp_path)) @unlink($zip_tmp_path);
            });
            
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_tmp_path));
            header('Pragma: no-cache');
            readfile($zip_tmp_path);
            exit;
        }
    }

    public function getInput($value) {
        $files = $value ? (is_array($value) ? $value : cmsModel::yamlToArray($value)) : [];
        if (!is_array($files)) $files = [];
        $uid = uniqid();
        $name = $this->name;
        $max_files = (int)$this->getOption('max_files');
        $exts = $this->getOption('extensions');
        $accept = $exts ? '.' . str_replace(',', ',.', str_replace(' ', '', $exts)) : '';

        ob_start();
        ?>
        <div class="multifile-field-wrap" id="wrap_<?php echo $uid; ?>" style="border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; background: #f8fafc;">
            <input type="hidden" name="<?php echo $name; ?>[present]" value="1">
            <div class="upload-zone" style="margin-bottom: 15px;">
                <div id="inputs_<?php echo $uid; ?>">
                    <input type="file" id="trigger_<?php echo $uid; ?>" name="<?php echo $name; ?>_upload[]" multiple accept="<?php echo $accept; ?>" style="display:none;" onchange="window.handleFiles_<?php echo $uid; ?>(this)">
                </div>
                <button type="button" class="btn btn-primary" style="padding: 10px 20px;" onclick="document.getElementById('trigger_<?php echo $uid; ?>').click();">Выбрать файлы</button>
                <div style="margin-top: 10px; font-size: 13px; color: #64748b;">
                    <?php if ($exts): ?><div>Поддерживаемые форматы: <?php echo htmlspecialchars($exts); ?></div><?php endif; ?>
                    <?php if ($max_files): ?><div>Лимит: <?php echo $max_files; ?> файлов</div><?php endif; ?>
                </div>
            </div>

            <ul id="list_<?php echo $uid; ?>" style="list-style: none; padding: 0; margin: 0;">
                <?php
                $counter = 0;
                foreach ($files as $f) {
                    $cname = htmlspecialchars($f['custom_name'] ?? $f['name'], ENT_QUOTES);
                    $oname = htmlspecialchars($f['name'], ENT_QUOTES);
                    $ext = strtolower(pathinfo($f['path'] ?? $f['name'], PATHINFO_EXTENSION));
                    echo "<li draggable='true' style='padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;'>";
                    echo "<span style='cursor:grab; color:#94a3b8; font-size:20px;'>☰</span>";
                    echo "<input type='hidden' name='{$name}_meta[{$counter}][id]' value='{$f['id']}'>";
                    echo "<input type='hidden' name='{$name}_meta[{$counter}][original]' value='{$oname}'>";
                    echo "<input class='input form-control' type='text' name='{$name}_meta[{$counter}][name]' value='{$cname}' style='flex-grow:1; padding:8px;' placeholder='Название'>";
                    echo "<button type='button' onclick='this.parentElement.remove(); window.reindex_{$uid}();' class='btn-danger custom-del-btn' title='Удалить'><svg class='icms-svg-icon' fill='currentColor' style='width:16px; height:16px; display:block;'><use href='/templates/modern/images/icons/solid.svg#times-circle'></use></svg></button>";
                    echo "</li>";
                    $counter++;
                }
                ?>
            </ul>
            <div id="deleted_<?php echo $uid; ?>" style="display:none;"></div>
        </div>

        <script>
        (function() {
            var counter = <?php echo $counter; ?>;
            var list = document.getElementById('list_<?php echo $uid; ?>');
            var baseName = "<?php echo $name; ?>_meta";
            
            window.reindex_<?php echo $uid; ?> = function() {
                var items = list.children;
                var safeBaseName = baseName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
                var regex = new RegExp('(' + safeBaseName + ')\\[\\d+\\]');
                
                for (var i = 0; i < items.length; i++) {
                    var inputs = items[i].querySelectorAll('input[name*="_meta"]');
                    inputs.forEach(input => {
                        input.name = input.name.replace(regex, '$1[' + i + ']');
                    });
                }
                counter = items.length;
            };

            window.handleFiles_<?php echo $uid; ?> = function(input) {
                var limit = <?php echo (int)$max_files; ?>;
                if (limit > 0 && (list.children.length + input.files.length) > limit) {
                    alert('Превышен лимит файлов: ' + limit);
                    input.value = ''; return;
                }
                for (var i = 0; i < input.files.length; i++) {
                    var file = input.files[i];
                    var li = document.createElement('li');
                    li.draggable = true;
                    li.style.cssText = 'padding:12px; border:1px solid #e2e8f0; margin-bottom:8px; display:flex; align-items:center; gap:10px; background:#fff; border-radius: 6px;';
                    li.innerHTML = `<span style='cursor:grab; color:#94a3b8; font-size:20px;'>☰</span>
                        <input type="hidden" name="<?php echo $name; ?>_meta[${counter}][id]" value="">
                        <input type="hidden" name="<?php echo $name; ?>_meta[${counter}][original]" value="${file.name}">
                        <input class="input form-control" type="text" name="<?php echo $name; ?>_meta[${counter}][name]" value="${file.name.replace(/\.[^/.]+$/, "")}" style="flex-grow:1; padding:8px;" placeholder="Название">
                        <button type="button" onclick="this.parentElement.remove(); window.reindex_<?php echo $uid; ?>();" class="btn-danger custom-del-btn" title="Удалить"><svg class="icms-svg-icon" fill="currentColor" style="width:16px; height:16px; display:block;"><use href="/templates/modern/images/icons/solid.svg#times-circle"></use></svg></button>`;
                    list.appendChild(li);
                    counter++;
                }
                window.reindex_<?php echo $uid; ?>();
                
                var nextInput = input.cloneNode();
                nextInput.value = '';
                input.style.display = 'none'; input.id = '';
                input.parentNode.appendChild(nextInput);
            };

            var dragEl = null;
            list.addEventListener('dragstart', e => {
                if (e.target.tagName === 'INPUT') { e.preventDefault(); return; }
                dragEl = e.target.closest('li');
                if (dragEl) {
                    e.dataTransfer.effectAllowed = 'move';
                    setTimeout(() => dragEl.style.opacity = '0.5', 0);
                }
            });
            list.addEventListener('dragover', e => {
                e.preventDefault();
                var target = e.target.closest('li');
                if (target && dragEl && target !== dragEl) {
                    var rect = target.getBoundingClientRect();
                    var next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                    list.insertBefore(dragEl, next ? target.nextSibling : target);
                }
            });
            list.addEventListener('dragend', () => {
                if (dragEl) {
                    dragEl.style.opacity = '';
                    dragEl = null;
                    window.reindex_<?php echo $uid; ?>();
                }
            });
            
            var retries = 0;
            (function initForm() {
                var form = list.closest('form');
                if (form) { 
                    form.setAttribute('enctype', 'multipart/form-data'); 
                    form.classList.remove('ajax-form'); 
                } else if (retries < 20) {
                    retries++;
                    setTimeout(initForm, 100);
                }
            })();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function store($value, $is_submitted, $old_value = null) {
        $core = cmsCore::getInstance();
        $files_model = cmsCore::getModel('files');
        $saved_files = [];
        $old_files = $old_value ? (is_array($old_value) ? $old_value : cmsModel::yamlToArray($old_value)) : [];
        
        $meta = $core->request->get($this->name . '_meta', []);
        $max_files = (int)$this->getOption('max_files');

        $uploads = [];
        if (!empty($_FILES[$this->name . '_upload'])) {
            $f = $_FILES[$this->name . '_upload'];
            foreach ($f['name'] as $k => $v) {
                if ($f['error'][$k] == UPLOAD_ERR_OK) {
                    $uploads[] = ['name' => $v, 'tmp_name' => $f['tmp_name'][$k], 'size' => $f['size'][$k]];
                }
            }
        }

        foreach ($meta as $item) {
            if ($max_files > 0 && count($saved_files) >= $max_files) break;

            if (!empty($item['id'])) {
                foreach ($old_files as $k => $of) {
                    if ($of['id'] == $item['id']) {
                        $of['custom_name'] = $item['name'];
                        $saved_files[] = $of; unset($old_files[$k]); break;
                    }
                }
            } elseif (!empty($item['original'])) {
                $matched_upload = null;
                foreach ($uploads as $uk => $up) {
                    if ($up['name'] === $item['original']) {
                        $matched_upload = $up;
                        unset($uploads[$uk]); 
                        break;
                    }
                }

                if ($matched_upload) {
                    $result = $this->processCustomUpload($matched_upload);
                    if ($result['success']) {
                        $file_id = $files_model->registerFile([
                            'path' => $result['url'], 'name' => $result['name'], 'user_id' => cmsUser::get('id')
                        ]);
                        $saved_files[] = ['id' => $file_id, 'name' => $result['name'], 'custom_name' => $item['name'], 'size' => $matched_upload['size'], 'path' => $result['url']];
                    } else {
                        cmsUser::addSessionMessage('Ошибка загрузки файла "' . htmlspecialchars($matched_upload['name']) . '": ' . ($result['error'] ?? 'неизвестная ошибка'), 'error');
                    }
                }
            }
        }

        foreach ($old_files as $of) { if (!empty($of['id'])) $files_model->deleteFile($of['id']); }
        return $saved_files ?: null;
    }

    private function processCustomUpload($file) {
        $config = cmsConfig::getInstance();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array_map('trim', explode(',', strtolower($this->getOption('extensions'))));
        
        if (!in_array($ext, $allowed)) {
            return ['success' => false, 'error' => 'недопустимый формат файла'];
        }

        $max_size_mb = (float)$this->getOption('max_size_mb');
        if ($max_size_mb > 0) {
            $max_size_bytes = $max_size_mb * 1048576;
            if ($file['size'] > $max_size_bytes) {
                return ['success' => false, 'error' => 'превышен допустимый размер (' . $max_size_mb . ' Мб)'];
            }
        }
        
        $sub = 'files/' . date('Y-m') . '/';
        $dir = $config->upload_path . $sub;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $fname = md5(uniqid()) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
            return ['success' => true, 'url' => $sub . $fname, 'name' => $file['name']];
        }
        return ['success' => false, 'error' => 'ошибка перемещения загруженного файла на сервере'];
    }
}
