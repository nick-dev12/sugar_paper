<?php
/**
 * Partage de la boutique vendeur — modal unifiée + script réutilisable
 * Programmation procédurale uniquement
 */

if (!function_exists('vendeur_share_boutique_get_data')) {

function vendeur_share_boutique_get_data()
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $role = function_exists('admin_normalize_role_for_route')
        ? admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin')
        : (string) ($_SESSION['admin_role'] ?? '');

    if ($role !== 'vendeur') {
        $data = ['available' => false];
        return $data;
    }

    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $slug = '';
    $nom = '';

    if ($admin_id > 0) {
        if (!function_exists('get_admin_by_id')) {
            require_once __DIR__ . '/../../models/model_admin.php';
        }
        $admin = get_admin_by_id($admin_id);
        if ($admin && ($admin['role'] ?? '') === 'vendeur') {
            if (function_exists('admin_sync_vendeur_boutique_session_from_admin')) {
                admin_sync_vendeur_boutique_session_from_admin($admin);
            }
            $slug = trim((string) ($admin['boutique_slug'] ?? ''));
            $nom = trim((string) ($admin['boutique_nom'] ?? ''));
        }
    }

    if ($slug === '') {
        $slug = trim((string) ($_SESSION['admin_boutique_slug'] ?? ''));
    }
    if ($nom === '') {
        $nom = trim((string) ($_SESSION['admin_boutique_nom'] ?? ''));
    }

    if ($slug === '') {
        $data = ['available' => false];
        return $data;
    }

    if (!function_exists('get_site_base_url')) {
        require_once __DIR__ . '/../../includes/site_url.php';
    }
    if (!function_exists('boutique_url')) {
        require_once __DIR__ . '/../../includes/marketplace_helpers.php';
    }
    if (!function_exists('marketplace_boutique_share_payload')) {
        require_once __DIR__ . '/../../includes/marketplace_boutique_card_helpers.php';
    }

    if ($nom === '') {
        $nom = 'Ma boutique';
    }

    $share = marketplace_boutique_share_payload($slug, $nom);
    $path = boutique_url('index.php', $slug);
    $url = rtrim(get_site_base_url(), '/') . $path;

    $data = [
        'available' => true,
        'slug' => $slug,
        'nom' => $nom,
        'url' => $url,
        'subject' => $share['subject'],
        'message' => $share['message'],
    ];

    return $data;
}

function vendeur_share_boutique_is_available()
{
    $data = vendeur_share_boutique_get_data();
    return !empty($data['available']);
}

/**
 * @deprecated Utilise la modal unifiée platformShareModal (includes/partials/platform_share_modal.php).
 */
function vendeur_share_boutique_render_modal($modal_id = 'vendeurShareModal')
{
    unset($modal_id);
}

function vendeur_share_boutique_render_script(array $options = [])
{
    $data = vendeur_share_boutique_get_data();
    if (empty($data['available'])) {
        return;
    }

    $open_ids = $options['open_button_ids'] ?? [];
    if (!is_array($open_ids)) {
        $open_ids = [$open_ids];
    }
    $open_ids = array_values(array_filter(array_map('strval', $open_ids)));

    $url_json = json_encode($data['url'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $title_json = json_encode($data['subject'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $message_json = json_encode($data['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $open_ids_json = json_encode($open_ids, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $feedback_id = (string) ($options['feedback_id'] ?? '');
    ?>
    <script>
    (function () {
        var url = <?php echo $url_json; ?>;
        var title = <?php echo $title_json; ?>;
        var message = <?php echo $message_json; ?>;
        var openBtnIds = <?php echo $open_ids_json; ?>;
        var feedback = <?php echo $feedback_id !== '' ? 'document.getElementById(' . json_encode($feedback_id) . ')' : 'null'; ?>;

        function showFeedback(msg) {
            if (!feedback) return;
            feedback.textContent = msg;
            window.setTimeout(function () { if (feedback) feedback.textContent = ''; }, 3500);
        }

        function openBoutiqueShare() {
            if (typeof window.openPlatformShareModal !== 'function') return;
            window.openPlatformShareModal({
                modalTitle: 'Partager ma boutique',
                title: title,
                url: url,
                message: message,
                hint: 'Le lien ouvre votre boutique publique sur COLObanes.'
            });
        }

        openBtnIds.forEach(function (btnId) {
            var btn = document.getElementById(btnId);
            if (btn) btn.addEventListener('click', openBoutiqueShare);
        });

        var externalCopyId = <?php echo json_encode((string) ($options['external_copy_button_id'] ?? '')); ?>;
        if (externalCopyId) {
            var externalCopyBtn = document.getElementById(externalCopyId);
            if (externalCopyBtn) {
                externalCopyBtn.addEventListener('click', function () {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function () {
                            showFeedback('Lien copié dans le presse-papiers.');
                        }).catch(function () {
                            showFeedback('Copie impossible — sélectionnez le lien manuellement.');
                        });
                        return;
                    }
                    showFeedback('Copie impossible — sélectionnez le lien manuellement.');
                });
            }
        }

        window.vendeurShareBoutiqueCopyUrl = function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    showFeedback('Lien copié dans le presse-papiers.');
                });
                return;
            }
            showFeedback('Copie impossible — utilisez le bouton à côté du lien.');
        };
    })();
    </script>
    <?php
}

}
