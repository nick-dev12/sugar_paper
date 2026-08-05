<?php
/**
 * Manifeste ordonné des migrations (ajouts uniquement, sans scripts destructifs).
 * Les bundles (comptes_rh, invoice_bl) incluent déjà leurs sous-scripts.
 */
return [
    [
        'id' => 'production_ajouts',
        'label' => 'Tables et colonnes de base (production)',
        'script' => 'run_migration_production_ajouts.php',
    ],
    [
        'id' => 'colonnes_manquantes',
        'label' => 'Colonnes manquantes e-commerce',
        'script' => 'run_alter_colonnes_manquantes.php',
    ],
    [
        'id' => 'zones_livraison_cp',
        'label' => 'Zones livraison commandes personnalisées',
        'script' => 'run_add_zone_livraison_commandes_personnalisees.php',
    ],
    [
        'id' => 'variantes_surcouts',
        'label' => 'Variantes produits et surcoûts',
        'script' => 'run_add_variantes_surcouts.php',
    ],
    [
        'id' => 'panier_options',
        'label' => 'Options panier (couleur, taille, variante)',
        'script' => 'run_add_panier_options.php',
    ],
    [
        'id' => 'commande_options',
        'label' => 'Options lignes commande',
        'script' => 'run_add_commande_options.php',
    ],
    [
        'id' => 'commande_manuelle',
        'label' => 'Commandes manuelles admin',
        'script' => 'run_add_commande_manuelle.php',
    ],
    [
        'id' => 'commande_produits_nom',
        'label' => 'Nom produit sur lignes commande',
        'script' => 'run_add_commande_produits_nom.php',
    ],
    [
        'id' => 'commande_personnalisee_image',
        'label' => 'Image référence commandes personnalisées',
        'script' => 'run_add_commande_personnalisee_image.php',
    ],
    [
        'id' => 'prix_commandes_personnalisees',
        'label' => 'Prix commandes personnalisées',
        'script' => 'run_add_prix_commandes_personnalisees.php',
    ],
    [
        'id' => 'statut_paye',
        'label' => 'Statut payé sur commandes',
        'script' => 'run_add_statut_paye_commandes.php',
    ],
    [
        'id' => 'factures',
        'label' => 'Table factures commandes',
        'script' => 'run_add_factures.php',
    ],
    [
        'id' => 'factures_token',
        'label' => 'Token public factures',
        'script' => 'run_add_factures_token.php',
    ],
    [
        'id' => 'devis',
        'label' => 'Module devis',
        'script' => 'run_add_devis.php',
    ],
    [
        'id' => 'contacts',
        'label' => 'Table contacts',
        'script' => 'run_add_contacts.php',
    ],
    [
        'id' => 'google_auth',
        'label' => 'Colonnes Firebase / Google Auth',
        'script' => 'run_migrate_google_auth.php',
    ],
    [
        'id' => 'stock_mouvements',
        'label' => 'Mouvements de stock',
        'script' => 'run_add_stock_mouvements.php',
    ],
    [
        'id' => 'invoice_bl',
        'label' => 'Module factures B2B / bons de livraison',
        'script' => 'run_migrate_invoice_bl.php',
        'bundle' => true,
    ],
    [
        'id' => 'devis_bl_columns',
        'label' => 'Colonnes devis / BL (adresse client, TVA)',
        'script' => 'run_add_devis_bl_columns_complement.php',
    ],
    [
        'id' => 'devis_bl_remise',
        'label' => 'Remise globale devis / BL',
        'script' => 'run_add_devis_bl_remise_globale.php',
    ],
    [
        'id' => 'admin_role_contable',
        'label' => 'Rôle contable admin (legacy)',
        'script' => 'run_alter_admin_role_contable.php',
    ],
    [
        'id' => 'admin_roles_trois',
        'label' => 'Rôles admin (admin, utilisateur, livreur)',
        'script' => 'run_alter_admin_roles_trois.php',
    ],
    [
        'id' => 'admin_tracabilite_interactions',
        'label' => 'Traçabilité admin (devis, commandes, B2B)',
        'script' => 'run_add_admin_tracabilite_interactions.php',
    ],
    [
        'id' => 'tracabilite_produits_stock',
        'label' => 'Traçabilité produits, catégories, stock',
        'script' => 'run_add_tracabilite_produits_categories_stock.php',
    ],
    [
        'id' => 'logos',
        'label' => 'Table logos partenaires',
        'script' => 'run_add_logos.php',
    ],
    [
        'id' => 'caisse_tables',
        'label' => 'Tables caisse magasin',
        'script' => 'run_add_caisse_tables.php',
    ],
    [
        'id' => 'comptes_rh',
        'label' => 'Module comptes / RH / bulletins de paie',
        'script' => 'run_migrate_comptes_rh.php',
        'bundle' => true,
    ],
    [
        'id' => 'livreur_tracking',
        'label' => 'Suivi GPS livreurs',
        'script' => 'run_add_livreur_tracking.php',
    ],
    [
        'id' => 'livreur_positions_fk',
        'label' => 'Correction FK positions livreur',
        'script' => 'run_fix_livreur_positions_fk.php',
    ],
    [
        'id' => 'admin_photo_profil',
        'label' => 'Photo de profil comptes admin',
        'script' => 'run_add_admin_photo_profil.php',
    ],
    [
        'id' => 'livraison_arrivee',
        'label' => 'Confirmation arrivée livreur',
        'script' => 'run_add_livraison_arrivee.php',
    ],
    [
        'id' => 'livreur_notes_client',
        'label' => 'Notes clients sur livreurs',
        'script' => 'run_add_livreur_notes_client.php',
    ],
];
