<?php
/**
 * Notifications push liées aux actions livreur (prise en charge, arrivée).
 * Programmation procédurale uniquement.
 */

if (!function_exists('livreur_notify_get_livreur_label')) {

    function livreur_notify_get_livreur_label($livreur_id) {
        global $db;
        $livreur_id = (int) $livreur_id;
        if ($livreur_id < 1 || !isset($db) || !($db instanceof PDO)) {
            return 'Un livreur';
        }
        try {
            $stmt = $db->prepare('SELECT prenom, nom FROM admin WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $livreur_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return 'Un livreur';
            }
            $label = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));
            return $label !== '' ? $label : 'Un livreur';
        } catch (PDOException $e) {
            return 'Un livreur';
        }
    }

    function livreur_notify_normalize_type($type) {
        if ($type === 'facture') {
            return 'facture';
        }
        if ($type === 'personnalisee') {
            return 'personnalisee';
        }
        return 'commande';
    }

    function livreur_notify_get_client_label($type, $livraison_id) {
        $type = livreur_notify_normalize_type($type);
        $livraison_id = (int) $livraison_id;
        if ($livraison_id < 1) {
            return 'client';
        }

        if ($type === 'facture') {
            if (!function_exists('livreur_get_facture_tracking')) {
                require_once __DIR__ . '/../models/model_livreur_tracking.php';
            }
            $facture = livreur_get_facture_tracking($livraison_id);
            if (!$facture) {
                return 'client';
            }
            $label = trim((string) ($facture['client_nom'] ?? $facture['raison_sociale'] ?? ''));
            return $label !== '' ? $label : 'client';
        }

        if ($type === 'personnalisee') {
            if (!function_exists('livreur_get_cp_tracking')) {
                require_once __DIR__ . '/../models/model_livreur_tracking.php';
            }
            $cp = livreur_get_cp_tracking($livraison_id);
            if (!$cp) {
                return 'client';
            }
            $label = trim(
                (string) ($cp['client_prenom'] ?? $cp['prenom'] ?? '') . ' ' .
                (string) ($cp['client_nom'] ?? $cp['nom'] ?? '')
            );
            return $label !== '' ? $label : 'client';
        }

        if (!function_exists('get_commande_by_id')) {
            require_once __DIR__ . '/../models/model_commandes_admin.php';
        }
        $commande = get_commande_by_id($livraison_id);
        if (!$commande) {
            if (!function_exists('livreur_get_commande_tracking')) {
                require_once __DIR__ . '/../models/model_livreur_tracking.php';
            }
            $commande = livreur_get_commande_tracking($livraison_id);
        }
        if (!$commande) {
            return 'client';
        }
        $label = trim(
            (string) ($commande['client_prenom'] ?? $commande['user_prenom'] ?? '') . ' ' .
            (string) ($commande['client_nom'] ?? $commande['user_nom'] ?? '')
        );
        if ($label === '') {
            $label = trim((string) ($commande['client_nom'] ?? ''));
        }
        return $label !== '' ? $label : 'client';
    }

    /**
     * Push aux admins : un livreur a pris en charge une commande ou une facture.
     *
     * @param 'commande'|'facture' $type
     */
    function notify_admins_livreur_prise_en_charge($type, $livraison_id, $livreur_id, $numero = '') {
        require_once __DIR__ . '/notify_helpers.php';
        notifications_db_bootstrap();
        require_once __DIR__ . '/firebase_push.php';
        require_once __DIR__ . '/../includes/site_url.php';

        $type = livreur_notify_normalize_type($type);
        $livraison_id = (int) $livraison_id;
        $livreur_id = (int) $livreur_id;
        if ($livraison_id < 1 || $livreur_id < 1) {
            return false;
        }

        $livreur_label = livreur_notify_get_livreur_label($livreur_id);
        $client_label = livreur_notify_get_client_label($type, $livraison_id);
        $numero = trim((string) $numero);
        if ($numero === '') {
            $numero = (string) $livraison_id;
        }

        if ($type === 'facture') {
            $title = 'Livreur ' . $livreur_label;
            $body = "Le livreur {$livreur_label} a pris la facture #{$numero} du client {$client_label}.";
            $link_path = '/admin/livreurs/suivi.php?bl_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-prise-bl-' . $numero;
        } elseif ($type === 'personnalisee') {
            $title = 'Livreur ' . $livreur_label;
            $body = "Le livreur {$livreur_label} a pris la commande personnalisée #{$numero} du client {$client_label}.";
            $link_path = '/admin/livreurs/suivi.php?cp_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-prise-cp-' . $numero;
        } else {
            $title = 'Livreur ' . $livreur_label;
            $body = "Le livreur {$livreur_label} a pris la commande #{$numero} du client {$client_label}.";
            $link_path = '/admin/livreurs/suivi.php?commande_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-prise-cmd-' . $numero;
        }

        $base = rtrim(get_site_base_url(), '/');
        $result = firebase_send_notification_to_all_admins($title, $body, [
            'link' => $base . $link_path,
            'numero_commande' => $numero,
            'livraison_type' => $type,
            'livraison_id' => (string) $livraison_id,
            'livreur_id' => (string) $livreur_id,
            'livreur_nom' => $livreur_label,
            'client_nom' => $client_label,
            'tag' => $tag,
        ]);

        return ((int) ($result['success'] ?? 0)) > 0;
    }

    /**
     * Push aux admins : le livreur est arrivé chez le client.
     *
     * @param 'commande'|'facture' $type
     */
    function notify_admins_livreur_arrive($type, $livraison_id, $livreur_id, $numero = '') {
        require_once __DIR__ . '/notify_helpers.php';
        notifications_db_bootstrap();
        require_once __DIR__ . '/firebase_push.php';
        require_once __DIR__ . '/../includes/site_url.php';

        $type = livreur_notify_normalize_type($type);
        $livraison_id = (int) $livraison_id;
        $livreur_id = (int) $livreur_id;
        if ($livraison_id < 1 || $livreur_id < 1) {
            return false;
        }

        $livreur_label = livreur_notify_get_livreur_label($livreur_id);
        $client_label = livreur_notify_get_client_label($type, $livraison_id);
        $numero = trim((string) $numero);
        if ($numero === '') {
            $numero = (string) $livraison_id;
        }

        if ($type === 'facture') {
            $title = 'Livreur ' . $livreur_label . ' arrivé';
            $body = "Le livreur {$livreur_label} est arrivé chez le client {$client_label} (facture #{$numero}).";
            $link_path = '/admin/livreurs/suivi.php?bl_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-arrive-bl-' . $numero;
        } elseif ($type === 'personnalisee') {
            $title = 'Livreur ' . $livreur_label . ' arrivé';
            $body = "Le livreur {$livreur_label} est arrivé chez le client {$client_label} (commande personnalisée #{$numero}).";
            $link_path = '/admin/livreurs/suivi.php?cp_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-arrive-cp-' . $numero;
        } else {
            $title = 'Livreur ' . $livreur_label . ' arrivé';
            $body = "Le livreur {$livreur_label} est arrivé chez le client {$client_label} (commande #{$numero}).";
            $link_path = '/admin/livreurs/suivi.php?commande_id=' . $livraison_id . '&regarder=1';
            $tag = 'livreur-arrive-cmd-' . $numero;
        }

        $base = rtrim(get_site_base_url(), '/');
        $result = firebase_send_notification_to_all_admins($title, $body, [
            'link' => $base . $link_path,
            'numero_commande' => $numero,
            'livraison_type' => $type,
            'livraison_id' => (string) $livraison_id,
            'livreur_id' => (string) $livreur_id,
            'livreur_nom' => $livreur_label,
            'client_nom' => $client_label,
            'tag' => $tag,
        ]);

        return ((int) ($result['success'] ?? 0)) > 0;
    }

    /**
     * Push au client e-commerce : le livreur est arrivé.
     */
    function notify_client_livreur_arrive($commande_id) {
        require_once __DIR__ . '/notify_helpers.php';
        notifications_db_bootstrap();
        require_once __DIR__ . '/../models/model_commandes_admin.php';
        require_once __DIR__ . '/../includes/site_url.php';

        $commande_id = (int) $commande_id;
        if ($commande_id < 1) {
            return false;
        }

        $commande = get_commande_by_id($commande_id);
        if (!$commande) {
            return false;
        }

        $user_id = (int) ($commande['user_id'] ?? 0);
        if ($user_id < 1) {
            return false;
        }

        $numero = (string) ($commande['numero_commande'] ?? $commande_id);
        $title = 'Votre livreur est arrivé';
        $body = "Le livreur est arrivé pour votre commande #{$numero}. Préparez-vous à réceptionner votre colis.";
        $base = rtrim(get_site_base_url(), '/');
        $link = $base . '/user/commande-categorie.php?commande_id=' . $commande_id;

        require_once __DIR__ . '/notify_helpers.php';
        $result = notifications_send_user_push($user_id, $title, $body, [
            'link' => $link,
            'commande_id' => (string) $commande_id,
            'statut' => 'livraison_en_cours',
            'numero_commande' => $numero,
            'tag' => 'livreur-arrive-' . $numero,
        ], 'livreur_arrive');

        return ((int) ($result['success'] ?? 0)) > 0;
    }

    /**
     * Push au client : le livreur est arrivé (commande personnalisée).
     */
    function notify_client_livreur_arrive_cp($cp_id) {
        require_once __DIR__ . '/notify_helpers.php';
        notifications_db_bootstrap();
        require_once __DIR__ . '/../includes/site_url.php';
        if (!function_exists('livreur_get_cp_tracking')) {
            require_once __DIR__ . '/../models/model_livreur_tracking.php';
        }

        $cp_id = (int) $cp_id;
        if ($cp_id < 1) {
            return false;
        }

        $cp = livreur_get_cp_tracking($cp_id);
        if (!$cp) {
            return false;
        }

        $user_id = (int) ($cp['user_id'] ?? 0);
        if ($user_id < 1) {
            return false;
        }

        $numero = livreur_cp_numero($cp_id);
        $title = 'Votre livreur est arrivé';
        $body = "Le livreur est arrivé pour votre commande personnalisée {$numero}.";
        $base = rtrim(get_site_base_url(), '/');
        $link = $base . '/user/commande-personnalisee-details.php?id=' . $cp_id;
        $suivi = livreur_client_public_suivi_url_cp($cp_id, $user_id, false);
        if ($suivi) {
            $link = $suivi;
        }

        $result = notifications_send_user_push($user_id, $title, $body, [
            'link' => $link,
            'commande_perso_id' => (string) $cp_id,
            'statut' => 'livraison_en_cours',
            'numero_commande' => $numero,
            'tag' => 'livreur-arrive-cp-' . $numero,
        ], 'livreur_arrive');

        return ((int) ($result['success'] ?? 0)) > 0;
    }

    /**
     * Enfile les push « livreur arrivé » (admin + client) — ne bloque pas la réponse HTTP.
     *
     * @param 'commande'|'facture' $type
     * @return bool
     */
    function livreur_enqueue_arrive_notifications($type, $livraison_id, $livreur_id, $numero = '') {
        require_once __DIR__ . '/notify_queue.php';

        $type = livreur_notify_normalize_type($type);
        $livraison_id = (int) $livraison_id;
        $livreur_id = (int) $livreur_id;
        if ($livraison_id < 1 || $livreur_id < 1) {
            return false;
        }

        $queued = notify_queue_enqueue('livreur_arrive', [
            'type' => $type,
            'livraison_id' => $livraison_id,
            'livreur_id' => $livreur_id,
            'numero' => trim((string) $numero),
        ], true);

        return !empty($queued['success']);
    }

    /**
     * Enfile les push admin « livreur a pris en charge » — ne bloque pas la réponse HTTP.
     *
     * @param 'commande'|'facture'|'personnalisee' $type
     * @return bool
     */
    function livreur_enqueue_prise_notifications($type, $livraison_id, $livreur_id, $numero = '') {
        require_once __DIR__ . '/notify_queue.php';

        $type = livreur_notify_normalize_type($type);
        $livraison_id = (int) $livraison_id;
        $livreur_id = (int) $livreur_id;
        if ($livraison_id < 1 || $livreur_id < 1) {
            return false;
        }

        $queued = notify_queue_enqueue('livreur_prise', [
            'type' => $type,
            'livraison_id' => $livraison_id,
            'livreur_id' => $livreur_id,
            'numero' => trim((string) $numero),
        ], true);

        return !empty($queued['success']);
    }

    /**
     * Push au client inscrit dont le téléphone correspond à la facture B2B.
     *
     * @param 'created'|'prise'|'gps'|'arrive'|'terminee' $event
     */
    function notify_client_bl_event($event, $bl_id) {
        require_once __DIR__ . '/notify_helpers.php';
        notifications_db_bootstrap();
        require_once __DIR__ . '/../includes/site_url.php';
        require_once __DIR__ . '/../models/model_bl.php';
        require_once __DIR__ . '/../models/model_livreur_tracking.php';

        $event = trim((string) $event);
        $bl_id = (int) $bl_id;
        if ($bl_id < 1 || $event === '') {
            return false;
        }

        $user_id = bl_find_user_id_from_bl($bl_id);
        if ($user_id < 1) {
            return false;
        }

        $bl = function_exists('livreur_get_facture_tracking')
            ? livreur_get_facture_tracking($bl_id)
            : get_bl_by_id($bl_id);
        if (!$bl) {
            return false;
        }

        $numero = trim((string) ($bl['numero_bl'] ?? ''));
        if ($numero === '') {
            $numero = (string) $bl_id;
        }

        $base = rtrim(get_site_base_url(), '/');
        $mes_commandes = $base . '/user/mes-commandes.php';
        $suivi = livreur_client_public_suivi_url_bl($bl_id, $user_id, false);
        $facture_url = bl_public_facture_url($bl);

        $title = 'Sugar Paper';
        $body = '';
        $link = $mes_commandes;
        $tag = 'bl-' . $event . '-' . $numero;

        switch ($event) {
            case 'created':
                $title = 'Commande reçue';
                $body = "Votre commande {$numero} a été enregistrée. Vous pouvez la consulter dans l'application (Mes commandes).";
                $link = $facture_url !== '' ? $facture_url : $mes_commandes;
                break;
            case 'prise':
                $title = 'Livreur en route';
                $body = "Un livreur a pris en charge votre commande {$numero}. Vous serez notifié dès que le suivi GPS démarre.";
                break;
            case 'gps':
                $title = 'Suivi GPS disponible';
                $body = "Le livreur a démarré la livraison de {$numero}. Suivez-le en temps réel dans l'application.";
                $link = $suivi ? $suivi : $mes_commandes;
                break;
            case 'arrive':
                $title = 'Votre livreur est arrivé';
                $body = "Le livreur est arrivé pour votre commande {$numero}. Préparez-vous à réceptionner votre colis.";
                $link = $suivi ? $suivi : $mes_commandes;
                break;
            case 'terminee':
                $title = 'Commande livrée';
                $body = "Votre commande {$numero} a été livrée. Merci de votre confiance.";
                $link = $facture_url !== '' ? $facture_url : $mes_commandes;
                break;
            default:
                return false;
        }

        $result = notifications_send_user_push($user_id, $title, $body, [
            'link' => $link,
            'bl_id' => (string) $bl_id,
            'numero_commande' => $numero,
            'tag' => $tag,
        ], 'bl_client_' . $event);

        return ((int) ($result['success'] ?? 0)) > 0;
    }
}
