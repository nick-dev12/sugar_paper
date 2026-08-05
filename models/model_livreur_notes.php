<?php
/**
 * Notes clients sur les livreurs (1 à 5 étoiles).
 */

if (!function_exists('livreur_livraison_arrivee_column_ok')) {
    require_once __DIR__ . '/model_livreur_tracking.php';
}

require_once __DIR__ . '/model_admin.php';

function livreur_notes_tables_ready()
{
    global $db;
    try {
        $db->query('SELECT 1 FROM livreur_notes_client LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_note_get_for_livraison($commande_id = null, $bl_id = null)
{
    global $db;
    if (!livreur_notes_tables_ready()) {
        return null;
    }

    $commande_id = $commande_id !== null ? (int) $commande_id : 0;
    $bl_id = $bl_id !== null ? (int) $bl_id : 0;

    try {
        if ($bl_id > 0) {
            $stmt = $db->prepare('SELECT * FROM livreur_notes_client WHERE bl_id = :bl_id LIMIT 1');
            $stmt->execute(['bl_id' => $bl_id]);
        } elseif ($commande_id > 0) {
            $stmt = $db->prepare('SELECT * FROM livreur_notes_client WHERE commande_id = :commande_id LIMIT 1');
            $stmt->execute(['commande_id' => $commande_id]);
        } else {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function livreur_note_peut_noter($livraison, $livraison_type)
{
    if (!is_array($livraison) || empty($livraison)) {
        return false;
    }
    if (empty($livraison['livreur_id'])) {
        return false;
    }
    if (!livreur_livraison_arrivee_column_ok()) {
        return false;
    }
    if (empty($livraison['livraison_arrivee_at'])) {
        return false;
    }
    if ($livraison_type === 'facture' && !livreur_bl_livraison_columns_ok()) {
        return false;
    }
    return true;
}

function livreur_note_recalc_moyenne($livreur_id)
{
    global $db;
    $livreur_id = (int) $livreur_id;
    if ($livreur_id < 1 || !livreur_notes_tables_ready()) {
        return ['moyenne' => null, 'nb' => 0];
    }

    try {
        $stmt = $db->prepare('
            SELECT AVG(note) AS moyenne, COUNT(*) AS nb
            FROM livreur_notes_client
            WHERE livreur_id = :livreur_id
        ');
        $stmt->execute(['livreur_id' => $livreur_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nb = (int) ($row['nb'] ?? 0);
        $moyenne = $nb > 0 ? round((float) $row['moyenne'], 2) : null;

        if (admin_has_column('livreur_note_moyenne') && admin_has_column('livreur_nb_notes')) {
            $upd = $db->prepare('
                UPDATE admin
                SET livreur_note_moyenne = :moyenne, livreur_nb_notes = :nb
                WHERE id = :id
            ');
            $upd->execute([
                'moyenne' => $moyenne,
                'nb' => $nb,
                'id' => $livreur_id,
            ]);
        }

        return ['moyenne' => $moyenne, 'nb' => $nb];
    } catch (PDOException $e) {
        return ['moyenne' => null, 'nb' => 0];
    }
}

function livreur_note_build_client_snapshot($livraison, $livraison_type)
{
    $client_user_id = null;
    $client_nom = '';
    $client_telephone = '';
    $numero_reference = '';

    if ($livraison_type === 'facture') {
        $client_nom = trim((string) ($livraison['client_nom'] ?? $livraison['raison_sociale'] ?? ''));
        $client_telephone = trim((string) ($livraison['client_telephone'] ?? ''));
        $numero_reference = trim((string) ($livraison['numero_bl'] ?? ''));
    } else {
        $client_user_id = !empty($livraison['user_id']) ? (int) $livraison['user_id'] : null;
        $prenom = trim((string) ($livraison['client_prenom'] ?? ''));
        $nom = trim((string) ($livraison['client_nom'] ?? ''));
        $client_nom = trim($prenom . ' ' . $nom);
        $client_telephone = trim((string) ($livraison['client_telephone'] ?? $livraison['telephone_livraison'] ?? ''));
        $numero_reference = trim((string) ($livraison['numero_commande'] ?? ''));
    }

    if ($client_nom === '') {
        $client_nom = 'Client';
    }

    return [
        'client_user_id' => $client_user_id,
        'client_nom' => $client_nom,
        'client_telephone' => $client_telephone,
        'numero_reference' => $numero_reference,
    ];
}

function livreur_note_enregistrer($livreur_id, $note, $livraison_type, $commande_id = null, $bl_id = null, array $client_snapshot = [])
{
    global $db;

    $livreur_id = (int) $livreur_id;
    $note = (int) $note;
    $commande_id = $commande_id !== null ? (int) $commande_id : 0;
    $bl_id = $bl_id !== null ? (int) $bl_id : 0;
    $livraison_type = $livraison_type === 'facture' ? 'facture' : 'commande';

    if ($livreur_id < 1 || $note < 1 || $note > 5) {
        return ['ok' => false, 'error' => 'Note invalide (1 à 5).'];
    }
    if ($commande_id < 1 && $bl_id < 1) {
        return ['ok' => false, 'error' => 'Livraison introuvable.'];
    }
    if (!livreur_notes_tables_ready()) {
        return ['ok' => false, 'error' => 'Module de notation indisponible.'];
    }

    $existing = livreur_note_get_for_livraison(
        $commande_id > 0 ? $commande_id : null,
        $bl_id > 0 ? $bl_id : null
    );
    if ($existing) {
        return [
            'ok' => false,
            'error' => 'Cette livraison a déjà été notée.',
            'note' => (int) $existing['note'],
        ];
    }

    try {
        $stmt = $db->prepare('
            INSERT INTO livreur_notes_client (
                livreur_id, commande_id, bl_id, livraison_type, note,
                client_user_id, client_nom, client_telephone, numero_reference, date_creation
            ) VALUES (
                :livreur_id, :commande_id, :bl_id, :livraison_type, :note,
                :client_user_id, :client_nom, :client_telephone, :numero_reference, NOW()
            )
        ');
        $stmt->execute([
            'livreur_id' => $livreur_id,
            'commande_id' => $commande_id > 0 ? $commande_id : null,
            'bl_id' => $bl_id > 0 ? $bl_id : null,
            'livraison_type' => $livraison_type,
            'note' => $note,
            'client_user_id' => !empty($client_snapshot['client_user_id']) ? (int) $client_snapshot['client_user_id'] : null,
            'client_nom' => (string) ($client_snapshot['client_nom'] ?? 'Client'),
            'client_telephone' => (string) ($client_snapshot['client_telephone'] ?? ''),
            'numero_reference' => (string) ($client_snapshot['numero_reference'] ?? ''),
        ]);

        $stats = livreur_note_recalc_moyenne($livreur_id);

        return [
            'ok' => true,
            'note' => $note,
            'moyenne_livreur' => $stats['moyenne'],
            'nb_notes_livreur' => $stats['nb'],
        ];
    } catch (PDOException $e) {
        if (strpos(strtolower($e->getMessage()), 'duplicate') !== false) {
            return ['ok' => false, 'error' => 'Cette livraison a déjà été notée.'];
        }
        return ['ok' => false, 'error' => 'Impossible d\'enregistrer la note.'];
    }
}

function livreur_notes_liste_livreurs()
{
    global $db;
    if (!livreur_notes_tables_ready()) {
        return [];
    }

    try {
        $cols = 'a.id, a.nom, a.prenom, a.email, a.statut, COALESCE(a.role, \'admin\') AS role';
        if (admin_has_column('photo_profil')) {
            $cols .= ', a.photo_profil';
        }
        if (admin_has_column('livreur_note_moyenne')) {
            $cols .= ', a.livreur_note_moyenne, a.livreur_nb_notes';
        }

        $stmt = $db->query("
            SELECT {$cols}
            FROM admin a
            WHERE a.role = 'livreur' OR a.id IN (
                SELECT DISTINCT livreur_id FROM livreur_notes_client
            )
            ORDER BY a.prenom ASC, a.nom ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function livreur_notes_detail_livreur($livreur_id)
{
    global $db;
    $livreur_id = (int) $livreur_id;
    if ($livreur_id < 1 || !livreur_notes_tables_ready()) {
        return [];
    }

    try {
        $stmt = $db->prepare('
            SELECT *
            FROM livreur_notes_client
            WHERE livreur_id = :livreur_id
            ORDER BY date_creation DESC
        ');
        $stmt->execute(['livreur_id' => $livreur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function livreur_note_format_stars_html($note)
{
    $note = max(0, min(5, (int) $note));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $note
            ? '<i class="fas fa-star livreur-note-star livreur-note-star--on" aria-hidden="true"></i>'
            : '<i class="far fa-star livreur-note-star livreur-note-star--off" aria-hidden="true"></i>';
    }
    return $html;
}

function livreur_note_format_moyenne_label($moyenne, $nb = 0)
{
    if ($moyenne === null || $nb < 1) {
        return '—';
    }
    return number_format((float) $moyenne, 1, ',', ' ') . ' / 5';
}
