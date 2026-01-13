<?php
/* Copyright (C) 2012-2013	Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2014		Ferran Marcet		<fmarcet@2byte.es>
 * Copyright (C) 2015       Marcos García       <marcosgdf@gmail.com>
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

/**
 *	\file       mmistats/margin/agentMargins.php
 *	\ingroup    mmistats
 *	\brief      Page des marges par agent commercial
 */

// Load Dolibarr environment
require_once '../main_load.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('companies', 'bills', 'products', 'margins','mmistats@mmistats'));

$mesg = '';

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortorder) {
	$sortorder = "ASC";
}
if ($user->hasRight('margins', 'read', 'all')) {
	$agentid = GETPOST('agentid', 'int');
} else {
	$agentid = $user->id;
}
if (!$sortfield) {
	if ($agentid > 0) {
		$sortfield = "s.nom";
	} else {
		$sortfield = "u.lastname";
	}
}

$startdate = $enddate = '';

$startdateday   = GETPOST('startdateday', 'int');
$startdatemonth = GETPOST('startdatemonth', 'int');
$startdateyear  = GETPOST('startdateyear', 'int');
$enddateday     = GETPOST('enddateday', 'int');
$enddatemonth   = GETPOST('enddatemonth', 'int');
$enddateyear    = GETPOST('enddateyear', 'int');

if (!empty($startdatemonth)) {
	$startdate = dol_mktime(0, 0, 0, $startdatemonth, $startdateday, $startdateyear);
}
if (!empty($enddatemonth)) {
	$enddate = dol_mktime(23, 59, 59, $enddatemonth, $enddateday, $enddateyear);
}

// Security check
$result = restrictedArea($user, 'margins');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$object = new User($db);
$hookmanager->initHooks(array('mmistatsmarginagentlist'));

/*
 * Actions
 */

// None



/*
 * View
 */

$userstatic = new User($db);
$companystatic = new Societe($db);
$invoicestatic = new Facture($db);

$form = new Form($db);

llxHeader('', $langs->trans("Margins").' - '.$langs->trans("Agents"));

$text = $langs->trans("Margins");
//print load_fiche_titre($text);

// Show tabs
$head = marges_prepare_head();

$titre = $langs->trans("Margins");
$picto = 'margin';

print '<form method="post" name="sel" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print dol_get_fiche_head($head, 'agentMarginsEnhanced', $titre, 0, $picto);

print '<table class="border centpercent">';

print '<tr><td class="titlefield">'.$langs->trans('ContactOfInvoice').'</td>';
print '<td class="maxwidthonsmartphone" colspan="4">';
print  img_picto('', 'user') . $form->select_dolusers($agentid, 'agentid', 1, '', $user->hasRight('margins', 'read', 'all') ? 0 : 1, '', '', 0, 0, 0, '', 0, '', 'maxwidth300');
print '</td></tr>';

// Start date
print '<td>'.$langs->trans('DateStart').' ('.$langs->trans("DateValidation").')</td>';
print '<td>';
print $form->selectDate($startdate, 'startdate', '', '', 1, "sel", 1, 1);
print '</td>';
print '<td>'.$langs->trans('DateEnd').' ('.$langs->trans("DateValidation").')</td>';
print '<td>';
print $form->selectDate($enddate, 'enddate', '', '', 1, "sel", 1, 1);
print '</td>';
print '<td style="text-align: center;">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Refresh')).'" />';
print '</td></tr>';
print "</table>";

print dol_get_fiche_end();

print '</form>';


$totalMarginPropal = 0;
$marginRate = '';
$markRate = '';
$cumul_achat_propal = 0;
$cumul_vente_propal = 0;

$propalData=[];

//Build Data Array For Propal
$sqlpropal = "SELECT";
$sqlpropal .= " s.rowid as socid, s.nom as name, s.code_client, s.client,";
$sqlpropal .= " u.rowid as agent, u.login, u.lastname, u.firstname,";
$sqlpropal .= " sum(d.total_ht) as selling_price,";
// Note: qty and buy_price_ht is always positive (if not your database may be corrupted, you can update this)

