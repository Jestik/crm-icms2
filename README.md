# crm-icms2
CRM for InstantCMS 2

**Набор дополнений для организации мини CRM системы на базе InstantCMS 2**

**Краткие пояснения:**

* **Поля:**
  * [expenses.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/expenses.php) — считает расходы и доходы участников сделки
  * [multifile.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/multifile.php) — позволяет загружать файлы к записи
  * [phone.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/phone.php) — выводит телефон и мессенджеры
  * [profit.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/profit.php) — считает сумму дочерних записей типа контента
  * [recordid.php](https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/recordid.php) — выводит ID записи, возможно использовать как артикул
* **Виджеты:**
  * [crm_chart](https://github.com/Jestik/crm-icms2/tree/main/package/system/widgets/crm_chart) — выводит график сделок
* **Шаблоны вывода списка:**
  * [default_list_tableplus.tpl.php](https://github.com/Jestik/crm-icms2/blob/main/package/templates/modern/content/default_list_tableplus.tpl.php) — стиль для отображения сделок

---

**Инструкции:**

1. Установить архив ([Инструкция](https://docs.instantcms.ru/manual/addons))
2. Создать тип контента c системным именем «deals»
3. Создать в ТК поле «Приход» (или свое название), опции: Тип: Число, Системное имя: строго «income»
4. Создать поле «Расходы», Тип: CRM, Системное имя: «expenses»
5. Всё остальное настраивается в панели администратора и интуитивно понятно.

Предложения, вопросы и обсуждения в теме форума по [ссылке](https://instantcms.ru/forum/mini-crm-na-baze-instantcms2.html).
