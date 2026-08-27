<?php
/**
 * Contrôleur pour les commandes personnalisées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_cp_catalogue.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

/**
 * S'assure que la colonne image_reference existe (migration automatique)
 */
function ensure_image_reference_column() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image_reference'");
        if (!$stmt || $stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE commandes_personnalisees ADD COLUMN image_reference TEXT NULL DEFAULT NULL AFTER description");
            return true;
        }
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower($col['Type'] ?? '');
        if (strpos($type, 'text') === false) {
            $db->exec("ALTER TABLE commandes_personnalisees MODIFY COLUMN image_reference TEXT NULL DEFAULT NULL");
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Normalise le tableau $_FILES pour un input multiple
 * @param array|null $files
 * @return array
 */
function normalize_commande_personnalisee_files_array($files) {
    if (!is_array($files) || empty($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return [$files];
    }
    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0
        ];
    }
    return $normalized;
}

/**
 * Upload plusieurs images de référence
 * @param array|null $files_input
 * @param int $max_files
 * @return array
 */
function upload_commande_personnalisee_images($files_input, $max_files = 6) {
    $files = normalize_commande_personnalisee_files_array($files_input);
    $paths = [];
    $max_bytes = 5 * 1024 * 1024;

    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($paths) >= $max_files) {
            return [
                'success' => false,
                'message' => 'Vous pouvez joindre au maximum ' . $max_files . ' images.',
                'paths' => []
            ];
        }
        if (($file['size'] ?? 0) > $max_bytes) {
            return [
                'success' => false,
                'message' => 'Chaque image doit faire moins de 5 Mo.',
                'paths' => []
            ];
        }
        $validation = validate_commande_personnalisee_image($file);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message'], 'paths' => []];
        }
        $upload_result = upload_commande_personnalisee_image($file);
        if (!$upload_result['success']) {
            return ['success' => false, 'message' => $upload_result['message'], 'paths' => []];
        }
        if (!empty($upload_result['path'])) {
            $paths[] = $upload_result['path'];
        }
    }

    return ['success' => true, 'message' => '', 'paths' => $paths];
}

/**
 * Retourne le type MIME réel d'une image uploadée
 * @param string $tmp_name
 * @return string
 */
function get_commande_personnalisee_image_mime_type($tmp_name) {
    if (!is_string($tmp_name) || $tmp_name === '' || !file_exists($tmp_name)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            return is_string($mime) ? $mime : '';
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp_name);
        return is_string($mime) ? $mime : '';
    }

    return '';
}

/**
 * Valide l'image jointe à une commande personnalisée
 * @param array $file
 * @return array
 */
function validate_commande_personnalisee_image($file) {
    if (!is_array($file) || empty($file)) {
        return ['success' => true, 'message' => '', 'mime' => '', 'extension' => ''];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'mime' => '', 'extension' => ''];
    }

    if ($error_code !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Le téléversement de l\'image a échoué. Veuillez réessayer.', 'mime' => '', 'extension' => ''];
    }

    $file_size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($file_size <= 0) {
        return ['success' => false, 'message' => 'Le fichier image est invalide.', 'mime' => '', 'extension' => ''];
    }

    $mime_type = get_commande_personnalisee_image_mime_type($file['tmp_name'] ?? '');
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    if (!isset($allowed_mimes[$mime_type])) {
        return ['success' => false, 'message' => 'Format d\'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF.', 'mime' => '', 'extension' => ''];
    }

    return [
        'success' => true,
        'message' => '',
        'mime' => $mime_type,
        'extension' => $allowed_mimes[$mime_type]
    ];
}

/**
 * Upload l'image jointe à une commande personnalisée
 * @param array $file
 * @return array
 */
function upload_commande_personnalisee_image($file) {
    $validation = validate_commande_personnalisee_image($file);
    if (!$validation['success']) {
        return ['success' => false, 'message' => $validation['message'], 'path' => null];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'path' => null];
    }

    $upload_dir = __DIR__ . '/../upload/commandes-personnalisees/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['success' => false, 'message' => 'Impossible de préparer le dossier d\'upload de l\'image.', 'path' => null];
    }

    $result = upload_optimize_image_file($file, $upload_dir, 'commandes-personnalisees', 'commande_perso_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        return ['success' => true, 'message' => '', 'path' => (string) $result['relative_path']];
    }

    return [
        'success' => false,
        'message' => (string) ($result['message'] ?? 'Impossible d\'enregistrer l\'image de référence.'),
        'path' => null,
    ];
}

