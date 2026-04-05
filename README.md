# crm-icms2
CRM for InstantCMS 2

<p><strong>Набор дополнений для организации мини CRM системы на базе InstantCMS 2</strong></p>

<p><strong>Краткие пояснения:</strong></p>

<ul>
	<li><strong>Поля:</strong>

	<ul>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/expenses.php">expenses.php</a>&nbsp;- считает расходы и доходы учестников сделки</li>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/multifile.php">multifile.php</a>&nbsp;- позволяет загружать файлы к записи</li>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/phone.php">phone.php</a>&nbsp;- выводит телефон и мессендждеры</li>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/profit.php">profit.php</a>&nbsp;- считает сумму дочерних записей типа контента</li>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/system/fields/recordid.php">recordid.php</a>&nbsp;- выводит ID записи, возможно использовать как артикул</li>
	</ul>
	</li>
	<li><strong>Виджеты</strong>
	<ul>
		<li><a href="https://github.com/Jestik/crm-icms2/tree/main/package/system/widgets/crm_chart">crm_chart</a>&nbsp;- выводит график сделок</li>
	</ul>
	</li>
	<li><strong>Шаблоны вывода списка:</strong>
	<ul>
		<li><a href="https://github.com/Jestik/crm-icms2/blob/main/package/templates/modern/content/default_list_tableplus.tpl.php">default_list_tableplus.tpl.php</a>&nbsp;- стиль для отображения сделок</li>
	</ul>
	</li>
</ul>

<p><strong>Инструкции:</strong></p>

<ol>
	<li>Установить архив (<a href="https://docs.instantcms.ru/manual/addons" target="_blank">Инструкция</a>)</li>
	<li>Создать тип контента c системным именем &laquo;deals&raquo;</li>
	<li>Создать в ТК поле &laquo;Приход&raquo; (или свое название), опции: Тип: Число, Системное имя: строго &laquo;income&raquo;</li>
	<li>Создать поле &laquo;Расходы&raquo;, Тип: CRM, Системное имя: &laquo;expenses&raquo;</li>
</ol>

<p>Предложения и обсуждения в теме форума по <a href="https://instantcms.ru/forum/mini-crm-na-baze-instantcms2.html">ссылке</a>.</p>

