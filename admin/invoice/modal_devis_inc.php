<?php if (!admin_can_devis()) { return; } ?>
    <div id="modal-devis" class="modal-commande-manuelle invoice-form-modal <?php echo $show_modal_devis ? 'modal-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="modal-devis-title">
        <div class="modal-commande-manuelle-backdrop" id="modal-devis-backdrop"></div>
        <div class="modal-commande-manuelle-content">
            <div class="modal-commande-manuelle-header">
                <h2 id="modal-devis-title"><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($devis_form_title ?? 'Nouveau devis'); ?></h2>
                <button type="button" class="modal-commande-manuelle-close" id="modal-devis-close" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-commande-manuelle-body">
                <?php if ($devis_erreur): ?>
                    <div class="message error modal-commande-erreur">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($devis_erreur); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($devis_form_action ?? '../devis/create.php'); ?>" id="form-devis">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'] ?? ''); ?>">
                    <?php if (!empty($devis_form_is_edit) && !empty($devis_edit_id)): ?>
                    <input type="hidden" name="devis_id" value="<?php echo (int) $devis_edit_id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="user_id" id="user_id" value="<?php echo htmlspecialchars($devis_post['user_id'] ?? ''); ?>">
                    <div class="form-commande-manuelle-grid">
                        <div class="form-commande-manuelle-col form-col-articles">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-search"></i>
                                    <h3>Rechercher un produit</h3>
                                </div>
                                <div class="form-group search-group">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="search-produit" placeholder="Tapez le nom du produit..." autocomplete="off">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-loading" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span>
                                    </div>
                                    <div id="search-produit-results" class="search-produit-results" role="listbox" aria-hidden="true"></div>
                                </div>
                            </div>
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Produits du devis</h3>
                                    <span class="lignes-count" id="lignes-count">0 article(s)</span>
                                </div>
                                <div id="lignes-commande" class="lignes-commande lignes-commande-devis-wrap">
                                    <div class="ligne-commande-head ligne-commande-head-devis ligne-commande-head-invoice" id="lignes-head-devis" hidden>
                                        <span class="lch-head-cell">Produit</span>
                                        <span class="lch-head-cell">Quantité</span>
                                        <span class="lch-head-cell">Montant</span>
                                        <span class="lch-head-cell">Total</span>
                                        <span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>
                                    </div>
                                    <div class="lignes-empty" id="lignes-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun produit ajouté. Utilisez la recherche ci-dessus.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-commande-manuelle-col form-col-client">
                            <div class="form-section-card">
                                <div class="form-section-header">
                                    <i class="fas fa-user"></i>
                                    <h3>Informations client</h3>
                                </div>
                                <div class="form-group search-group" style="position:relative;">
                                    <label for="search-client">Client <span class="required">*</span></label>
                                    <div class="search-input-wrapper">
                                        <i class="fas fa-search search-icon"></i>
                                        <span class="search-loading" id="search-client-loading" style="visibility:hidden;"><i class="fas fa-spinner fa-spin"></i></span>
                                        <input type="text" id="search-client" placeholder="Nom ou téléphone (carnet + téléphone)…" autocomplete="off">
                                    </div>
                                    <div id="search-client-results" class="search-produit-results" role="listbox" aria-hidden="true" style="position:absolute; left:0; right:0; top:100%; z-index:100;"></div>
                                    <div id="client-selected" class="client-selected-chip" style="display:none;" aria-hidden="true">
                                        <div class="client-selected-info">
                                            <strong id="client-selected-nom"></strong>
                                            <span id="client-selected-tel"></span>
                                        </div>
                                        <button type="button" id="client-selected-clear" class="client-selected-clear" title="Changer de client" aria-label="Changer de client">&times;</button>
                                    </div>
                                    <input type="hidden" id="client_nom" name="client_nom" value="<?php echo htmlspecialchars($devis_post['client_nom'] ?? ''); ?>">
                                    <input type="hidden" id="client_telephone" name="client_telephone" value="<?php echo htmlspecialchars($devis_post['client_telephone'] ?? ''); ?>">
                                    <p class="form-hint">Suggestions : clients enregistrés + contacts du téléphone (dans l’app). Nouveau : « Nom 07… ».</p>
                                </div>
                                <div class="form-group">
                                    <label for="zone_livraison_id">Adresse de livraison <span class="required">*</span></label>
                                    <select id="zone_livraison_id" name="zone_livraison_id">
                                        <option value="">— Sélectionnez une adresse —</option>
                                        <?php foreach ($zones_livraison as $z): ?>
                                        <option value="<?php echo (int) $z['id']; ?>"
                                            data-adresse="<?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>"
                                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                                            <?php echo (isset($devis_post['zone_livraison_id']) && (int)$devis_post['zone_livraison_id'] === (int)$z['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="custom" <?php echo (isset($devis_post['zone_livraison_id']) && $devis_post['zone_livraison_id'] === 'custom') ? 'selected' : ''; ?>>— Adresse personnalisée —</option>
                                    </select>
                                    <div id="adresse-custom-wrap" class="adresse-custom-wrap" style="display:none; margin-top:10px;">
                                        <textarea id="adresse_livraison_ta" rows="3"><?php echo htmlspecialchars($devis_post['adresse_livraison'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="adresse-zone-display" class="adresse-zone-display" style="display:none; margin-top:8px;"></div>
                                    <input type="hidden" name="adresse_livraison" id="adresse_livraison" value="">
                                    <input type="hidden" name="frais_livraison" id="frais_livraison" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="remise_globale_pct_devis"><i class="fas fa-percent"></i> Réduction globale (%)</label>
                                    <input type="number" name="remise_globale_pct" id="remise_globale_pct_devis" min="0" max="100" step="0.01" placeholder="0"
                                        value="<?php echo htmlspecialchars($devis_post['remise_globale_pct'] ?? '0'); ?>">
                                    <p class="form-hint">Pourcentage appliqué sur le sous-total produits + livraison.</p>
                                </div>
                                <div class="commande-manuelle-recap">
                                    <div class="recap-line"><span>Sous-total produits</span><span id="recap-sous-total">0 FCFA</span></div>
                                    <div class="recap-line"><span>Frais de livraison</span><span id="recap-frais">0 FCFA</span></div>
                                    <div class="recap-line recap-remise-line" id="recap-remise-line-devis" style="display:none;">
                                        <span>Réduction (<span id="recap-remise-pct-devis">0</span> %)</span>
                                        <span id="recap-remise-montant-devis">0 FCFA</span>
                                    </div>
                                    <div class="recap-line recap-total"><span>Total</span><span id="recap-total">0 FCFA</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-commande-manuelle-actions">
                        <button type="button" class="btn-secondary" id="modal-devis-cancel">Annuler</button>
                        <button type="submit" class="btn-primary btn-submit-commande" name="submit_devis">
                            <i class="fas fa-check"></i> <?php echo htmlspecialchars($devis_form_submit_label ?? 'Créer le devis'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
