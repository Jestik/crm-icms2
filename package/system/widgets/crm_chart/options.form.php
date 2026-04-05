<?php
class formWidgetCrmChartOptions extends cmsForm {
    
    public function init() {
        return array(
            array(
                'type' => 'fieldset',
                'title' => 'Настройки графика',
                'childs' => array(
                    new fieldList('options:ctype_name', array(
                        'title' => 'Тип контента',
                        'hint' => 'Выберите тип контента, из которого брать данные',
                        'generator' => function() {
                            $model = cmsCore::getModel('content');
                            $ctypes = $model->getContentTypes();
                            $items = array();
                            if ($ctypes) {
                                foreach ($ctypes as $ctype) {
                                    $items[$ctype['name']] = $ctype['title'];
                                }
                            }
                            return $items;
                        }
                    )),
                    new fieldList('options:period', array(
                        'title' => 'Период',
                        'default' => 'month',
                        'items' => array(
                            'month'    => 'Текущий месяц',
                            'quarter'  => 'Текущий квартал',
                            'halfyear' => 'Последние полгода',
                            'year'     => 'Текущий год'
                        )
                    )),
                    new fieldListMultiple('options:show_metrics', array(
                        'title' => 'Что отображать на графике',
                        'default' => array('income', 'expenses', 'profit', 'count'),
                        'items' => array(
                            'count'    => 'Количество сделок',
                            'income'   => 'Доход (Приход)',
                            'expenses' => 'Расходы',
                            'profit'   => 'Чистая прибыль'
                        )
                    )),
                    new fieldList('options:chart_style', array(
                        'title' => 'Стиль графика',
                        'default' => 'bar',
                        'items' => array(
                            'line' => 'Линейный график (Line)',
                            'bar'  => 'Столбчатая диаграмма (Bar)'
                        )
                    ))
                )
            ),
            
            array(
                'type' => 'fieldset',
                'title' => 'Привязка полей (Системные имена)',
                'childs' => array(
                    new fieldString('options:field_income', array(
                        'title' => 'Поле "Приход" (сумма)',
                        'default' => 'income'
                    )),
                    new fieldString('options:field_expenses', array(
                        'title' => 'Поле "Расходы" (CRM Калькулятор)',
                        'default' => 'expenses'
                    )),
                    new fieldString('options:field_profit', array(
                        'title' => 'Поле "Чистая прибыль"',
                        'hint' => 'Оставьте пустым, если прибыль нужно считать автоматически (Приход - Расходы)',
                        'default' => ''
                    )),
                    new fieldString('options:field_date_pub', array(
                        'title' => 'Поле "Дата старта сделки"',
                        'hint' => 'Используется для графиков "Количество" и "Расходы". (По умолчанию: date_pub)',
                        'default' => 'date_pub'
                    )),
                    new fieldString('options:field_date_done', array(
                        'title' => 'Поле "Дата закрытия сделки"',
                        'hint' => 'Используется для "Прихода" и "Прибыли". (По умолчанию: verkaufdate)',
                        'default' => 'verkaufdate'
                    ))
                )
            )
        );
    }
}
