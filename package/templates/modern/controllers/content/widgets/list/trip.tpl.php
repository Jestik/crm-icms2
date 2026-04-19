<?php
/**
 * Template Name: TRIP
 * Template Type: widget
 */
?>
<?php if (!$items) return; ?>
<?php $first_item = reset($items); ?>

<div class="icms-widget__content_list content_list table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <?php foreach($first_item['fields'] as $field){ ?>
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
        <?php foreach($items as $item) { ?>
            <tr>
                <?php foreach($item['fields'] as $field){ ?>
                    <td class="align-middle field ft_<?php echo $field['type']; ?> f_<?php echo $field['name']; ?> <?php if ($field['label_pos'] === 'none') { ?> d-none d-lg-table-cell<?php } ?>">

                        <?php if ($field['name'] === 'title' && $ctype['options']['item_on']){ ?>
                            <h3 class="m-0 h6">
                            <?php if ($item['parent_id']){ ?>
                                <a class="parent_title" href="<?php echo rel_to_href($item['parent_url']); ?>"><?php html($item['parent_title']); ?></a>
                                &rarr;
                            <?php } ?>

                            <?php if (!empty($item['is_private_item'])) { ?>
                                <?php html($item[$field['name']]); ?>
                                <span class="is_private text-secondary" title="<?php html($item['private_item_hint']); ?>">
                                    <?php html_svg_icon('solid', 'lock'); ?>
                                </span>
                            <?php } else { ?>
                                <a href="<?php echo href_to($ctype['name'], $item['slug'].'.html'); ?>">
                                    <?php html($item[$field['name']]); ?>
                                </a>
                                <?php if ($item['is_private']) { ?>
                                    <span class="is_private text-secondary" title="<?php echo LANG_PRIVACY_HINT; ?>">
                                        <?php html_svg_icon('solid', 'lock'); ?>
                                    </span>
                                <?php } ?>
                            <?php } ?>
                            </h3>

                        <?php } elseif ($field['type'] === 'tripcost') { ?>
                            
                            <?php 
                                $tc_total = 0;
                                $tc_raw = isset($item[$field['name']]) ? $item[$field['name']] : '';
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
                            <div class="value">
                                <?php echo $field['html']; ?>
                            </div>
                        <?php } ?>
                    </td>
                <?php } ?>

                <?php if (!empty($item['info_bar'])){ ?>
                    <td class="align-middle info_bar d-none d-lg-table-cell">
                        <?php foreach($item['info_bar'] as $bar){ ?>
                            <div class="mr-2 bar_item <?php echo !empty($bar['css']) ? $bar['css'] : ''; ?>" title="<?php html(!empty($bar['title']) ? $bar['title'] : ''); ?>">
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
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
