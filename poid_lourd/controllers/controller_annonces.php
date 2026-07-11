<?php
/**
 * Contrôleur annonces plateforme (super admin)
 */

require_once __DIR__ . '/../models/model_annonces.php';
require_once __DIR__ . '/../models/model_super_admin.php';
require_once __DIR__ . '/../services/send_annonce_notification.php';
require_once __DIR__ . '/controller_super_admin.php';

/**
 * Envoi d'une annonce par le super admin
 * @return array{success:bool,message:string,annonce_id:int}
 */
function process_super_admin_send_annonce() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '', 'annonce_id' => 0];
    }

    $errors = [];
    $sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    $titre = isset($_POST['titre']) ? trim((string) $_POST['titre']) : '';
    $message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';
    $audience = isset($_POST['audience']) ? trim((string) $_POST['audience']) : '';
    $lien_url = isset($_POST['lien_url']) ? trim((string) $_POST['lien_url']) : '';

    if ($sa_id <= 0) {
        $errors[] = 'Session invalide.';
    }
    if ($titre === '' || strlen($titre) < 3) {
        $errors[] = 'Le titre est obligatoire (au moins 3 caractères).';
    }
    if ($message === '' || strlen($message) < 5) {
        $errors[] = 'Le message est obligatoire (au moins 5 caractères).';
    }
    if (!in_array($audience, ['client', 'vendeur'], true)) {
        $errors[] = 'Choisissez une audience : clients ou vendeurs.';
    }
    if ($lien_url !== '' && !preg_match('#^(/|https?://)#i', $lien_url)) {
        $errors[] = 'Le lien doit commencer par / ou http(s)://';
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($csrf)) {
        $errors[] = 'Session expirée ou formulaire invalide. Réessayez.';
    }

    if (!annonces_table_exists()) {
        $errors[] = 'Tables annonces absentes. Exécutez la migration.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors), 'annonce_id' => 0];
    }

    $annonce_id = annonce_create($titre, $message, $audience, $sa_id, $lien_url !== '' ? $lien_url : null);
    if (!$annonce_id) {
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'annonce.', 'annonce_id' => 0];
    }

    $push = send_annonce_push_notification($annonce_id);
    super_admin_log_action(
        $sa_id,
        'annonce_envoyee',
        'annonce',
        $annonce_id,
        $audience . ' — ' . $titre
    );

    return [
        'success' => true,
        'message' => $push['message'],
        'annonce_id' => $annonce_id,
    ];
}
