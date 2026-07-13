<?php
/**
 * Panneau Contacts — intégré dans admin/invoice/index.php
 * Variables attendues : $contacts, $contacts_success, $contacts_error
 */
?>
<div class="invoice-contacts-panel">
    <?php if (!empty($contacts_success)): ?>
        <div class="message success page-devis-message">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($contacts_success); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($contacts_error)): ?>
        <div class="message error page-devis-message">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($contacts_error); ?></span>
        </div>
    <?php endif; ?>

    <div class="invoice-contacts-toolbar">
        <div class="invoice-panel-search-bar invoice-panel-search-bar--contacts">
            <label class="sr-only" for="search-contacts">Rechercher un contact</label>
            <div class="invoice-panel-search-wrap">
                <i class="fas fa-search invoice-panel-search-ic" aria-hidden="true"></i>
                <input type="search" id="search-contacts" class="invoice-panel-search-input" placeholder="Rechercher nom, téléphone, factures, montant…" autocomplete="off" inputmode="search">
            </div>
        </div>
        <div class="invoice-contacts-actions">
            <button type="button" class="btn-primary" id="btn-import-contacts-invoice" title="Importer depuis le téléphone ou un fichier">
                <i class="fas fa-mobile-alt"></i> Importer
            </button>
            <button type="button" class="btn-primary" id="btn-add-contact-invoice">
                <i class="fas fa-plus"></i> Ajouter
            </button>
        </div>
    </div>

    <div class="section-title invoice-contacts-section-title">
        <h2><i class="fas fa-list"></i> Liste des contacts (<span id="contacts-count-visible"><?php echo min(30, count($contacts)); ?></span> / <span id="contacts-count-matching"><?php echo count($contacts); ?></span>)</h2>
    </div>

    <?php if (empty($contacts)): ?>
        <div class="empty-state page-devis-empty" id="contacts-empty-state">
            <div class="page-devis-empty__ic" aria-hidden="true"><i class="fas fa-address-book"></i></div>
            <h3>Aucun contact</h3>
            <p>Ajoutez des contacts manuellement ou importez-les depuis votre répertoire téléphonique.</p>
        </div>
    <?php else: ?>
        <div class="invoice-panel-table-wrap" id="contacts-table-wrap">
            <table class="data-table invoice-data-table invoice-contacts-table">
                <colgroup>
                    <col class="invoice-col-client">
                    <col class="invoice-col-tel">
                    <col class="invoice-col-montant">
                </colgroup>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th class="invoice-col-num">Payé</th>
                    </tr>
                </thead>
                <tbody id="contacts-list-body">
                    <?php foreach ($contacts as $c): ?>
                        <?php
                        $contact_id = (int) ($c['id'] ?? 0);
                        $nom_complet = trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''));
                        if ($nom_complet === '') {
                            $nom_complet = '—';
                        }
                        $nb_factures = (int) ($c['nb_factures'] ?? 0);
                        $montant_paye = (float) ($c['montant_paye'] ?? 0);
                        $montant_txt = number_format($montant_paye, 0, ',', ' ');
                        $factures_label = $nb_factures . ' facture' . ($nb_factures > 1 ? 's' : '');
                        $search_blob = invoice_tab_search_blob(
                            $nom_complet,
                            (string) $contact_id,
                            $c['nom'] ?? '',
                            $c['prenom'] ?? '',
                            $c['telephone'] ?? '',
                            $c['email'] ?? '',
                            $factures_label,
                            $montant_txt,
                            'fcfa',
                            'payé'
                        );
                        ?>
                        <tr class="invoice-list-item" data-search="<?php echo $search_blob; ?>">
                            <td data-label="Client">
                                <strong class="invoice-cell-primary"><?php echo htmlspecialchars($nom_complet); ?></strong>
                                <span class="invoice-cell-sub"><?php echo htmlspecialchars($factures_label); ?></span>
                            </td>
                            <td data-label="Téléphone" class="invoice-col-tel">
                                <span class="invoice-cell-primary"><?php echo htmlspecialchars($c['telephone']); ?></span>
                                <?php if (!empty($c['email'])): ?>
                                    <span class="invoice-cell-sub"><?php echo htmlspecialchars($c['email']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Payé" class="invoice-col-num">
                                <div class="invoice-contact-amount-stack">
                                    <span class="invoice-cell-primary"><?php echo $montant_txt; ?> FCFA</span>
                                    <?php if ($nb_factures > 0 && $montant_paye <= 0): ?>
                                        <span class="invoice-cell-sub">Aucun paiement</span>
                                    <?php endif; ?>
                                    <button type="button" class="invoice-table-link btn-edit-contact-invoice invoice-contact-edit-btn"
                                        data-id="<?php echo $contact_id; ?>"
                                        data-nom="<?php echo htmlspecialchars($c['nom']); ?>"
                                        data-prenom="<?php echo htmlspecialchars($c['prenom'] ?? ''); ?>"
                                        data-telephone="<?php echo htmlspecialchars($c['telephone']); ?>"
                                        data-email="<?php echo htmlspecialchars($c['email'] ?? ''); ?>">
                                        <i class="fas fa-edit" aria-hidden="true"></i> Modifier
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="invoice-list-no-results" id="contacts-no-results" hidden><i class="fas fa-search"></i> Aucun contact ne correspond à votre recherche.</p>
        <div class="invoice-list-load-more-wrap">
            <button type="button" class="btn-secondary invoice-list-load-more" id="contacts-load-more" hidden>Voir plus</button>
        </div>
    <?php endif; ?>
</div>

<div class="invoice-contacts-modal modal-fullscreen" id="modal-add-contact-invoice">
    <div class="modal-fullscreen-content">
        <div class="modal-fullscreen-header">
            <h2><i class="fas fa-user-plus"></i> Ajouter un contact</h2>
            <button type="button" class="modal-close-btn" id="modal-add-contact-invoice-close">&times;</button>
        </div>
        <div class="modal-fullscreen-body">
            <form method="POST" action="index.php?tab=contacts">
                <input type="hidden" name="add_contact" value="1">
                <div class="form-group">
                    <label>Nom <span style="color:var(--accent-promo,#E5488A);">*</span></label>
                    <input type="text" name="nom" required placeholder="Nom de famille">
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" placeholder="Prénom">
                </div>
                <div class="form-group">
                    <label>Téléphone <span style="color:var(--accent-promo,#E5488A);">*</span></label>
                    <input type="tel" name="telephone" required placeholder="Ex: 77 12 34 56 78">
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#888;font-weight:400;">(optionnel)</span></label>
                    <input type="email" name="email" placeholder="email@exemple.com">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <button type="button" class="btn-cancel" id="modal-add-contact-invoice-cancel">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="invoice-contacts-modal modal-fullscreen" id="modal-edit-contact-invoice">
    <div class="modal-fullscreen-content">
        <div class="modal-fullscreen-header">
            <h2><i class="fas fa-user-edit"></i> Modifier le contact</h2>
            <button type="button" class="modal-close-btn" id="modal-edit-contact-invoice-close">&times;</button>
        </div>
        <div class="modal-fullscreen-body">
            <form method="POST" action="index.php?tab=contacts" id="form-edit-contact-invoice">
                <input type="hidden" name="update_contact" value="1">
                <input type="hidden" name="contact_id" id="edit_contact_id_invoice" value="">
                <div class="form-group">
                    <label>Nom <span style="color:var(--accent-promo,#E5488A);">*</span></label>
                    <input type="text" name="nom" id="edit_nom_invoice" required>
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" id="edit_prenom_invoice">
                </div>
                <div class="form-group">
                    <label>Téléphone <span style="color:var(--accent-promo,#E5488A);">*</span></label>
                    <input type="tel" name="telephone" id="edit_telephone_invoice" required>
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#888;font-weight:400;">(optionnel)</span></label>
                    <input type="email" name="email" id="edit_email_invoice">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    <button type="button" class="btn-cancel" id="modal-edit-contact-invoice-cancel">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" action="index.php?tab=contacts" id="form-import-contacts-invoice" style="display:none;">
    <input type="hidden" name="import_contacts" value="1">
    <input type="hidden" name="import_contacts_data" id="import_contacts_data_invoice">
</form>

<div class="invoice-contacts-modal modal-fullscreen" id="modal-import-contacts-invoice">
    <div class="modal-fullscreen-content">
        <div class="modal-fullscreen-header">
            <h2><i class="fas fa-address-book"></i> Importer des contacts</h2>
            <button type="button" class="modal-close-btn" id="modal-import-contacts-invoice-close">&times;</button>
        </div>
        <div class="modal-fullscreen-body">
            <div class="invoice-import-options">
                <button type="button" class="invoice-import-option" id="btn-import-phone-contacts">
                    <span class="invoice-import-option__ic" aria-hidden="true"><i class="fas fa-mobile-alt"></i></span>
                    <span class="invoice-import-option__txt">
                        <strong>Choisir des contacts</strong>
                        <small id="import-phone-hint">Sélection dans le carnet du téléphone</small>
                    </span>
                </button>

                <button type="button" class="invoice-import-option" id="btn-import-all-phone-contacts" hidden>
                    <span class="invoice-import-option__ic" aria-hidden="true"><i class="fas fa-check-double"></i></span>
                    <span class="invoice-import-option__txt">
                        <strong>Importer tous les contacts</strong>
                        <small id="import-all-phone-hint">Tous les contacts avec numéro (app Sugar Paper)</small>
                    </span>
                </button>

                <label class="invoice-import-option" for="import-contacts-file-invoice">
                    <span class="invoice-import-option__ic" aria-hidden="true"><i class="fas fa-file-import"></i></span>
                    <span class="invoice-import-option__txt">
                        <strong>Depuis un fichier</strong>
                        <small>Fichier .vcf (export téléphone) ou .csv</small>
                    </span>
                    <input type="file" id="import-contacts-file-invoice" accept=".vcf,.csv,text/vcard,text/x-vcard,text/csv,text/plain" hidden>
                </label>
            </div>

            <p class="invoice-import-status" id="import-contacts-status" hidden></p>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="modal-import-contacts-invoice-cancel">Fermer</button>
            </div>
        </div>
    </div>
</div>
