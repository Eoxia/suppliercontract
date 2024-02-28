<?php
/* Copyright (C) 2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    admin/setup.php
 * \ingroup suppliercontract
 * \brief   SupplierContract setup page
 */

// Load SupplierContract environment
if (file_exists('../suppliercontract.main.inc.php')) {
    require_once __DIR__ . '/../suppliercontract.main.inc.php';
} elseif (file_exists('../../suppliercontract.main.inc.php')) {
    require_once __DIR__ . '/../../suppliercontract.main.inc.php';
} else {
    die('Include of suppliercontract main fails');
}

// Load SupplierContract libraries
require_once __DIR__ . '/../lib/suppliercontract.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize view objects
$form = new Form($db);

// Security check - Protection if external user
$permissionToRead = $user->rights->suppliercontract->adminpage->read;
saturne_check_access($permissionToRead);

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'SupplierContract');
$help_url = 'FR:Module_SupplierContract';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkBack = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = suppliercontract_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $title, -1, 'suppliercontract_color@suppliercontract');

$db->close();
llxFooter();
