<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_buy_process.php
 * DESCRIPTION : Traitement de l'achat de crédits messages
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';

if ((!isset($_SESSION['user_id']) && !isset($_SESSION['user_code'])) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messages_buy.php');
    exit;
}

$user_code = gntoma_resolve_logged_in_user_code($pdo) ?? '';
if ($user_code === '') {
    header('Location: ../index.php');
    exit;
}

$return = trim((string) ($_POST['return'] ?? ''));
$pack_size = (int)($_POST['pack_size'] ?? 1000);
$pack_price = (float)($_POST['pack_price'] ?? 2);
$is_gift = isset($_POST['is_gift']);
$recipient_code = $is_gift ? strtoupper(trim((string)($_POST['recipient_code'] ?? ''))) : null;

// Validation des packs
$valid_packs = [
    500 => 1.2,
    1000 => 2,
    2500 => 4
];

if (!isset($valid_packs[$pack_size]) || $valid_packs[$pack_size] !== $pack_price) {
    header("Location: messages_buy.php?error=invalid_pack");
    exit;
}

// Si cadeau, vérifier le destinataire
if ($is_gift) {
    if (empty($recipient_code)) {
        header("Location: messages_buy.php?error=missing_recipient");
        exit;
    }
    
    $check_stmt = $pdo->prepare("SELECT user_code FROM users WHERE user_code = ? LIMIT 1");
    $check_stmt->execute([$recipient_code]);
    if (!$check_stmt->fetch()) {
        header("Location: messages_buy.php?error=recipient_not_found");
        exit;
    }
}

try {
    // Enregistrer l'achat en attente
    $purchase_stmt = $pdo->prepare("
        INSERT INTO message_credit_purchases 
        (user_code, credits_amount, price, currency, is_gift, recipient_user_code, status)
        VALUES (?, ?, ?, 'USD', ?, ?, 'pending')
    ");
    $purchase_stmt->execute([
        $user_code, $pack_size, $pack_price, 
        $is_gift ? 1 : 0, 
        $is_gift ? $recipient_code : null
    ]);
    $purchase_id = $pdo->lastInsertId();
    
    // Rediriger vers le paiement FlexPay (réutiliser le système existant)
    // ou traiter directement pour l'instant
    
    // Pour l'instant, simulons un paiement réussi
    // Dans la vraie implémentation, rediriger vers payment_init_11.php avec les bons paramètres
    
    // Traiter le paiement
    $pdo->beginTransaction();
    
    // Mettre à jour le statut de l'achat
    $pdo->prepare("
        UPDATE message_credit_purchases 
        SET status = 'completed', transaction_reference = ?
        WHERE id = ?
    ")->execute(['MANUAL_' . time(), $purchase_id]);
    
    // Déterminer qui reçoit les crédits
    $beneficiary = $is_gift ? $recipient_code : $user_code;
    
    // Vérifier/créer les crédits du bénéficiaire
    $credits_check = $pdo->prepare("SELECT 1 FROM message_credits WHERE user_code = ?");
    $credits_check->execute([$beneficiary]);
    
    if (!$credits_check->fetch()) {
        $pdo->prepare("
            INSERT INTO message_credits (user_code, total_credits, used_credits, remaining_credits)
            VALUES (?, 0, 0, 0)
        ")->execute([$beneficiary]);
    }
    
    // Ajouter les crédits
    $pdo->prepare("
        UPDATE message_credits 
        SET total_credits = total_credits + ?, 
            remaining_credits = remaining_credits + ?,
            last_purchase_at = NOW()
        WHERE user_code = ?
    ")->execute([$pack_size, $pack_size, $beneficiary]);
    
    $pdo->commit();
    
    if ($return === 'messages_list') {
        header('Location: messages_list.php?success=credits_purchased');
    } else {
        header('Location: messages_buy.php?success=purchased');
    }
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur achat crédits : " . $e->getMessage());
    header("Location: messages_buy.php?error=system");
    exit;
}
