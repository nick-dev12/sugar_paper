<?php
/**
 * Contrôleur pour les commandes personnalisées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes_personnalisees.php';

/**
 * Traite la soumission d'une commande personnalisée
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
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $type_produit = isset($_POST['type_produit']) ? trim($_POST['type_produit']) : '';
    $quantite = isset($_POST['quantite']) ? trim($_POST['quantite']) : '';
    $date_souhaitee = isset($_POST['date_souhaitee']) ? trim($_POST['date_souhaitee']) : '';

    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    if (empty($prenom)) {
        $errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }

    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = 'Le format du téléphone n\'est pas valide.';
    }

    if (empty($description)) {
        $errors[] = 'La description de votre demande est obligatoire.';
    } elseif (strlen($description) < 10) {
        $errors[] = 'Veuillez détailler davantage votre demande (minimum 10 caractères).';
    }

    if (!empty($date_souhaitee) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_souhaitee)) {
        $errors[] = 'La date souhaitée n\'est pas valide.';
    }

    if (empty($errors)) {
        $data = [
            'user_id' => $user_id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'description' => $description,
            'type_produit' => $type_produit ?: null,
            'quantite' => $quantite ?: null,
            'date_souhaitee' => $date_souhaitee ?: null
        ];

        $id = create_commande_personnalisee($data);
        if ($id) {
            $success = true;
            $message = 'Votre demande de commande personnalisée a été envoyée avec succès. Nous vous contacterons rapidement.';
        } else {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    $message = $success ? $message : implode('<br>', $errors);
    return ['success' => $success, 'message' => $message];
}
