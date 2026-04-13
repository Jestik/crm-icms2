# CRM for InstantCMS 2

**MVP - использование на даной стадии на рабочих проектах не рекомендуется**

**Набор дополнений для организации мини CRM системы на базе [InstantCMS 2](https://github.com/instantsoft/icms2)**

**Краткие пояснения:**

* **Поля:**
  * [expenses.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/expenses.php) — CRM Калькулятор, считает расходы и доходы участников сделки
  * [multifile.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/multifile.php) — Мультифайл (список файлов), позволяет загружать файлы к записи
  * [phone.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/phone.php) — Телефон с мессенджерами, выводит телефон и мессенджеры
  * [profit.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/profit.php) — Окупаемость / Прибыль, считает сумму дочерних записей типа контента и вычисляет разницу между стоимостью родительской записи
  * [recordid.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/recordid.php) — ID записи, выводит ID записи, возможно использовать как артикул
  * [tripcost.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/tripcost.php) — Калькулятор поездки, считает стоимость поездки с амортизацией ТС
  * [sticker.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/sticker.php) — Генератор картинки с нужными полями (Например наклеек для термопринтера)
  * [images.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/images.php) — Модификация стандартного поля "Набор изображений", добавляет функцию "Сделать фото" для телефона сразу при добавлении записи
  * [parentsum.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/parentsum.php) — Сумма родительских записей, считает сумму родительских записей (в вложенном типе контента)
* **Виджеты:**
  * [crm_chart](https://github.com/Jestik/crm-icms2/tree/main/package/system/widgets/crm_chart) — выводит график сделок, есть кое какие настройки, по датам и что выводить
* **Шаблоны вывода списка:**
  * [default_list_tableplus.tpl.php](https://github.com/Jestik/crm-icms2/blob/main/package/templates/modern/content/default_list_tableplus.tpl.php) — стиль для отображения сделок
* **Шаблон виджета:**
  * [crm_chart.tpl.php](https://github.com/Jestik/crm-icms2/blob/main/package/templates/modern/widgets/crm_chart/crm_chart.tpl.php) — стиль для отображения виджета


---

**Инструкции:**

1. Установить архив ([Инструкция](https://docs.instantcms.ru/manual/addons))
2. Создать тип контента (ТК) c системным именем «deals» или другим именем
3. Создать в ТК поле «Приход» (или свое название), опции: Тип: Число, Системное имя, напрмиер «income»
4. Создать поле «Расходы», Тип: CRM, Системное имя, например «expenses», ввести системное имя поля из предыдщего пункта, выбрать валюту
5. Выбрать для отображения списка стиль CRM - [default_list_tableplus.tpl.php](https://github.com/Jestik/crm-icms2/blob/main/package/templates/modern/content/default_list_tableplus.tpl.php) - в этом файле в самом начале нужно указать названия полей для правильного отображения.
6. Всё остальное настраивается в панели администратора и интуитивно понятно.

Предложения, вопросы и обсуждения в теме форума по [ссылке](https://instantcms.ru/forum/mini-crm-na-baze-instantcms2.html).