function ensure_note_vocale_column() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'note_vocale'");
        if (!$stmt || $stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE commandes_personnalisees ADD COLUMN note_vocale VARCHAR(255) NULL DEFAULT NULL AFTER image_reference");
            return true;
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Valide une note vocale uploadée
 * @param array $file
 * @return array
 */
function validate_commande_personnalisee_voice($file) {
    if (!is_array($file) || empty($file)) {
        return ['success' => true, 'message' => '', 'extension' => ''];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'extension' => ''];
    }

    if ($error_code !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Le téléversement de la note vocale a échoué. Veuillez réessayer.', 'extension' => ''];
    }

    $file_size = isset($file['size']) ? (int) $file['size'] : 0;
    $max_bytes = 10 * 1024 * 1024;
    if ($file_size <= 0) {
        return ['success' => false, 'message' => 'Le fichier audio est invalide.', 'extension' => ''];
    }
    if ($file_size > $max_bytes) {
        return ['success' => false, 'message' => 'La note vocale doit faire moins de 10 Mo.', 'extension' => ''];
    }

    $mime_type = get_commande_personnalisee_image_mime_type($file['tmp_name'] ?? '');
    $allowed_mimes = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/mpeg' => 'mp3',
        'audio/x-m4a' => 'm4a',
        'video/webm' => 'webm'
    ];

    if (!isset($allowed_mimes[$mime_type])) {
        return ['success' => false, 'message' => 'Format audio non autorisé. Réenregistrez votre message.', 'extension' => ''];
    }

    return [
        'success' => true,
        'message' => '',
        'extension' => $allowed_mimes[$mime_type]
    ];
}

/**
 * Enregistre une note vocale
 * @param array $file
 * @return array
 */
function upload_commande_personnalisee_voice($file) {
    $validation = validate_commande_personnalisee_voice($file);
    if (!$validation['success']) {
        return ['success' => false, 'message' => $validation['message'], 'path' => null];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'path' => null];
    }

    $upload_dir = __DIR__ . '/../upload/commandes-personnalisees/voice/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['success' => false, 'message' => 'Impossible de préparer le dossier d\'upload audio.', 'path' => null];
    }

    $extension = $validation['extension'] ?: 'webm';
    $filename = 'cp_voice_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $dest = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'Impossible d\'enregistrer la note vocale.', 'path' => null];
    }

    return [
        'success' => true,
        'message' => '',
        'path' => '/upload/commandes-personnalisees/voice/' . $filename
    ];
}

/**
 * @return array ['success' => bool, 'message' => string]
 */