$sqlpropal .= " sum(".$db->ifsql('(d.total_ht < 0 OR (d.total_ht = 0))', '-1 * d.qty * d.buy_price_ht', 'd.qty * d.buy_price_ht').") as buying_price,";
$sqlpropal .= " sum(".$db->ifsql('(d.total_ht < 0 OR (d.total_ht = 0))', '-1 * (abs(d.total_ht) - (d.buy_price_ht * d.qty))', 'd.total_ht - (d.buy_price_ht * d.qty)').") as marge";

$sqlpropal .= " ,count(DISTINCT p.rowid) as nb";

$sqlpropal .= " FROM ".MAIN_DB_PREFIX."societe as s";
$sqlpropal .= ", ".MAIN_DB_PREFIX."propal as p";
$sqlpropal .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact e ON e.element_id = p.rowid and e.statut = 4 and e.fk_c_type_contact = ".(!getDolGlobalString('AGENT_CONTACT_TYPE') ? -1 : $conf->global->AGENT_CONTACT_TYPE);
$sqlpropal .= ", ".MAIN_DB_PREFIX."propaldet as d";
$sqlpropal .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sqlpropal .= ", ".MAIN_DB_PREFIX."user as u";
$sqlpropal .= " WHERE p.fk_soc = s.rowid";
$sqlpropal .= ' AND p.entity IN ('.getEntity('propal').')';
$sqlpropal .= " AND sc.fk_soc = p.fk_soc";
$sqlpropal .= " AND (d.product_type = 0 OR d.product_type = 1)";
if (getDolGlobalString('AGENT_CONTACT_TYPE')) {
	$sqlpropal .= " AND ((e.fk_socpeople IS NULL AND sc.fk_user = u.rowid) OR (e.fk_socpeople IS NOT NULL AND e.fk_socpeople = u.rowid))";
} else {
	$sqlpropal .= " AND sc.fk_user = u.rowid";
}
//$sqlpropal .= " AND p.fk_statut NOT IN (".$db->sanitize(implode(', ', $invoice_status_except_list)).")";
$sqlpropal .= ' AND s.entity IN ('.getEntity('societe').')';
$sqlpropal .= " AND d.fk_propal = p.rowid";
if ($agentid > 0) {
	if (getDolGlobalString('AGENT_CONTACT_TYPE')) {
		$sqlpropal .= " AND ((e.fk_socpeople IS NULL AND sc.fk_user = ".((int) $agentid).") OR (e.fk_socpeople IS NOT NULL AND e.fk_socpeople = ".((int) $agentid)."))";
	} else {
		$sqlpropal .= " AND sc.fk_user = ".((int) $agentid);
	}
}
if (!empty($startdate)) {
	$sqlpropal .= " AND p.datep >= '".$db->idate($startdate)."'";
}
if (!empty($enddate)) {
	$sqlpropal .= " AND p.datep <= '".$db->idate($enddate)."'";
}
$sqlpropal .= " AND d.buy_price_ht IS NOT NULL";
// We should not use this here. Option ForceBuyingPriceIfNull should have effect only when inserting data. Once data is recorded, it must be used as it is for report.
// We keep it with value ForceBuyingPriceIfNull = 2 for retroactive effect but results are unpredicable.
if (getDolGlobalInt('ForceBuyingPriceIfNull') == 2) {
	$sqlpropal .= " AND d.buy_price_ht <> 0";
}
//if ($agentid > 0) $sql.= " GROUP BY s.rowid, s.nom, s.code_client, s.client, u.rowid, u.login, u.lastname, u.firstname";
//else $sql.= " GROUP BY u.rowid, u.login, u.lastname, u.firstname";
$sqlpropal .= " GROUP BY s.rowid, s.nom, s.code_client, s.client, u.rowid, u.login, u.lastname, u.firstname";
$sqlpropal .= $db->order($sortfield, $sortorder);


