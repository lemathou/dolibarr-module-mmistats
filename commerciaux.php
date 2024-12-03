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

define('DEBUG_AFF', false);
define('DEBUG', false);
define('DEBUG_SQL', true);

//Stats que je souhaiterai avec possibilité de choisir les dates/périodes :
//Taux de transformation nombre de devis/nombre de ventes par commercial et pour l'ensemble de l'équipe.
//Taux de transformation valeurs devis/valeur ventes.

//Type de produits vendus par commercial et pour l'équipe.


// Taux de transformation nombre de devis/nombre de ventes par commercial et pour l'ensemble de l'équipe.

// Types contact internes associés
$c_type_contact = [];
$sql = 'SELECT rowid, code, element'
	.' FROM '.MAIN_DB_PREFIX.'c_type_contact'
	.' WHERE source="internal" AND code="SALESREPFOLL" AND element IN ("propal", "commande", "facture")';
$q = $db->query($sql);
if (DEBUG_AFF && DEBUG_SQL)
	var_dump($q);
if ($q) {
	while($r=$db->fetch_array($q)) {
		$c_type_contact[$r['element']] = $r['rowid'];
	}
}

$mode = GETPOST('mode');
if (empty($mode))
	$mode = 'propal';
$mode_list = [
	'propal'=>'Conversion Devis',
	'product'=>'Conversion Produits',
	'facture'=>'Factures',
	'comm'=>'Action commerciale',
];

