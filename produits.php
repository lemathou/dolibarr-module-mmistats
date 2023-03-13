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


// Load Dolibarr environment
require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("mmistats@mmistats"));

define('DEBUG_AFF', false);
define('DEBUG', false);
define('DEBUG_SQL', true);

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("MMIStatsProduitsArea"));

print load_fiche_titre($langs->trans("MMIStatsProduitsArea"), '', 'mmistats@mmistats');


//Stats que je souhaiterai avec possibilité de choisir les dates/périodes :

//Type de produits vendus par commercial et pour l'équipe.


// Taux de transformation nombre de devis/nombre de ventes par commercial et pour l'ensemble de l'équipe.

// @todo sql query
$c_type_contact_devis = 31;
$c_type_contact_commande = 91;

$sqlists = [
	'devis' => [
		'fields' => [
			'devis_nb'=> [
				'label'=>'Devis Nb',
				'type'=>'int',
				'sql'=>'COUNT(d.rowid)'
			],
			'devis_gagne_nb'=> [
				'label'=>'Devis gagnés Nb',
				'type'=>'int',
				'sql'=>'SUM(IF(d.fk_statut IN (2, 4), 1, 0))'
			],
			'devis_gagne_nb_p'=> [
				'label'=>'Devis gagnés Nb%',
				'type'=>'float',
				'unit' => '%',
				'sql'=>'ROUND(100*SUM(IF(d.fk_statut IN (2, 4), 1, 0))/COUNT(d.rowid), 2)'
			],
			'devis_mt'=> [
				'label'=>'Devis Mt',
				'type'=>'int',
				'unit'=>'€',
				'sql'=>'ROUND(SUM(d.total_ht))'
			],
			'devis_gagne_mt'=> [
				'label'=>'Devis gagnés Mt',
				'type'=>'int',
				'unit' => '€',
				'sql'=>'ROUND(SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0)))'
			],
			'devis_gagne_mt_p'=> [
				'label'=>'Devis gagnés Mt%',
				'type'=>'int',
				'unit' => '%',
				'sql'=>'ROUND(100*SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0))/SUM(d.total_ht), 2)'
			],
			'devis_perdu_nb'=> [
				'label'=>'Devis perdus Nb',
				'type'=>'int',
				'sql'=>'SUM(IF(d.fk_statut=3, 1, 0))'
			],
			/*'devis_perdu_p'=> [
				'label'=>'Devis perdus %',
				'type'=>'float',
				'unit' => '%',
				'sql'=>'ROUND(100*SUM(IF(d.fk_statut=3, 1, 0))/COUNT(d.rowid), 2)'
			],*/
		],
		'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact_devis.' AND ec.element_id=d.rowid'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=d.fk_soc',
		'parameters' => [
			'year' => 'YEAR(d.datec)="$param"',
			'commercial' => '(ec.fk_socpeople=$param OR su.fk_user=$param)',
		],
		'groupby' => [
			'w' => ['year'=>'YEAR(d.datec)', 'week'=>'LPAD(WEEK(d.datec), 2, "0")'],
			'm' =>  ['year'=>'YEAR(d.datec)', 'month'=>'MONTH(d.datec)'],
			'y' => ['year'=>'YEAR(d.datec)'],
			'a' => [],
		],
	],
	'devis_commande' => [
		'fields' => [
			'devis_commandes_nb'=> [
				'label'=>'Devis en Commande Nb',
				'type'=>'int',
				'sql'=>'COUNT(DISTINCT d.rowid)'
			],
		],
		'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
			.' INNER JOIN '.MAIN_DB_PREFIX.'element_element j ON (j.fk_source=d.rowid AND j.sourcetype="propal" AND j.targettype="commande") OR (j.fk_target=d.rowid AND j.targettype="propal" AND j.sourcetype="commande")'
			.' INNER JOIN '.MAIN_DB_PREFIX.'commande c ON (c.rowid=j.fk_target AND j.targettype="commande") OR (c.rowid=j.fk_source AND j.sourcetype="commande")'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact_devis.' AND ec.element_id=d.rowid'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=d.fk_soc',
		'parameters' => [
			'year' => 'YEAR(d.datec)="$param"',
			'commercial' => '(ec.fk_socpeople=$param OR su.fk_user=$param)',
		],
		'groupby' => [
			'w' => ['year'=>'YEAR(d.datec)', 'week'=>'LPAD(WEEK(d.datec), 2, "0")'],
			'm' =>  ['year'=>'YEAR(d.datec)', 'month'=>'MONTH(d.datec)'],
			'y' => ['year'=>'YEAR(d.datec)'],
			'a' => [],
		],
	],
	'commande_directe' => [
		'fields' => [
			'commandes_directes_nb'=> [
				'label'=>'Commandes directes Nb',
				'type'=>'int',
				'sql'=>'COUNT(DISTINCT c.rowid)'
			],
			'commandes_directes_mt'=> [
				'label'=>'Commandes directes Mt',
				'type'=>'int',
				'unit'=>'€',
				'sql'=>'ROUND(SUM(c.total_ht))'
			],
		],
		'from' => ' FROM '.MAIN_DB_PREFIX.'commande c'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_element j ON (c.rowid=j.fk_target AND j.targettype="commande" AND j.sourcetype="propal") OR (c.rowid=j.fk_source AND j.sourcetype="commande" AND j.targettype="propal")'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact_commande.' AND ec.element_id=c.rowid'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=c.fk_soc'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=c.fk_soc',
		'where' => 'j.rowid IS NULL AND c.fk_statut > 0',
		'parameters' => [
			'year' => 'YEAR(c.date_creation)="$param"',
			'commercial' => '(ec.fk_socpeople=$param OR su.fk_user=$param)',
		],
		'groupby' => [
			'w' => ['year'=>'YEAR(c.date_creation)', 'week'=>'LPAD(WEEK(c.date_creation), 2, "0")'],
			'm' =>  ['year'=>'YEAR(c.date_creation)', 'month'=>'MONTH(c.date_creation)'],
			'y' => ['year'=>'YEAR(c.date_creation)'],
			'a' => [],
		],
	],
	'facture' => [
		'fields' => [
			'facture_nb'=> [
				'label'=>'Factures Nb',
				'type'=>'int',
				'sql'=>'COUNT(DISTINCT f.rowid)'
			],
			'facture_mt'=> [
				'label'=>'Factures Mt',
				'type'=>'int',
				'unit'=>'€',
				'sql'=>'ROUND(SUM(f.total_ht))'
			],
		],
		'from' => ' FROM '.MAIN_DB_PREFIX.'facture f'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_element j ON (f.rowid=j.fk_target AND j.targettype="commande" AND j.sourcetype="propal") OR (f.rowid=j.fk_source AND j.sourcetype="commande" AND j.targettype="propal")'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact_commande.' AND ec.element_id=f.rowid'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=f.fk_soc'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=f.fk_soc',
		'where' => 'j.rowid IS NULL AND f.fk_statut > 0',
		'parameters' => [
			'year' => 'YEAR(f.date_valid)="$param"',
			'commercial' => '(ec.fk_socpeople=$param OR su.fk_user=$param)',
		],
		'groupby' => [
			'w' => ['year'=>'YEAR(f.date_valid)', 'week'=>'LPAD(WEEK(f.date_valid), 2, "0")'],
			'm' =>  ['year'=>'YEAR(f.date_valid)', 'month'=>'MONTH(f.date_valid)'],
			'y' => ['year'=>'YEAR(f.date_valid)'],
			'a' => [],
		],
	],
];

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
	],
];

