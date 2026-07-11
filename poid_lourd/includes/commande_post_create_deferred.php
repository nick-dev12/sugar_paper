<?php
/**
 * Notifications après création commande — exécutées après la réponse HTTP (redirect client).
 */

/**
 * Push vendeurs + emails en file (hors chemin critique utilisateur).
 *
 * @param array $payload
 */
function commande_run_deferred_notifications(array $payload): void
{
    if (!file_exists(__DIR__ . '/../services/send_new_commande_to_admin.php')) {
        return;
    }

    require_once __DIR__ . '/../services/send_new_commande_to_admin.php';
    require_once __DIR__ . '/../services/send_commande_notification.php';

    try {
        if (!empty($payload['notifications']) && is_array($payload['notifications'])) {
            foreach ($payload['notifications'] as $n) {
                if (empty($n['vendeur_id']) || empty($n['numero_commande'])) {
                    continue;
                }
                send_new_commande_to_vendeur(
                    (int) $n['vendeur_id'],
                    isset($n['commande_id']) ? (int) $n['commande_id'] : 0,
                    (string) $n['numero_commande'],
                    (float) ($n['montant_total'] ?? 0),
                    (int) ($n['nombre_articles'] ?? 0),
                    (string) ($n['telephone_livraison'] ?? ''),
                    (string) ($n['adresse_livraison'] ?? ''),
                    is_array($n['produits'] ?? null) ? $n['produits'] : []
                );
            }
        } elseif (!empty($payload['email_data']) && is_array($payload['email_data'])) {
            $d = $payload['email_data'];
            send_new_commande_to_admin(
                $d['numero_commande'],
                $d['montant_total'],
                $d['nombre_articles'],
                $d['telephone_livraison'] ?? '',
                $d['adresse_livraison'] ?? '',
                $d['produits'] ?? []
            );
        }

        $user_id = (int) ($payload['user_id'] ?? 0);
        $nums_client = !empty($payload['numeros_commandes']) && is_array($payload['numeros_commandes'])
            ? $payload['numeros_commandes']
            : [];
        if ($nums_client === [] && !empty($payload['numero_commande'])) {
            $nums_client = [(string) $payload['numero_commande']];
        }
        $montant_client = isset($payload['email_data']['montant_total'])
            ? (float) $payload['email_data']['montant_total']
            : 0.0;

        if ($user_id > 0 && $nums_client !== []) {
            send_new_commande_confirmation_to_client(
                $user_id,
                $nums_client,
                $montant_client,
                trim((string) ($payload['user_email'] ?? ''))
            );
        }
    } catch (Throwable $e) {
        error_log('[commande_post_create_deferred] ' . $e->getMessage());
    }
}
