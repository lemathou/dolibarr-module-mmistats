<?php
/* Copyright (C) 2022      Mathieu MOULIN		<mathieu@iprospective.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("mmistats@mmistats"));

$fk_usergroup_comm = 2;

$groupby_fields = [
	'year' => [
		'label' => 'Année',
	],
	'month' => [
		'label' => 'Mois',
	],
	'week' => [
		'label' => 'N°Semaine',
	],
	'commercial' => [
		'label' => 'Commercial',
		'sql' => 'su.fk_user',
	],
	'categorie' => [
		'label' => 'Catégorie Produit',
		'sql' => 'kp.fk_categorie',
	],
	'fournisseur' => [
		'label' => 'Fournisseur',
		'sql' => 'p2.fk_soc_fournisseur',
	],
	'pro' => [
		'label' => 'Pro/Particulier',
		'sql' => 'IF(s2.pro=1, "PRO", "")',
	],
];

// Filtres par défaut
if (!isset($filters) || !is_array($filters))
	$filters = [];
$filters['categorie'] = [
	'label' => 'Catégorie produit',
	'list' => [],
	'sql' => 'SELECT rowid, label AS name
		FROM '.MAIN_DB_PREFIX.'categorie
		WHERE type=0
		ORDER BY label',
];
$filters['commercial'] = [
	'label' => 'Commercial',
	'list' => [],
	'sql' => 'SELECT DISTINCT u.rowid, login, CONCAT(u.firstname, " ", u.lastname) AS name, IF(u.statut>0 AND u.lastname<>"Commercial" AND (SELECT 1 FROM '.MAIN_DB_PREFIX.'usergroup_user ug WHERE ug.fk_user=u.rowid AND ug.fk_usergroup='.$fk_usergroup_comm.'), "ACTIF", "Inactif/Autre") AS filter_group
		FROM '.MAIN_DB_PREFIX.'user u
		ORDER BY filter_group, u.lastname',
];
$filters['fournisseur'] = [
	'label' => 'Fournisseur',
	'list' => [],
	'sql' => 'SELECT rowid, nom AS name
		FROM '.MAIN_DB_PREFIX.'societe
		WHERE fournisseur=1
		ORDER BY nom',
];
foreach($filters as $name=>&$filter) {
	if (DEBUG_AFF && DEBUG_SQL)
		echo '<p>'.$filter['sql'].'</p>';
	$q = $db->query($filter['sql']);
	if (DEBUG_AFF && DEBUG_SQL)
		var_dump($q);
	while($r=$q->fetch_object()) {
		$filter['list'][$r->rowid] = $r;
	}
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("MMIStatsCommerciauxArea"));

print load_fiche_titre($langs->trans("MMIStatsCommerciauxArea"), '', 'mmistats@mmistats');


?>
<style type="text/css">
td.int, td.float {
	text-align: right;
}
.odd {
	background-color: #eee;
}
#modes a {
	border: 1px transparent solid;
	padding: 2px 5px;
}
#modes a.on {
	border-color: gray;
	background-color: #ffc;
}
</style>

<div id="modes">
<?php
$mode_aff = [];
foreach($mode_list as $i=>$j) {
	$mode_aff[] = '<a href="?mode='.$i.'"'.($mode==$i ?' class="on"' :'').'>'.$j.'</a>';
}
echo implode('&nbsp;|&nbsp;', $mode_aff);
?>
</div>
<br />

<form method="GET">
<input type="hidden" name="mode" value="<?php echo $mode; ?>" />
<table border="1">
<tr>
	<td><label for="period">Groupage par</label> :</td>
	<td><select name="period"><?php
$period = GETPOST('period');
if (empty($period))
	$period = 'm';
foreach(['w'=>'Semaine', 'm'=>'Mois', 'y'=>'Année', 'a'=>'Tout'] as $i=>$j) {
	echo '<option value="'.$i.'" '.($period==$i ?' selected' :'').'>'.$j.'</option>';
}
?></select>
	<br />
	<select name="groupbymore"><option value="">--</option><?php
$groupbymore = GETPOST('groupbymore');
foreach($groupbymore_fields as $i) {
	$j = $groupby_fields[$i];
	echo '<option value="'.$i.'" '.($groupbymore==$i ?' selected' :'').'>'.$j['label'].'</option>';
}
?></select></td>
	<td><label for="cols[]">Affichage colonnes</label> :</td>
	<td rowspan="3"><select name="cols[]" multiple size="6" style="overflow:auto;"><?php
$cols = GETPOST('cols');
if (!is_array($cols))
	$cols = [];
$cols_empty = empty($cols);
foreach($sqlists as $l) {
	foreach($l['fields'] as $i=>$j) {
		if ($cols_empty && (in_array('default', $j) || in_array('always', $j)))
			$cols[] = $i;
		elseif(!in_array($i, $cols) && in_array('always', $j))
			$cols[] = $i;
		echo '<option value="'.$i.'" '.(in_array($i, $cols) ?' selected' :'').'>'.$j['label'].'</option>';
	}
}
?></select></td>
	<td><input type="submit" name="_refresh" value="Refresh" style="background-color: #dfc;" /></td>
</tr>

<tr>
	<td><label for="commercial">Filtrage par</label> :</td>
	<td><?php
	foreach($filters as $name=>&$filter) {
		${$name} = GETPOST($name);
		//var_dump($filter['list']);
		echo '<select name="'.$name.'">';
		echo '<option value="">-- '.$filter['label'].' --</option>';
		$filter_group = NULL;
		foreach($filter['list'] as $i=>$r) {
			if (isset($r->filter_group) && $filter_group !== $r->filter_group) {
				if ($filter_group !== NULL)
					echo '</optgroup>';
				$filter_group = $r->filter_group;
				echo '<optgroup label="'.$r->filter_group.'">';
			}
			echo '<option value="'.$i.'" '.(${$name}==$i ?' selected' :'').'>'.$r->name.'</option>';
		}
		if ($filter_group !== NULL)
			echo '</optgroup>';
		echo '</select>';
		echo '<br />';
	}
	?></td>
</tr>

<tr>
	<td><label for="year">Période</label> :</td>
	<td><select name="year"><option value="">-- Tout --</option><?php
$year = GETPOST('year');
$sql = '(SELECT DISTINCT YEAR(datec) `year` FROM '.MAIN_DB_PREFIX.'propal)
UNION DISTINCT
(SELECT DISTINCT YEAR(date_creation) `year` FROM '.MAIN_DB_PREFIX.'commande WHERE fk_statut > 0)
UNION DISTINCT
(SELECT DISTINCT YEAR(datef) `year` FROM '.MAIN_DB_PREFIX.'facture WHERE fk_statut > 0)
ORDER BY `year`';
//date_valid
if (DEBUG_AFF && DEBUG_SQL)
	echo '<p>'.$sql.'</p>';
$q = $db->query($sql);
while($r=$q->fetch_object()) {
	echo '<option value="'.$r->year.'" '.($year==$r->year ?' selected' :'').'>'.$r->year.'</option>';
}
?></select></td>
</tr>
</table>
</form>

<br />
<?php

if ($period=='w') {
	$groupby = ['year', 'week'];
}
elseif ($period=='m') {
	$groupby = ['year', 'month'];
}
elseif ($period=='y') {
	$groupby = ['year'];
}
else {
	$groupby = [];
}

//$groupbymore = '';
//$groupbymore = 'commercial';
if ($groupbymore) {
	$groupby_field = $groupby_fields[$groupbymore];
	$groupby[] = $groupbymore;
}

$list = [];

// Devis
foreach ($sqlist as $l) {
	$params = $sqlists[$l];
	
	// SELECT FIELDS
	$sql_select = [];
	foreach($params['fields'] as $i=>$j)
		$sql_select[$i] = $j['sql'].' as `'.$i.'`';
	$sql_groupby = [];
	$sql_orderby = [];
	foreach($params['groupby'][$period] as $i=>$j) {
		$sql_select[$i] = $j.' as '.$i;
		$sql_groupby[$i] = $j;
		$sql_orderby[$i] = $j;
	}
	if ($groupbymore) {
		$i = $groupbymore;
		$j = $groupby_field['sql'];
		$sql_select[$i] = $j.' as '.$i;
		$sql_groupby[$i] = $j;
		$sql_orderby[$i] = $j;
	}
	if (DEBUG_AFF && DEBUG_SQL)
		var_dump($sql_select);
	
	// FROM & JOIN
	$sql_from = $params['from'];
	
	// WHERE & PARAMS
	$sql_where = [];
	if (!empty($params['where']))
		$sql_where[] = $params['where'];
	foreach($params['filters'] as $i=>$j)
		if (!empty(${$i}))
			$sql_where[] = str_replace('$param', ${$i}, $j);
	
	// QUERY
	$sql = 'SELECT '.implode(', ', $sql_select)
		.$sql_from
		.(!empty($sql_where) ?' WHERE '.implode(' AND ', $sql_where) :'')
		.(!empty($sql_groupby) ?' GROUP BY '.implode(', ', $sql_groupby) :'')
		.(!empty($sql_orderby) ?' ORDER BY '.implode(', ', $sql_orderby) :'');
	if (DEBUG_AFF && DEBUG_SQL)
		echo '<p>'.$sql.'</p>';
	//continue;
	$q = $db->query($sql);
	if (DEBUG_AFF && DEBUG_SQL)
		var_dump($q);
	if ($q) {
		while($r=$db->fetch_array($q)) {
			if (DEBUG_AFF && DEBUG_SQL)
				var_dump($r);
			$k = [];
			foreach($groupby as $i)
				$k[] = $r[$i];
			$key = implode('-', $k);
			if (isset($list[$key]))
				$list[$key] = array_merge($list[$key], $r);
			else
				$list[$key] = $r;
		}
	}
}

if (DEBUG_AFF && DEBUG)
	var_dump($list);


$select_cols = [];
foreach($groupby as $i)
	$select_cols[$i] = $groupby_fields[$i];
foreach($sqlist as $l) {
	foreach($sqlists[$l]['fields'] as $i=>$j)
		$select_cols[$i] = $j;
}
if (DEBUG_AFF && DEBUG)
	var_dump($select_cols);

ksort($list);

?>
<table border="1">
<thead>
<tr>
<?php
foreach($select_cols as $i=>$j) {
	if (in_array($i, $cols) || in_array($i, $groupby))
		echo '<th>'.$j['label'].'</th>';
}
?>
</tr>
</thead>
<tbody>
<?php
$total = [];
$nb = 0;
foreach($list as $r) {
	echo '<tr class="'.($nb%2==1 ?'odd' :'').'">';
	foreach($select_cols as $i=>$j) {
		if (in_array($i, $cols) || in_array($i, $groupby)) {
			if (empty($j['unit']) || $j['unit'] != '%')
				$total[$i] += $r[$i];
			if (!empty($j['unit']) && $j['unit']=='€')
				$v = number_format($r[$i], 0, '.', '&nbsp;');
			elseif(isset($filters[$i]))
				$v = is_object($filters[$i]['list'][$r[$i]]) ?$filters[$i]['list'][$r[$i]]->name :$filters[$i]['list'][$r[$i]];
			else
				$v = $r[$i];
			echo '<td class="'.(!empty($j['type']) ?' '.$j['type'] :'').'">'.$v.(!empty($j['unit']) ?'&nbsp;'.$j['unit'] :'').'</td>';
		}
	}
	echo '</tr>';
	$nb++;
}
?>
<tr></tr>
<?php
if ($groupby_nb=count($groupby)) {
	echo '<tr class="'.($nb%2==1 ?'odd' :'').'">';
	echo '<td colspan="'.$groupby_nb.'"><b>TOTAL</b></td>';

	foreach($select_cols as $i=>$j) {
		if (in_array($i, $cols)) {
			if (!empty($j['unit']) && $j['unit']=='€')
				$v = number_format($total[$i], 0, '.', '&nbsp;');
			else
				$v = $total[$i];
			echo '<td class="'.(!empty($j['type']) ?$j['type'] :'').'">'.$v.(!empty($j['unit']) ?'&nbsp;'.$j['unit'] :'').'</td>';
		}
	}
	echo '</tr>';
}
?>
</tbody>
</table>