dol_syslog('margin::agentMarginsEnhanced', LOG_DEBUG);
$result = $db->query($sqlpropal);
if ($result) {
	$num = $db->num_rows($result);
	if ($num>0) {
		$group_list = array();
		while ($objp = $db->fetch_object($result)) {
			if ($agentid > 0) {
				$group_id = $objp->socid;
			} else {
				$group_id = $objp->agent;
			}

			if (!isset($group_list[$group_id])) {
				if ($agentid > 0) {
					$group_name = $objp->name;
					$companystatic->id = $objp->socid;
					$companystatic->name = $objp->name;
					$companystatic->client = $objp->client;
					$group_htmlname = $companystatic->getNomUrl(1, 'customer');
				} else {
					$group_name = $objp->lastname;
					$userstatic->fetch($objp->agent);
					$group_htmlname = $userstatic->getFullName($langs, 0, 0, 0);
				}
				$group_list[$group_id] = array('name' => $group_name, 'htmlname' => $group_htmlname, 'selling_price' => 0, 'buying_price' => 0, 'marge' => 0 , 'nb'=>0);
			}

			$seller_nb = 1;
//			if ($objp->socid > 0) {
//				// sql nb sellers
//				$sql_seller  = "SELECT COUNT(sc.rowid) as nb";
//				$sql_seller .= " FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc";
//				$sql_seller .= " WHERE sc.fk_soc = ".((int) $objp->socid);
//				$sql_seller .= " LIMIT 1";
//
//				$resql_seller = $db->query($sql_seller);
//				if (!$resql_seller) {
//					dol_print_error($db);
//				} else {
//					if ($obj_seller = $db->fetch_object($resql_seller)) {
//						if ($obj_seller->nb > 0) {
//							$seller_nb = $obj_seller->nb;
//						}
//					}
//				}
//			}

			$group_list[$group_id]['selling_price'] += $objp->selling_price / $seller_nb;
			$group_list[$group_id]['buying_price'] += $objp->buying_price / $seller_nb;
			$group_list[$group_id]['marge'] += $objp->marge / $seller_nb;
			$group_list[$group_id]['nb'] += $objp->nb;
		}

		// sort group array by sortfield
		if ($sortfield == 'u.lastname' || $sortfield == 's.nom') {
			$sortfield = 'name';
		}
		$group_list = dol_sort_array($group_list, $sortfield, $sortorder);

		foreach ($group_list as $group_id => $group_array) {
			$pa = $group_array['buying_price'];
			$pv = $group_array['selling_price'];
			$marge = $group_array['marge'];

			$marginRate = ($pa != 0) ? (100 * $marge / $pa) : '';
			$markRate = ($pv != 0) ? (100 * $marge / $pv) : '';

			$propalData[$group_array['htmlname']]=[
				'pv'=>price(price2num($pv, 'MT')),
				'pa'=>price(price2num($pa, 'MT')),
				'marge'=>price(price2num($marge, 'MT')),
				'nb' => $group_array['nb']
				];
			if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
				$propalData[$group_array['htmlname']]['marginRate']=(($marginRate === '') ? 'n/a' : price(price2num($marginRate, 'MT'))."%");
			}
			if (getDolGlobalString('DISPLAY_MARK_RATES')) {
				$propalData[$group_array['htmlname']]['markRate']=(($markRate === '') ? 'n/a' : price(price2num($markRate, 'MT'))."%");
			}

			$cumul_achat_propal += $pa;
			$cumul_vente_propal += $pv;
		}
	}
} else {
	dol_print_error($db);
}
$db->free($result);




$totalMargin = 0;
$marginRate = '';
$markRate = '';
$invoice_status_except_list = array(Facture::STATUS_DRAFT, Facture::STATUS_ABANDONED);
$contact_type = getDolGlobalInt('AGENT_CONTACT_TYPE');

