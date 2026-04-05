<?php
/**
 * Template Name: TRIP
 * Template Type: content
 */
if( $ctype['options']['list_show_filter'] ) {
    $this->renderAsset('ui/filter-panel', [
        'css_prefix'   => $ctype['name'],
        'page_url'     => $page_url,
        'fields'       => $fields,
        'props_fields' => $props_fields,
        'props'        => $props,
        'filters'      => $filters,
        'ext_hidden_params' => $ext_hidden_params,
        'is_expanded'  => $ctype['options']['list_expand_filter']
    ]);
}
?>
<?php if (!$items){ ?>
    <p class="alert alert-info mt-4 alert-list-empty">
        <?php if(!empty($ctype['labels']['many'])){ ?>
            <?php echo sprintf(LANG_TARGET_LIST_EMPTY, $ctype['labels']['many']); ?>
        <?php } else { ?>
            <?php echo LANG_LIST_EMPTY; ?>
        <?php } ?>
    </p>
<?php return; } ?>

<?php $first_item = reset($items); ?>

<div class="content_list table <?php echo $ctype['name']; ?>_list table-responsive-md mt-3 mt-md-4">

    <table class="table table-hover">
        <thead>
            <tr>
                <?php foreach($first_item['fields_names'] as $field){ ?>
                    <th <?php if ($field['label_pos'] === 'none') { ?>class="d-none d-lg-table-cell"<?php } ?>>
                        <?php echo $field['label_pos'] !== 'none' ? string_replace_svg_icons($field['title']) : ''; ?>
                    </th>
                <?php } ?>
                <?php if (!empty($first_item['info_bar'])){ ?>
                    <th class="d-none d-lg-table-cell"></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($items as $item){ ?>
            <tr>
            <?php foreach($item['fields_names'] as $_field){ ?>
                <td class="align-middle field ft_<?php echo $_field['type']; ?> f_<?php echo $_field['name']; ?><?php if ($_field['label_pos'] === 'none') { ?> d-none d-lg-table-cell<?php } ?>">

                    <?php if (!isset($item['fields'][$_field['name']])) { continue; } ?>
                    <?php $field = $item['fields'][$_field['name']]; ?>

                    <?php if ($field['name'] === 'title' && $ctype['options']['item_on']){ ?>
                        <h3 class="h5 m-0">
                        <?php if ($item['parent_id']){ ?>
                            <a class="parent_title" href="<?php echo rel_to_href($item['parent_url']); ?>">
                                <?php html($item['parent_title']); ?>
                            </a>
                            &rarr;
                        <?php } ?>
                        <a href="<?php echo href_to($ctype['name'], $item['slug'].'.html'); ?>">
                            <?php html($item[$field['name']]); ?>
                        </a>
                        </h3>
                        
                    <?php } elseif ($_field['type'] === 'tripcost') { ?>
                        
                        <?php 
                            $tc_total = 0;
                            $tc_raw = isset($item[$_field['name']]) ? $item[$_field['name']] : '';
                            $tc_data = [];

                            if (is_array($tc_raw)) {
                                $tc_data = $tc_raw;
                            } elseif (is_string($tc_raw) && trim($tc_raw) !== '') {
                                $tc_data = json_decode($tc_raw, true);
                                if (is_string($tc_data)) { $tc_data = json_decode($tc_data, true); }
                                if (!is_array($tc_data)) {
                                    try { $tc_data = cmsModel::yamlToArray($tc_raw); } catch (Exception $e) { $tc_data = []; }
                                }
                            }

                            if (is_array($tc_data) && !empty($tc_data['distance'])) {
                                $dist    = (float)str_replace(',', '.', (string)$tc_data['distance']);
                                $f_price = (float)str_replace(',', '.', (string)($tc_data['fuel_price'] ?? 0));
                                $f_cons  = (float)str_replace(',', '.', (string)($tc_data['fuel_cons'] ?? 0));
                                $a_rate  = (float)str_replace(',', '.', (string)($tc_data['amort_rate'] ?? 0));
                                $has_trailer = !empty($tc_data['trailer']);
                                
                                $fuel_cost  = ($dist / 100) * $f_cons * $f_price;
                                $amort_cost = $dist * $a_rate;
                                $tc_total   = $fuel_cost + $amort_cost;

                                if ($has_trailer) {
                                    $tc_total *= 1.40; // +40%
                                }
                            }
                        ?>
                        <span style="font-size: 1.1em; font-weight: bold; color: #155724; white-space: nowrap;">
                            <?php echo number_format($tc_total, 2, '.', ' '); ?> &euro;
                        </span>
                        
                    <?php } else { ?>
                        <?php echo $field['html']; ?>
                    <?php } ?>
                </td>
            <?php } ?>
            <?php if (!empty($item['info_bar'])){ ?>
                <td class="align-middle d-none d-lg-table-cell">
                    <div class="info_bar">
                        <?php foreach($item['info_bar'] as $bar){ ?>
                            <div class="bar_item <?php echo !empty($bar['css']) ? $bar['css'] : ''; ?>" title="<?php html(!empty($bar['title']) ? $bar['title'] : ''); ?>">
                                <?php if (!empty($bar['icon'])){ ?>
                                    <?php html_svg_icon('solid', $bar['icon']); ?>
                                <?php } ?>
                                <?php if (!empty($bar['href'])){ ?>
                                    <a class="stretched-link" href="<?php echo $bar['href']; ?>">
                                        <?php echo $bar['html']; ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo $bar['html']; ?>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </td>
            <?php } ?>
            </tr>
        <?php } ?>
        </tbody>
    </table>

</div>
<?php echo html_pagebar($page, $perpage, $total, $page_url, $filter_query); ?>