function process_commande_personnalisee() {
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = '';
    $email = '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $prix_propose = isset($_POST['prix_propose']) ? trim($_POST['prix_propose']) : '';
    $type_produit = isset($_POST['type_produit']) ? trim($_POST['type_produit']) : '';
    $catalogue_produit_id = isset($_POST['catalogue_produit_id']) ? (int) $_POST['catalogue_produit_id'] : 0;
    $description_creation = isset($_POST['description_creation']) ? trim($_POST['description_creation']) : '';
    $description = '';
    $quantite = null;
    $date_souhaitee = null;
    $zone_livraison_id = null;
    $image_reference = null;
    $images_files = $_FILES['images_reference'] ?? null;
    $legacy_image_file = $_FILES['image_reference'] ?? null;
    $voice_file = $_FILES['note_vocale'] ?? null;
    $note_vocale = null;

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($csrf === '' || empty($_SESSION['cp_form_csrf']) || !hash_equals((string) $_SESSION['cp_form_csrf'], $csrf)) {
        $errors[] = 'Session expirée. Veuillez renvoyer le formulaire.';
    }

    if ($catalogue_produit_id <= 0) {
        $errors[] = 'Veuillez sélectionner un produit dans le catalogue.';
    }

    $catalogue_produit = null;
    if ($catalogue_produit_id > 0) {
        $catalogue_produit = get_cp_produit_by_id($catalogue_produit_id, true);
        if (!$catalogue_produit) {
            $errors[] = 'Le produit sélectionné n\'est plus disponible.';
            $catalogue_produit_id = 0;
        } else {
            $type_produit = trim($catalogue_produit['nom']);
        }
    }

    if ($user_id > 0) {
        require_once __DIR__ . '/../models/model_users.php';
        $user_compte = get_user_by_id($user_id);
        if ($user_compte) {
            $email = trim($user_compte['email'] ?? '');
            $prenom = trim($user_compte['prenom'] ?? '');
            $nom = trim($user_compte['nom'] ?? $nom);
            $telephone = trim($user_compte['telephone'] ?? $telephone);
        }
    } else {
        if (empty($nom)) {
            $errors[] = 'Le nom est obligatoire.';
        } elseif (strlen($nom) < 2) {
            $errors[] = 'Le nom doit contenir au moins 2 caractères.';
        }

        if (empty($telephone)) {
            $errors[] = 'Le téléphone est obligatoire.';
        } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
            $errors[] = 'Le format du téléphone n\'est pas valide.';
        } else {
            require_once __DIR__ . '/../models/model_users.php';
            $tel_digits = users_normalize_phone_digits($telephone);
            if (strlen($tel_digits) < 8) {
                $errors[] = 'Le numéro de téléphone semble incomplet.';
            } else {
                $telephone = $tel_digits;
            }
        }
    }

    if ($prix_propose === '' || !is_numeric($prix_propose)) {
        $errors[] = 'Indiquez un prix proposé valide.';
    } else {
        $prix_val = (float) $prix_propose;
        if ($prix_val <= 0) {
            $errors[] = 'Le prix proposé doit être supérieur à 0.';
        } elseif ($catalogue_produit) {
            $pmin = (float) ($catalogue_produit['prix_min'] ?? 0);
            $pmax = (float) ($catalogue_produit['prix_max'] ?? 0);
            if ($prix_val < $pmin || $prix_val > $pmax) {
                $errors[] = 'Le prix proposé doit être entre '
                    . number_format($pmin, 0, ',', ' ')
                    . ' et '
                    . number_format($pmax, 0, ',', ' ')
                    . ' FCFA.';
            }
        }
    }

    if ($description_creation !== '' && mb_strlen($description_creation) > 2000) {
        $errors[] = 'La description de votre création ne doit pas dépasser 2000 caractères.';
    }

    if (empty($errors) && $catalogue_produit) {
        $prix_aff = number_format((float) $prix_propose, 0, ',', ' ');
        $description = 'Produit catalogue : ' . $type_produit . '. Prix proposé : ' . $prix_aff . ' FCFA.';
        if ($description_creation !== '') {
            $description .= ' Personnalisation : ' . $description_creation;
        }
    }

    if (is_array($voice_file) && (($voice_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $voice_validation = validate_commande_personnalisee_voice($voice_file);
        if (!$voice_validation['success']) {
            $errors[] = $voice_validation['message'];
        }
    }

    if (empty($errors) && trim($nom) === '') {
        $errors[] = 'Impossible de récupérer vos informations. Reconnectez-vous ou contactez le support.';
    }

    $files_to_validate = normalize_commande_personnalisee_files_array($images_files);
    if (empty($files_to_validate) && is_array($legacy_image_file) && (($legacy_image_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $files_to_validate = [$legacy_image_file];
    }
    foreach ($files_to_validate as $file_item) {
        if (($file_item['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $image_validation = validate_commande_personnalisee_image($file_item);
        if (!$image_validation['success']) {
            $errors[] = $image_validation['message'];
            break;
        }
    }

    if (empty($errors)) {
        if ($user_id < 1) {
            require_once __DIR__ . '/../includes/guest_checkout_auth.php';
            $auth = guest_checkout_register_or_login($nom, $telephone, true);
            if (empty($auth['success'])) {
                $errors[] = (string) ($auth['message'] ?? 'Impossible de créer ou de connecter le compte avec ce numéro.');
            } else {
                $user_id = (int) ($auth['user']['id'] ?? 0);
                if ($user_id > 0) {
                    $email = trim((string) ($auth['user']['email'] ?? $email));
                    $prenom = trim((string) ($auth['user']['prenom'] ?? $prenom));
                }
            }
        }
    }

    if (empty($errors)) {
        $upload_batch = upload_commande_personnalisee_images($images_files);
        if (!$upload_batch['success'] && !empty($upload_batch['message'])) {
            $errors[] = $upload_batch['message'];
        } elseif (!empty($upload_batch['paths'])) {
            $image_reference = encode_commande_personnalisee_images($upload_batch['paths']);
        } elseif (is_array($legacy_image_file) && (($legacy_image_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $upload_result = upload_commande_personnalisee_image($legacy_image_file);
            if (!$upload_result['success']) {
                $errors[] = $upload_result['message'];
            } else {
                $image_reference = $upload_result['path'];
            }
        }

        if (empty($errors) && is_array($voice_file) && (($voice_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $voice_upload = upload_commande_personnalisee_voice($voice_file);
            if (!$voice_upload['success']) {
                $errors[] = $voice_upload['message'];
            } else {
                $note_vocale = $voice_upload['path'];
            }
        }
    }

    if (empty($errors)) {
        if ($user_id < 1) {
            $errors[] = 'Impossible d\'associer la demande à un compte. Vérifiez votre numéro.';
        }
    }

    if (empty($errors)) {
        ensure_image_reference_column();
        ensure_note_vocale_column();
        if ($note_vocale && $description !== '') {
            $description .= ' Message vocal joint.';
        }
        $data = [
            'user_id' => $user_id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'description' => $description,
            'image_reference' => $image_reference,
            'note_vocale' => $note_vocale,
            'type_produit' => $type_produit ?: null,
            'catalogue_produit_id' => $catalogue_produit_id > 0 ? $catalogue_produit_id : null,
            'quantite' => $quantite ?: null,
            'date_souhaitee' => $date_souhaitee ?: null,
            'zone_livraison_id' => $zone_livraison_id > 0 ? $zone_livraison_id : null
        ];

        $id = create_commande_personnalisee($data);
        if ($id) {
            $success = true;
            $message = 'Votre demande de commande personnalisée a été envoyée avec succès. Vous pouvez la suivre dans Mes commandes.';
            return [
                'success' => true,
                'message' => $message,
                'notify_data' => [
                    'commande_perso_id' => (int) $id,
                    'user_id' => $user_id,
                    'nom' => $nom,
                    'telephone' => $telephone,
                    'description' => $description,
                    'type_produit' => $type_produit,
                    'quantite' => $quantite,
                    'user_email' => $email
                ]
            ];
        } else {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    $message = $success ? $message : implode('<br>', $errors);
    return ['success' => $success, 'message' => $message];
}