$sql = "SELECT";
$sql .= " s.rowid as socid, s.nom as name, s.code_client, s.client,";
$sql .= " u.rowid as agent, u.login, u.lastname, u.firstname,";
$sql .= " sum(d.total_ht) as selling_price,";
// Note: qty and buy_price_ht is always positive (if not your database may be corrupted, you can update this)

$sql .= " sum(".$db->ifsql('(d.total_ht < 0 OR (d.total_ht = 0 AND f.type = 2))', '-1 * d.qty * d.buy_price_ht * (d.situation_percent / 100)', 'd.qty * d.buy_price_ht * (d.situation_percent / 100)').") as buying_price,";
$sql .= " sum(".$db->ifsql('(d.total_ht < 0 OR (d.total_ht = 0 AND f.type = 2))', '-1 * (abs(d.total_ht) - (d.buy_price_ht * d.qty * (d.situation_percent / 100)))', 'd.total_ht - (d.buy_price_ht * d.qty * (d.situation_percent / 100))').") as marge";

$sql .= " ,count(DISTINCT f.rowid) as nb";

$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql .= ", ".MAIN_DB_PREFIX."facture as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_contact e ON e.element_id = f.rowid and e.statut = 4 and e.fk_c_type_contact = ".(empty($contact_type) ? -1 : $contact_type);
$sql .= ", ".MAIN_DB_PREFIX."facturedet as d";
if (empty($contact_type)) {
	$sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc";
}
$sql .= ", ".MAIN_DB_PREFIX."user as u";
$sql .= " WHERE f.fk_soc = s.rowid";
$sql .= ' AND f.entity IN ('.getEntity('invoice').')';
if (empty($contact_type)) {
	$sql .= " AND sc.fk_soc = f.fk_soc";
}
$sql .= " AND (d.product_type = 0 OR d.product_type = 1)";
if ($contact_type>0) {
	$sql .= " AND (e.fk_socpeople = u.rowid)";
} else {
	$sql .= " AND sc.fk_user = u.rowid";
}
$sql .= " AND f.fk_statut NOT IN (".$db->sanitize(implode(', ', $invoice_status_except_list)).")";
$sql .= ' AND s.entity IN ('.getEntity('societe').')';
$sql .= " AND d.fk_facture = f.rowid";
if ($agentid > 0) {
	if (getDolGlobalString('AGENT_CONTACT_TYPE')) {
		$sql .= " AND ((e.fk_socpeople IS NULL AND sc.fk_user = ".((int) $agentid).") OR (e.fk_socpeople IS NOT NULL AND e.fk_socpeople = ".((int) $agentid)."))";
	} else {
		$sql .= " AND sc.fk_user = ".((int) $agentid);
	}
}
if (!empty($startdate)) {
	$sql .= " AND f.datef >= '".$db->idate($startdate)."'";
}
if (!empty($enddate)) {
	$sql .= " AND f.datef <= '".$db->idate($enddate)."'";
}
$sql .= " AND d.buy_price_ht IS NOT NULL";
// We should not use this here. Option ForceBuyingPriceIfNull should have effect only when inserting data. Once data is recorded, it must be used as it is for report.
// We keep it with value ForceBuyingPriceIfNull = 2 for retroactive effect but results are unpredicable.
if (getDolGlobalInt('ForceBuyingPriceIfNull') == 2) {
	$sql .= " AND d.buy_price_ht <> 0";
}
//if ($agentid > 0) $sql.= " GROUP BY s.rowid, s.nom, s.code_client, s.client, u.rowid, u.login, u.lastname, u.firstname";
//else $sql.= " GROUP BY u.rowid, u.login, u.lastname, u.firstname";
$sql .= " GROUP BY s.rowid, s.nom, s.code_client, s.client, u.rowid, u.login, u.lastname, u.firstname";
$sql .= $db->order($sortfield, $sortorder);
// TODO: calculate total to display then restore pagination
//$sql.= $db->plimit($conf->liste_limit +1, $offset);


