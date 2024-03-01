<?php
/* Copyright (C) 2024 Alice Adminson <aadminson@example.com>
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
 * \file    suppliercontract/class/actions_suppliercontract.class.php
 * \ingroup suppliercontract
 * \brief   SupplierContract hook overload
 */

/**
 * Class ActionsSuppliercontract
 */
class ActionsSuppliercontract
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Error code (or message)
     */
    public string $error = '';

    /**
     * @var array Errors
     */
    public array $errors = [];

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public array $results = [];

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public string $resprints;

    /**
     * @var int Priority of hook (50 is used if value is not defined)
     */
    public int $priority;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the addMoreBoxStatsSupplier function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function addMoreRecentObjects(array $parameters, &$object): int
    {
        global $conf, $user, $langs;

        if (preg_match('/thirdpartycomm|thirdpartysupplier/', $parameters['context'])) {
            if (isModEnabled('contrat') && $user->hasRight('contrat', 'lire') && isModEnabled('saturne')) {
                // Load Dolibarr libraries
                require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';

                // Load Saturne libraries
                require_once __DIR__ . '/../../saturne/lib/object.lib.php';

                $countContracts = 0;
                $supplier       = strpos($parameters['context'], 'thirdpartysupplier');
                $contracts      = saturne_fetch_all_object_type('Contrat', 'DESC', 'datec', 0, 0, ['customsql' => 't.fk_soc = ' . $object->id]);
                if (is_array($contracts) && !empty($contracts)) {
                    $nbContracts    = count($contracts);
                    $maxList        = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT');

                    $out  = '<div class="div-table-responsive-no-min">';
                    $out .= '<table class="noborder centpercent lastrecordtable">';

                    $out .= '<tr class="liste_titre">';
                    $out .= '<td colspan="4"><table class="nobordernopadding centpercent"><tr>';
                    $out .= '<td>' . $langs->trans('LastContracts', ($nbContracts <= $maxList ? '' : $maxList)) . '</td>';
                    $out .= '<td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/contrat/list.php?socid=' . $object->id . '">' . $langs->trans('AllContracts') . '<span class="badge marginleftonlyshort">' . $nbContracts . '</span></a></td>';
                    //$out .= '<td class="right" style="width: 20px;"><a href="' . DOL_URL_ROOT . '/contract/stats/index.php?socid=' . $object->id . '">' . img_picto($langs->trans('Statistics'),'stats') . '</a></td>';
                    $out .= '</tr></table></td>';
                    $out .= '</tr>';

                    foreach ($contracts as $contract) {
                        if (($supplier > 0 && empty($contract->ref_supplier)) || ($supplier == 0 && empty($contract->ref_customer))) {
                            continue;
                        }

                        if ($countContracts == $maxList) {
                            break;
                        } else {
                            $countContracts++;
                        }

                        $late = '';
                        $contract->fetch_lines();
                        if (is_array($contract->lines) && !empty($contract->lines)) {
                            foreach ($contract->lines as $line) {
                                if ($contract->statut == Contrat::STATUS_VALIDATED && $line->statut == ContratLigne::STATUS_OPEN) {
                                    if (((!empty($line->date_end) ? $line->date_end : 0) + $conf->contrat->services->expires->warning_delay) < dol_now()) {
                                        $late = img_warning($langs->trans('Late'));
                                    }
                                }
                            }
                        }

                        $out .= '<tr class="oddeven">';
                        $out .= '<td class="nowraponall">';
                        $out .= $contract->getNomUrl(1);
                        if (!empty($contract->ref_customer)) {
                            $out .= '<span class="customer-back" title="'. $langs->trans("Client") . '">' . substr($langs->trans("Client"), 0, 1) . '</span>';
                        }
                        if (!empty($contract->ref_supplier)) {
                            $out .= '<span class="vendor-back" title="' . $langs->trans("Supplier") . '">' . substr($langs->trans("Supplier"), 0, 1) . '</span>';
                        }
                        // Preview
                        $fileDir  = $conf->contrat->multidir_output[$contract->entity] . '/' . dol_sanitizeFileName($contract->ref);
                        $fileList = null;
                        if (!empty($fileDir)) {
                            $fileList = dol_dir_list($fileDir, 'files', 0, '', '(\.meta|_preview.*.*\.png)$', 'date', SORT_DESC);
                        }
                        if (is_array($fileList) && !empty($fileList)) {
                            // Defined relative dir to DOL_DATA_ROOT
                            $relativeDir = '';
                            if ($fileDir) {
                                $relativeDir = preg_replace('/^' . preg_quote(DOL_DATA_ROOT, '/') . '/', '', $fileDir);
                                $relativeDir = preg_replace('/^\//', '', $relativeDir);
                            }
                            // Get list of files stored into database for same relative directory
                            if ($relativeDir) {
                                completeFileArrayWithDatabaseInfo($fileList, $relativeDir);
                                if (!empty($sortfield) && !empty($sortorder)) { // If $sortfield is for example 'position_name', we will sort on the property 'position_name' (that is concat of position+name)
                                    $fileList = dol_sort_array($fileList, $sortfield, $sortorder);
                                }
                            }
                            require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

                            $formFile = new FormFile($this->db);

                            $relativePath = dol_sanitizeFileName($contract->ref) . '/' . dol_sanitizeFileName($contract->ref) . '.pdf';
                            $out .= $formFile->showPreview($fileList, $contract->element, $relativePath);
                        }
                        $out .= $late;
                        $out .= '</td>';
                        $out .= '<td class="nowrap">' . $contract->ref_supplier . '</td>';
                        $out .= '<td class="right" style="width: 80px;"><span title="' . $langs->trans('DateContract') . '">' . dol_print_date($contract->date_contrat, 'day') . '</span></td>';
                        $out .= '<td class="nowraponall right" style="min-width: 60px;">' . $contract->getLibStatut(4) . '</td></tr>';
                    }

                    $out .= '</table>';
                    $out .= '</div>';

                    if ($supplier > 0) {
                        $this->resprints = $out;
                        return 0;
                    }
                }
                if ($countContracts == 0) {
                    $out = '';
                }
                ?>
                <script>
                    jQuery('.fa-suitcase.infobox-contrat').closest('.div-table-responsive-no-min').replaceWith(<?php echo json_encode($out); ?>);
                </script>
                <?php
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function addMoreActionsButtons(array $parameters, &$object): int
    {
        global $langs, $user;

        if (strpos($parameters['context'], 'thirdpartysupplier') !== false) {
            if (isModEnabled('contrat') && $user->hasRight('contrat', 'creer') && $object->fournisseur == Societe::SUPPLIER) {
                $langs->load('suppliercontract@suppliercontract');

                print dolGetButtonAction('', $langs->trans('AddSupplierContract'), 'default', DOL_URL_ROOT . '/contrat/card.php?socid=' . $object->id . '&action=create');
            }
        }

        return 0; // or return 1 to replace standard code
    }
}
