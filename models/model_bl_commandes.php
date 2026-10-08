<?php
/**
 * Factures Invoice (bons_livraison) générées depuis les commandes du site.
 * Une commande = une facture, mise à jour à chaque changement de statut.
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_bl.php';
require_once __DIR__ . '/model_clients_b2b.php';

function bl_commande_column_ok()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db || !bl_tables_available()) {
        return false;
    }
    try {
        $db->query('SELECT commande_id FROM bons_livraison LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function bl_id_for_commande($commande_id)
{
    global $db;
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0 || !bl_commande_column_ok()) {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT id FROM bons_livraison WHERE commande_id = :c ORDER BY id ASC LIMIT 1');
        $stmt->execute(['c' => $commande_id]);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Lignes facture d'une commande (produits + frais de livraison).
 *
 * @return list<array{produit_id:int|null, designation:string, quantite:float, pu:float, total:float}>
 */
function bl_commande_lignes_facture($commande_id, array $commande)
{
    global $db;
    $lignes = [];
    try {
        $stmt = $db->prepare('
            SELECT cp.*, p.nom AS produit_nom_catalogue
            FROM commande_produits cp
            LEFT JOIN produits p ON p.id = cp.produit_id
            WHERE cp.commande_id = :id
            ORDER BY cp.id ASC
        ');
        $stmt->execute(['id' => (int) $commande_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[bl_commande_lignes_facture] ' . $e->getMessage());
        return [];
    }

    foreach ($rows as $r) {
        $nom = trim((string) ($r['nom_produit'] ?? ''));
        if ($nom === '') {
            $nom = trim((string) ($r['produit_nom_catalogue'] ?? ''));
        }
        if ($nom === '') {
            $nom = 'Produit';
        }
        $variante = trim((string) ($r['variante_nom'] ?? ''));
        if ($variante !== '') {
            $nom .= ' (' . $variante . ')';
        }
        $q = (float) ($r['quantite'] ?? 0);
        $pu = (float) ($r['prix_unitaire'] ?? 0);
        $total = isset($r['prix_total']) ? (float) $r['prix_total'] : round($q * $pu, 2);
        if ($q <= 0) {
            continue;
        }
        $pid = (int) ($r['produit_id'] ?? 0);
        $lignes[] = [
            'produit_id' => $pid > 0 ? $pid : null,
            'designation' => mb_substr($nom, 0, 500),
            'quantite' => $q,
            'pu' => $pu,
            'total' => round($total, 2),
        ];
    }

    $frais = (float) ($commande['frais_livraison'] ?? 0);
    if ($frais > 0) {
        $lignes[] = [
            'produit_id' => null,
            'designation' => 'Frais de livraison',
            'quantite' => 1,
            'pu' => $frais,
            'total' => $frais,
        ];
    }
    return $lignes;
}

/**
 * Client B2B de la facture : retrouvé par téléphone, sinon créé.
 */
function bl_commande_client_b2b_id(array $commande)
{
    $tel = trim((string) ($commande['user_telephone'] ?? $commande['telephone_livraison'] ?? ''));
    $client = $tel !== '' ? find_client_b2b_by_telephone($tel) : false;
    if ($client) {
        return (int) $client['id'];
    }
    $prenom = trim((string) ($commande['user_prenom'] ?? ''));
    $nom = trim((string) ($commande['user_nom'] ?? ''));
    if ($prenom === '-') {
        $prenom = '';
    }
    $rs = trim($prenom . ' ' . $nom);
    $cid = create_client_b2b([
        'raison_sociale' => $rs !== '' ? $rs : 'Client ' . ($commande['numero_commande'] ?? ''),
        'nom_contact' => $nom,
        'prenom_contact' => $prenom,
        'email' => (string) ($commande['user_email'] ?? ''),
        'telephone' => $tel,
        'adresse' => (string) ($commande['adresse_livraison'] ?? ''),
        'notes' => 'Créé depuis la commande ' . ($commande['numero_commande'] ?? ''),
        'statut' => 'actif',
    ]);
    return $cid ? (int) $cid : 0;
}

/**
 * Crée ou met à jour la facture liée à une commande (montants, lignes, payée / impayée).
 * Une commande annulée retire sa facture (sauf si elle est déjà payée).
 *
 * @return int ID de la facture, 0 si aucune
 */
function bl_sync_from_commande($commande_id)
{
    global $db;
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0 || !bl_commande_column_ok()) {
        return 0;
    }
    require_once __DIR__ . '/model_commandes_admin.php';
    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        return 0;
    }

    $bl_id = bl_id_for_commande($commande_id);
    $statut = (string) ($commande['statut'] ?? '');

    if ($statut === 'annulee') {
        if ($bl_id > 0 && !bl_est_facture_payee($bl_id)) {
            bl_delete_for_commande($commande_id);
        }
        return 0;
    }

    $lignes = bl_commande_lignes_facture($commande_id, $commande);
    if (empty($lignes)) {
        return $bl_id;
    }
    $total = 0.0;
    foreach ($lignes as $l) {
        $total += $l['total'];
    }
    $total = round($total, 2);
    $payee = $statut === 'paye';
    $date_commande = !empty($commande['date_commande']) ? (string) $commande['date_commande'] : date('Y-m-d H:i:s');
    $date_bl = date('Y-m-d', strtotime($date_commande) ?: time());
    $notes = 'Commande ' . ($commande['numero_commande'] ?? '');
    $col_payee = bl_col_facture_payee_ok();

    try {
        $db->beginTransaction();

        if ($bl_id <= 0) {
            $client_id = bl_commande_client_b2b_id($commande);
            if ($client_id <= 0) {
                $db->rollBack();
                return 0;
            }
            $numero = generate_numero_bl();
            $stmt = $db->prepare('
                INSERT INTO bons_livraison (numero_bl, client_b2b_id, devis_id, commande_id, admin_createur_id, statut, date_bl, total_ht, notes, date_creation)
                VALUES (:numero_bl, :client_b2b_id, NULL, :commande_id, NULL, \'brouillon\', :date_bl, :total_ht, :notes, :date_creation)
            ');
            $stmt->execute([
                'numero_bl' => $numero,
                'client_b2b_id' => $client_id,
                'commande_id' => $commande_id,
                'date_bl' => $date_bl,
                'total_ht' => $total,
                'notes' => $notes,
                'date_creation' => $date_commande,
            ]);
            $bl_id = (int) $db->lastInsertId();
            if (bl_adresse_client_column_ok()) {
                $adr = trim((string) ($commande['adresse_livraison'] ?? ''));
                $db->prepare('UPDATE bons_livraison SET adresse_client = :a WHERE id = :id')->execute([
                    'a' => $adr !== '' ? $adr : null,
                    'id' => $bl_id,
                ]);
            }
        } else {
            $db->prepare('UPDATE bons_livraison SET total_ht = :t, date_modification = NOW() WHERE id = :id')->execute([
                't' => $total,
                'id' => $bl_id,
            ]);
            $db->prepare('DELETE FROM bl_lignes WHERE bl_id = :id')->execute(['id' => $bl_id]);
        }

        $ins = $db->prepare('
            INSERT INTO bl_lignes (bl_id, produit_id, designation, quantite, prix_unitaire_ht, total_ligne_ht, ordre)
            VALUES (:bl_id, :produit_id, :designation, :quantite, :pu, :total, :ordre)
        ');
        foreach ($lignes as $i => $l) {
            $ins->execute([
                'bl_id' => $bl_id,
                'produit_id' => $l['produit_id'],
                'designation' => $l['designation'],
                'quantite' => $l['quantite'],
                'pu' => $l['pu'],
                'total' => $l['total'],
                'ordre' => $i,
            ]);
        }

        if ($col_payee) {
            if ($payee) {
                $db->prepare('
                    UPDATE bons_livraison
                    SET facture_bl_payee = 1, date_paiement_bl = COALESCE(date_paiement_bl, NOW())
                    WHERE id = :id
                ')->execute(['id' => $bl_id]);
            } else {
                $db->prepare('
                    UPDATE bons_livraison SET facture_bl_payee = 0, date_paiement_bl = NULL WHERE id = :id
                ')->execute(['id' => $bl_id]);
            }
        }

        $db->commit();
        return $bl_id;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[bl_sync_from_commande] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Synchronisation sans jamais bloquer le flux commande.
 */
function bl_sync_from_commande_safe($commande_id)
{
    try {
        return bl_sync_from_commande($commande_id);
    } catch (Throwable $e) {
        error_log('[bl_sync_from_commande_safe] ' . $e->getMessage());
        return 0;
    }
}

function bl_delete_for_commande($commande_id)
{
    global $db;
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0 || !bl_commande_column_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT id FROM bons_livraison WHERE commande_id = :c');
        $stmt->execute(['c' => $commande_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($ids as $id) {
            $db->prepare('DELETE FROM bl_lignes WHERE bl_id = :id')->execute(['id' => (int) $id]);
            $db->prepare('DELETE FROM bons_livraison WHERE id = :id')->execute(['id' => (int) $id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('[bl_delete_for_commande] ' . $e->getMessage());
        return false;
    }
}

/**
 * Répercute le paiement saisi sur la facture vers la commande liée.
 */
function bl_propager_paiement_vers_commande($bl_id, $payee)
{
    if (!bl_commande_column_ok()) {
        return;
    }
    $bl = get_bl_by_id((int) $bl_id);
    $commande_id = (int) ($bl['commande_id'] ?? 0);
    if ($commande_id <= 0) {
        return;
    }
    require_once __DIR__ . '/model_commandes_admin.php';
    $commande = get_commande_by_id($commande_id);
    if (!$commande) {
        return;
    }
    $statut = (string) ($commande['statut'] ?? '');
    if ($payee && $statut !== 'paye' && $statut !== 'annulee') {
        update_commande_statut($commande_id, 'paye');
    } elseif (!$payee && $statut === 'paye') {
        update_commande_statut($commande_id, 'livree');
    }
}
