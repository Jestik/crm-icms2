<?php
class formWidgetCrmChartOptions extends cmsForm {
    public function init() {
        return array(
            array(
                'type' => 'fieldset',
                'title' => 'Настройки графика',
                'childs' => array(
                    new fieldList('options:period', array(
                        'title' => 'Период',
                        'default' => 'month',
                        'items' => array(
                            'month'    => 'Текущий месяц',
                            'quarter'  => 'Текущий квартал',
                            'halfyear' => 'Последние полгода',
                            'year'     => 'Текущий год'
                        )
                    ))
                )
            )
        );
    }
}