print '<br>';
print '<span class="opacitymedium">'.$langs->trans("MarginPerSaleRepresentativeWarning").'</span><br>';

$param = '';
if (!empty($agentid)) {
	$param .= "&amp;agentid=".urlencode($agentid);
}
if (!empty($startdateday)) {
	$param .= "&amp;startdateday=".urlencode($startdateday);
}
if (!empty($startdatemonth)) {
	$param .= "&amp;startdatemonth=".urlencode($startdatemonth);
}
if (!empty($startdateyear)) {
	$param .= "&amp;startdateyear=".urlencode($startdateyear);
}
if (!empty($enddateday)) {
	$param .= "&amp;enddateday=".urlencode($enddateday);
}
if (!empty($enddatemonth)) {
	$param .= "&amp;enddatemonth=".urlencode($enddatemonth);
}
if (!empty($enddateyear)) {
	$param .= "&amp;enddateyear=".urlencode($enddateyear);
}

$totalMargin = 0;
$marginRate = '';
$markRate = '';
$total_nb_propal=0;
$total_nb_facture=0;
dol_syslog('margin::agentMarginsEnhanced', LOG_DEBUG);
$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);

	print '<br>';
	print_barre_liste($langs->trans("MarginDetails"), $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, '', $num, $num, '', 0, '', '', 0, 1);

	if (getDolGlobalString('MARGIN_TYPE') == "1") {
		$labelcostprice = 'FactureBuyingPrice';
		$labelcostpricePropal = 'PropalBuyingPrice';
	} else { // value is 'costprice' or 'pmp'
		$labelcostprice = 'FactureCostPrice';
		$labelcostpricePropal = 'PropalCostPrice';
	}

	$moreforfilter = '';


	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

	print '<tr class="liste_titre">';
	if ($agentid > 0) {
		print_liste_field_titre("Customer", $_SERVER["PHP_SELF"], "s.nom", "", $param, '', $sortfield, $sortorder);
	} else {
		print_liste_field_titre("SalesRepresentative", $_SERVER["PHP_SELF"], "u.lastname", "", $param, '', $sortfield, $sortorder);
	}

	print_liste_field_titre("PropalSellingPrice", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre("PropalNb", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre($labelcostpricePropal, $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre("PropalMargin", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
		print_liste_field_titre("PropalMarginRate", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	}
	if (getDolGlobalString('DISPLAY_MARK_RATES')) {
		print_liste_field_titre("PropalMarkRate", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	}


	print_liste_field_titre("FactureSellingPrice", $_SERVER["PHP_SELF"], "selling_price", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre("FactureNb", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre($labelcostprice, $_SERVER["PHP_SELF"], "buying_price", "", $param, '', $sortfield, $sortorder, 'right ');
	print_liste_field_titre("FactureMargin", $_SERVER["PHP_SELF"], "marge", "", $param, '', $sortfield, $sortorder, 'right ');
	if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
		print_liste_field_titre("FactureMarginRate", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	}
	if (getDolGlobalString('DISPLAY_MARK_RATES')) {
		print_liste_field_titre("FactureMarkRate", $_SERVER["PHP_SELF"], "", "", $param, '', $sortfield, $sortorder, 'right ');
	}
	print "</tr>\n";

	if ($num > 0) {
		$group_list = array();
		while ($objp = $db->fetch_object($result)) {
			if ($agentid > 0) {
				$group_id = $objp->socid;
			} else {
				$group_id = $objp->agent;
			}

			if (!isset($group_list[$group_id])) {
				if ($agentid > 0) {
					$group_name = $objp->name;
					$companystatic->id = $objp->socid;
					$companystatic->name = $objp->name;
					$companystatic->client = $objp->client;
					$group_htmlname = $companystatic->getNomUrl(1, 'customer');
				} else {
					$group_name = $objp->lastname;
					$userstatic->fetch($objp->agent);
					$group_htmlname = $userstatic->getFullName($langs, 0, 0, 0);
				}
				$group_list[$group_id] = array('name' => $group_name, 'htmlname' => $group_htmlname, 'selling_price' => 0, 'buying_price' => 0, 'marge' => 0, 'nb'=>0);
			}

			$seller_nb = 1;
//			if ($objp->socid > 0) {
//				// sql nb sellers
//				$sql_seller  = "SELECT COUNT(sc.rowid) as nb";
//				$sql_seller .= " FROM ".MAIN_DB_PREFIX."societe_commerciaux as sc";
//				$sql_seller .= " WHERE sc.fk_soc = ".((int) $objp->socid);
//				$sql_seller .= " LIMIT 1";
//
//				$resql_seller = $db->query($sql_seller);
//				if (!$resql_seller) {
//					dol_print_error($db);
//				} else {
//					if ($obj_seller = $db->fetch_object($resql_seller)) {
//						if ($obj_seller->nb > 0) {
//							$seller_nb = $obj_seller->nb;
//						}
//					}
//				}
//			}

			$group_list[$group_id]['selling_price'] += $objp->selling_price / $seller_nb;
			$group_list[$group_id]['buying_price'] += $objp->buying_price / $seller_nb;
			$group_list[$group_id]['marge'] += $objp->marge / $seller_nb;
			$group_list[$group_id]['nb'] += $objp->nb;
		}

		// sort group array by sortfield
		if ($sortfield == 'u.lastname' || $sortfield == 's.nom') {
			$sortfield = 'name';
		}
		$group_list = dol_sort_array($group_list, $sortfield, $sortorder);

		foreach ($group_list as $group_id => $group_array) {


			print '<tr class="oddeven">';
			print "<td>".$group_array['htmlname']."</td>\n";
			//Data From Propal
			print '<td class="nowrap right"><span class="amount">';
			if (isset($propalData[$group_array['htmlname']])) {
				print $propalData[$group_array['htmlname']]['pv'];
			}
			print '</span></td>';
			print '<td class="nowrap right"><span class="amount">';
			if (isset($propalData[$group_array['htmlname']])) {
				print $propalData[$group_array['htmlname']]['nb'];
				$total_nb_propal += (int)$propalData[$group_array['htmlname']]['nb'];
			}
			print '</span></td>';
			print '<td class="nowrap right"><span class="amount">';
			if (isset($propalData[$group_array['htmlname']])) {
				print $propalData[$group_array['htmlname']]['pa'];
			}
			print '</span></td>';
			print '<td class="nowrap right"><span class="amount">';
			if (isset($propalData[$group_array['htmlname']])) {
				print $propalData[$group_array['htmlname']]['marge'];
			}
			print '</span></td>';
			if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
				print '<td class="nowrap right"><span class="amount">';
				if (isset($propalData[$group_array['htmlname']])) {
					print $propalData[$group_array['htmlname']]['marginRate'];
				}
				print '</span></td>';
			}
			if (getDolGlobalString('DISPLAY_MARK_RATES')) {
				print '<td class="nowrap right"><span class="amount">';
				if (isset($propalData[$group_array['htmlname']])) {
					print $propalData[$group_array['htmlname']]['markRate'];
				}
				print '</span></td>';
			}

			$pa = $group_array['buying_price'];
			$pv = $group_array['selling_price'];
			$nb = $group_array['nb'];
			$total_nb_facture += (int)$group_array['nb'];
			$marge = $group_array['marge'];

			$marginRate = ($pa != 0) ? (100 * $marge / $pa) : '';
			$markRate = ($pv != 0) ? (100 * $marge / $pv) : '';


			print '<td class="nowrap right"><span class="amount">'.price(price2num($pv, 'MT')).'</span></td>';
			print '<td class="nowrap right"><span class="amount">'.$nb.'</span></td>';
			print '<td class="nowrap right"><span class="amount">'.price(price2num($pa, 'MT')).'</span></td>';
			print '<td class="nowrap right"><span class="amount">'.price(price2num($marge, 'MT')).'</span></td>';
			if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
				print '<td class="nowrap right">'.(($marginRate === '') ? 'n/a' : price(price2num($marginRate, 'MT'))."%").'</td>';
			}
			if (getDolGlobalString('DISPLAY_MARK_RATES')) {
				print '<td class="nowrap right">'.(($markRate === '') ? 'n/a' : price(price2num($markRate, 'MT'))."%").'</td>';
			}
			print "</tr>\n";

			$cumul_achat += $pa;
			$cumul_vente += $pv;
		}
	}

	// Show total margin
	if (!isset($cumul_achat)) {
		$cumul_achat = 0;
	}
	if (!isset($cumul_vente)) {
		$cumul_vente = 0;
	}
	$totalMargin = $cumul_vente - $cumul_achat;
	$totalMarginPropal = $cumul_vente_propal - $cumul_achat_propal;

	$marginRate = ($cumul_achat != 0) ? (100 * $totalMargin / $cumul_achat) : '';
	$markRate = ($cumul_vente != 0) ? (100 * $totalMargin / $cumul_vente) : '';

	$marginRatePropal = ($cumul_achat_propal != 0) ? (100 * $totalMarginPropal / $cumul_achat_propal) : '';
	$markRatePropal = ($cumul_vente_propal != 0) ? (100 * $totalMarginPropal / $cumul_vente_propal) : '';


	print '<tr class="liste_total">';
	print '<td>';
	print $langs->trans('TotalMargin')."</td>";
	print '<td class="nowrap right">'.price(price2num($cumul_vente_propal, 'MT')).'</td>';
	print '<td class="nowrap right">'.$total_nb_propal.'</td>';
	print '<td class="nowrap right">'.price(price2num($cumul_achat_propal, 'MT')).'</td>';
	print '<td class="nowrap right">'.price(price2num($totalMarginPropal, 'MT')).'</td>';
	if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
		print '<td class="nowrap right">'.(($marginRatePropal === '') ? 'n/a' : price(price2num($marginRatePropal, 'MT'))."%").'</td>';
	}
	if (getDolGlobalString('DISPLAY_MARK_RATES')) {
		print '<td class="nowrap right">'.(($markRatePropal === '') ? 'n/a' : price(price2num($markRatePropal, 'MT'))."%").'</td>';
	}

	print '<td class="nowrap right">'.price(price2num($cumul_vente, 'MT')).'</td>';
	print '<td class="nowrap right">'.$total_nb_facture.'</td>';
	print '<td class="nowrap right">'.price(price2num($cumul_achat, 'MT')).'</td>';
	print '<td class="nowrap right">'.price(price2num($totalMargin, 'MT')).'</td>';
	if (getDolGlobalString('DISPLAY_MARGIN_RATES')) {
		print '<td class="nowrap right">'.(($marginRate === '') ? 'n/a' : price(price2num($marginRate, 'MT'))."%").'</td>';
	}
	if (getDolGlobalString('DISPLAY_MARK_RATES')) {
		print '<td class="nowrap right">'.(($markRate === '') ? 'n/a' : price(price2num($markRate, 'MT'))."%").'</td>';
	}
	print '</tr>';

	print '</table>';
	print '</div>';
} else {
	dol_print_error($db);
}
$db->free($result);

print "\n".'<script type="text/javascript">
$(document).ready(function() {
	console.log("Init some values");
  	$("#totalMargin").html("'.price(price2num($totalMargin, 'MT')).'");
	$("#marginRate").html("'.(($marginRate === '') ? 'n/a' : price(price2num($marginRate, 'MT'))."%").'");
	$("#markRate").html("'.(($markRate === '') ? 'n/a' : price(price2num($markRate, 'MT'))."%").'");
});
</script>'."\n";

// End of page
llxFooter();
$db->close();