if ($mode=='facture') {
	$sqlists = [
		'facture' => [
			'fields' => [
				'facture_nb'=> [
					'label'=>'Factures Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT f.rowid)',
					'always',
				],
				'facture_mt'=> [
					'label'=>'Factures Mt',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(f.total_ht))',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'facture f'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['facture'].' AND ec.element_id=f.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=f.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields s2 ON s2.fk_object=s.rowid',
			'join_more' => '',
			//'where' => 'j.rowid IS NULL AND f.fk_statut > 0',
			'where' => 'f.fk_statut > 0',
			'filters' => [
				'year' => 'YEAR(f.datef)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(f.datef)', 'week'=>'LPAD(WEEK(f.datef), 2, "0")'],
				'm' =>  ['year'=>'YEAR(f.datef)', 'month'=>'LPAD(MONTH(f.datef), 2, "0")'],
				'y' => ['year'=>'YEAR(f.datef)'],
				'a' => [],
			],
		],
	];

	$sqlist = ['facture']; //'devis_commande'

	$groupbymore_fields = ['categorie', 'fournisseur', 'pro'];//, 'categorie'
}
elseif ($mode=='propal') {
	$sqlists = [
		'devis' => [
			'fields' => [
				'devis_nb'=> [
					'label'=>'Devis Nb',
					'type'=>'int',
					'sql'=>'COUNT(d.rowid)',
					'always',
				],
				'devis_gagne_nb'=> [
					'label'=>'Devis gagnés Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))',
					'always',
				],
				'devis_gagne_nb_p'=> [
					'label'=>'Devis gagnés Nb%',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))/COUNT(d.rowid), 2)',
					'default',
				],
				'devis_mt'=> [
					'label'=>'Devis Mt',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(d.total_ht))',
					'always',
				],
				'devis_gagne_mt'=> [
					'label'=>'Devis gagnés Mt',
					'type'=>'int',
					'unit' => '€',
					'sql'=>'ROUND(SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0)))',
				],
				'devis_gagne_mt_p'=> [
					'label'=>'Devis gagnés Mt%',
					'type'=>'int',
					'unit' => '%',
					'sql'=>'ROUND(100*SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0))/SUM(d.total_ht), 2)',
				],
				'devis_perdu_nb'=> [
					'label'=>'Devis perdus Nb',
					'type'=>'int',
					'sql'=>'SUM(IF(d.fk_statut=3, 1, 0))',
				],
				/*'devis_perdu_p'=> [
					'label'=>'Devis perdus %',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*SUM(IF(d.fk_statut=3, 1, 0))/COUNT(d.rowid), 2)',
				],*/
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['propal'].' AND ec.element_id=d.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc',
			'join_more' => '',
			'filters' => [
				'year' => 'YEAR(d.datec)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(d.datec)', 'week'=>'LPAD(WEEK(d.datec), 2, "0")'],
				'm' =>  ['year'=>'YEAR(d.datec)', 'month'=>'LPAD(MONTH(d.datec), 2, "0")'],
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
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['propal'].' AND ec.element_id=d.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc',
			'join_more' => '',
			'filters' => [
				'year' => 'YEAR(d.datec)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
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
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['commande'].' AND ec.element_id=c.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=c.fk_soc',
			'join_more' => '',
			'where' => 'j.rowid IS NULL AND c.fk_statut > 0',
			'filters' => [
				'year' => 'YEAR(c.date_creation)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(c.date_creation)', 'week'=>'LPAD(WEEK(c.date_creation), 2, "0")'],
				'm' =>  ['year'=>'YEAR(c.date_creation)', 'month'=>'LPAD(MONTH(c.date_creation), 2, "0")'],
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
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['facture'].' AND ec.element_id=f.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=f.fk_soc',
			'join_more' => '',
			//'where' => 'j.rowid IS NULL AND f.fk_statut > 0',
			'where' => 'f.fk_statut > 0',
			'filters' => [
				'year' => 'YEAR(f.date_valid)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(f.datef)', 'week'=>'LPAD(WEEK(f.datef), 2, "0")'],
				'm' =>  ['year'=>'YEAR(f.datef)', 'month'=>'LPAD(MONTH(f.datef), 2, "0")'],
				'y' => ['year'=>'YEAR(f.datef)'],
				'a' => [],
			],
		],
	];
	
	$sqlist = ['devis', 'commande_directe', 'facture']; //'devis_commande'
	
	$groupbymore_fields = ['commercial'];
}
elseif ($mode=='product') {
	$sqlists = [
		'devis' => [
			'fields' => [
				'devis_nb'=> [
					'label'=>'Devis Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT d.rowid)',
					'always',
				],
				'devis_gagne_nb'=> [
					'label'=>'Devis gagnés Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))',
					'default',
				],
				'devis_gagne_nb_p'=> [
					'label'=>'Devis gagnés Nb%',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))/COUNT(DISTINCT d.rowid), 2)',
					'default',
				],
				'devis_perdu_nb'=> [
					'label'=>'Devis perdus Nb',
					'type'=>'int',
					'sql'=>'SUM(IF(d.fk_statut=3, 1, 0))',
				],
				'devis_product_qte'=> [
					'label'=>'Produits Qte',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(dd.qty))',
					'always',
				],
				'devis_product_gagne_qte'=> [
					'label'=>'Produits gagnés Qte',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(IF(d.fk_statut IN (2, 4), dd.qty, 0)))',
					'always',
				],
				'devis_product_gagne_qte_p'=> [
					'label'=>'Produits gagnés Qte%',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*SUM(IF(d.fk_statut IN (2, 4), dd.qty, 0))/SUM(dd.qty), 2)',
					'default',
				],
				'devis_product_mt'=> [
					'label'=>'Produits Mt',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(dd.total_ht))',
					'default',
				],
				'devis_product_gagne_mt'=> [
					'label'=>'Produits gagnés Mt',
					'type'=>'int',
					'unit'=>'€',
					'sql'=>'ROUND(SUM(IF(d.fk_statut IN (2, 4), dd.total_ht, 0)))',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'propaldet dd ON dd.fk_propal=d.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=dd.fk_product'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=dd.fk_product'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product kp ON kp.fk_product=dd.fk_product'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['propal'].' AND ec.element_id=d.rowid'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc',
			'join_more' => '',
			'filters' => [
				'year' => 'YEAR(d.datec)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
				'categorie' => '(kp.fk_categorie=$param)',
				'fournisseur' => '(p2.fk_soc_fournisseur=$param)',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(d.datec)', 'week'=>'LPAD(WEEK(d.datec), 2, "0")'],
				'm' =>  ['year'=>'YEAR(d.datec)', 'month'=>'LPAD(MONTH(d.datec), 2, "0")'],
				'y' => ['year'=>'YEAR(d.datec)'],
				'a' => [],
			],
		],
	];
		
	$sqlist = ['devis']; //'devis_commande'
	
	$groupbymore_fields = ['commercial', 'categorie', 'fournisseur'];//, 'categorie'
}
elseif ($mode=='comm') {
	$sqlists = [
		'devis' => [
			'fields' => [
				'devis_nb'=> [
					'label'=>'Devis Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT d.rowid)',
					'always',
				],
				'devis_mt'=> [
					'label'=>'Devis Mt',
					'type'=>'int',
					'sql'=>'ROUND(SUM(d.total_ht))',
					'always',
				],
				'devis_gagne_nb'=> [
					'label'=>'Devis gagnés Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))',
					'default',
				],
				'devis_gagne_mt'=> [
					'label'=>'Devis gagnés Mt',
					'type'=>'int',
					'sql'=>'ROUND(SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0)))',
					'default',
				],
				'devis_gagne_nb_p'=> [
					'label'=>'Devis gagnés Nb%',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*COUNT(DISTINCT IF(d.fk_statut IN (2, 4), d.rowid, NULL))/COUNT(DISTINCT d.rowid), 2)',
					'default',
				],
				'devis_gagne_mt_p'=> [
					'label'=>'Devis gagnés Mt%',
					'type'=>'float',
					'unit' => '%',
					'sql'=>'ROUND(100*SUM(IF(d.fk_statut IN (2, 4), d.total_ht, 0))/SUM(d.total_ht), 2)',
					'default',
				],
				'devis_perdu_nb'=> [
					'label'=>'Devis perdus Nb',
					'type'=>'int',
					'sql'=>'SUM(IF(d.fk_statut=3, 1, 0))',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['propal'].' AND ec.element_id=d.rowid',
			'join_more' => '',
			'filters' => [
				'year' => 'YEAR(d.datec)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupbymore' => [
				'commercial' => 'ec.fk_socpeople',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(d.datec)', 'week'=>'LPAD(WEEK(d.datec), 2, "0")'],
				'm' =>  ['year'=>'YEAR(d.datec)', 'month'=>'LPAD(MONTH(d.datec), 2, "0")'],
				'y' => ['year'=>'YEAR(d.datec)'],
				'a' => [],
			],
		],
		'devis_win' => [
			'fields' => [
				'devis_win_nb'=> [
					'label'=>'Devis Win Nb',
					'type'=>'int',
					'sql'=>'COUNT(d.rowid)',
					'always',
				],
				'devis_win_mt'=> [
					'label'=>'Devis Win Mt',
					'type'=>'int',
					'sql'=>'ROUND(SUM(d.total_ht))',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'propal d'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'element_contact ec ON ec.fk_c_type_contact='.$c_type_contact['propal'].' AND ec.element_id=d.rowid',
			'join_more' => '',
			'where' => 'd.fk_statut IN (2,4)',
			'filters' => [
				'year' => 'YEAR(d.date_signature)="$param"',
				'commercial' => '(ec.fk_socpeople=$param)',
			],
			'groupbymore' => [
				'commercial' => 'ec.fk_socpeople',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(d.date_signature)', 'week'=>'LPAD(WEEK(d.date_signature), 2, "0")'],
				'm' =>  ['year'=>'YEAR(d.date_signature)', 'month'=>'LPAD(MONTH(d.date_signature), 2, "0")'],
				'y' => ['year'=>'YEAR(d.date_signature)'],
				'a' => [],
			],
		],
		'tel_in' => [
			'fields' => [
				'tel_in_nb'=> [
					'label'=>'Tél In Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT a.id)',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'actioncomm a'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=a.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=a.fk_soc',
			'join_more' => '',
			'where' => 'a.code="AC_TEL" AND a.label LIKE "Appel téléphonique entrant"',
			'filters' => [
				'year' => 'YEAR(a.datep)="$param"',
				'commercial' => '(a.fk_user_action=$param)',
			],
			'groupbymore' => [
				'commercial' => 'a.fk_user_action',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(a.datep)', 'week'=>'LPAD(WEEK(a.datep), 2, "0")'],
				'm' =>  ['year'=>'YEAR(a.datep)', 'month'=>'LPAD(MONTH(a.datep), 2, "0")'],
				'y' => ['year'=>'YEAR(a.datep)'],
				'a' => [],
			],
		],
		'tel_out' => [
			'fields' => [
				'tel_out_nb'=> [
					'label'=>'Tél Out Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT a.id)',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'actioncomm a'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=a.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=a.fk_soc',
			'join_more' => '',
			'where' => 'a.code="AC_TEL" AND a.label LIKE "Appel téléphonique sortant"',
			'filters' => [
				'year' => 'YEAR(a.datep)="$param"',
				'commercial' => '(a.fk_user_action=$param)',
			],
			'groupbymore' => [
				'commercial' => 'a.fk_user_action',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(a.datep)', 'week'=>'LPAD(WEEK(a.datep), 2, "0")'],
				'm' =>  ['year'=>'YEAR(a.datep)', 'month'=>'LPAD(MONTH(a.datep), 2, "0")'],
				'y' => ['year'=>'YEAR(a.datep)'],
				'a' => [],
			],
		],
		'propal_sentmail' => [
			'fields' => [
				'propal_sentmail_nb'=> [
					'label'=>'Propal Email Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT a.id)',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'actioncomm a'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=a.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=a.fk_soc',
			'join_more' => '',
			'where' => 'a.code="AC_PROPAL_SENTBYMAIL"',
			'filters' => [
				'year' => 'YEAR(a.datep)="$param"',
				'commercial' => '(a.fk_user_action=$param)',
			],
			'groupbymore' => [
				'commercial' => 'a.fk_user_action',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(a.datep)', 'week'=>'LPAD(WEEK(a.datep), 2, "0")'],
				'm' =>  ['year'=>'YEAR(a.datep)', 'month'=>'LPAD(MONTH(a.datep), 2, "0")'],
				'y' => ['year'=>'YEAR(a.datep)'],
				'a' => [],
			],
		],
		'sms' => [
			'fields' => [
				'sms_nb'=> [
					'label'=>'SMS Nb',
					'type'=>'int',
					'sql'=>'COUNT(DISTINCT a.id)',
					'always',
				],
			],
			'from' => ' FROM '.MAIN_DB_PREFIX.'actioncomm a'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=a.fk_soc'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'societe_commerciaux su ON su.fk_soc=a.fk_soc',
			'join_more' => '',
			'where' => 'a.code="AC_COMPANY_SENTBYSMS"',
			'filters' => [
				'year' => 'YEAR(a.datep)="$param"',
				'commercial' => '(a.fk_user_action=$param)',
			],
			'groupbymore' => [
				'commercial' => 'a.fk_user_action',
			],
			'groupby' => [
				'w' => ['year'=>'YEAR(a.datep)', 'week'=>'LPAD(WEEK(a.datep), 2, "0")'],
				'm' =>  ['year'=>'YEAR(a.datep)', 'month'=>'LPAD(MONTH(a.datep), 2, "0")'],
				'y' => ['year'=>'YEAR(a.datep)'],
				'a' => [],
			],
		],
	];
		
	$sqlist = ['devis', 'devis_win', 'tel_in', 'tel_out', 'propal_sentmail', 'sms']; //'devis_commande'
	
	$groupbymore_fields = ['commercial'];//, 'categorie'
}

// définir des $groupby_fields perso

// définir des $filters perso

require_once 'stats.inc.php';