?>
<style type="text/css">
td.int, td.float {
	text-align: right;
}
.odd {
	background-color: #eee;
}
</style>

<div>
<?php
$mode = GETPOST('mode');
foreach(['devis'=>'Conversion Devis'] as $i=>$j) {
	echo '&nbsp; <a href="?mode='.$i.'"'.($mode==$i ?' class="on"' :'').'>'.$j.'</a>';
}
?>
</div>

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
?></select></td>
	<td><label for="cols[]">Affichage colonnes</label> :</td>
	<td rowspan="3"><select name="cols[]" multiple><?php
$cols = GETPOST('cols');
if (!is_array($cols))
	$cols = ['devis_nb', 'devis_gagne_nb', 'devis_gagne_nb_p'];
foreach($sqlists as $l) {
	foreach($l['fields'] as $i=>$j) {
		echo '<option value="'.$i.'" '.(in_array($i, $cols) ?' selected' :'').'>'.$j['label'].'</option>';
	}
}
?></select></td>
	<td><input type="submit" name="_refresh" value="Refresh" /></td>
</tr>
<tr>
	<td><label for="commercial">Filtrage par</label> :</td>
	<td><select name="commercial"><option value="">-- Tous les commerciaux --</option><?php
$commercial = GETPOST('commercial');
$sql = 'SELECT rowid, login, CONCAT(firstname, " ", lastname) name FROM '.MAIN_DB_PREFIX.'user';
if (DEBUG_AFF && DEBUG_SQL)
	echo '<p>'.$sql.'</p>';
$q = $db->query($sql);
while($r=$q->fetch_object()) {
	echo '<option value="'.$r->rowid.'" '.($commercial==$r->rowid ?' selected' :'').'>'.$r->name.'</option>';
}
?></select></td>
</tr>
<tr>
	<td><label for="year">Période</label> :</td>
	<td><select name="year"><option value="">-- Tout --</option><?php
$year = GETPOST('year');
$sql = '(SELECT DISTINCT YEAR(datec) `year` FROM '.MAIN_DB_PREFIX.'propal)
UNION DISTINCT
(SELECT DISTINCT YEAR(date_creation) `year` FROM '.MAIN_DB_PREFIX.'commande WHERE fk_statut > 0)
ORDER BY `year`';
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

$groupbymore = false;
if ($groupbymore)
	$groupby[] = 'commercial';

$list = [];

$sqlist = ['devis', 'commande_directe', 'facture']; //'devis_commande'

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
		$i = 'commercial';
		$j = 'su.fk_user';
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
	foreach($params['parameters'] as $i=>$j)
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
