<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/payment_session_handler.php
 * DESCRIPTION : Gestionnaire de persistance pour les sessions de paiement avec restauration.
 */

require_once 'config.php';

class PaymentSessionHandler {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Sauvegarder une session de paiement avec données de session PHP
     */
    public function savePaymentSession(string $session_id, string $user_code, float $amount, int $days, ?string $reference = null, array $session_data = []): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_sessions (session_id, user_code, amount, days_to_add, status, reference, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            
            if ($reference === null) {
                $reference = "GNT-" . $user_code . "-" . time();
            }
            $stmt->execute([$session_id, $user_code, $amount, $days, $reference]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur sauvegarde session paiement : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les données d'une session de paiement
     */
    public function getSessionData(string $session_id): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_sessions 
                WHERE session_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$session_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erreur récupération session paiement : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Confirmer un paiement réussi
     */
    public function confirmPayment(string $session_id): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE payment_sessions 
                SET status = 'success', updated_at = NOW() 
                WHERE session_id = ?
            ");
            $stmt->execute([$session_id]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur confirmation paiement : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marquer un paiement comme échoué
     */
    public function failPayment(string $session_id): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE payment_sessions 
                SET status = 'failed', updated_at = NOW() 
                WHERE session_id = ?
            ");
            $stmt->execute([$session_id]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Erreur échec paiement : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupérer une session par sa référence FlexPay
     */
    public function getSessionByReference(string $reference): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_sessions 
                WHERE reference = ? 
                LIMIT 1
            ");
            $stmt->execute([$reference]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erreur récupération session par reference : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer la dernière session en attente pour un utilisateur
     */
    public function getPendingSession(string $user_code): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_sessions 
                WHERE user_code = ? 
                AND status = 'pending' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erreur récupération session en attente : " . $e->getMessage());
            return null;
        }
    }
}