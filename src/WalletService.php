<?php
/**
 * clayon/src/WalletService.php
 * 
 * Wallet management, balance tracking, and ledger operations.
 */

class WalletService {
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: getDb();
    }

    /**
     * Direct debit (for confirmed SMS delivery)
     * Used ONLY after provider confirms delivery (status='completed')
     */
    public function directDebit($clientId, $units, $reference = '', $note = '') {
        try {
            $this->db->beginTransaction();

            // Check balance (prevent negative balance)
            $stmt = $this->db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ? FOR UPDATE");
            $stmt->execute([$clientId]);
            $wallet = $stmt->fetch();

            if (!$wallet || $wallet['balance_units'] < $units) {
                $this->db->rollBack();
                error_log("Insufficient balance for client $clientId. Required: $units, Available: " . ($wallet['balance_units'] ?? 0));
                return false;
            }

            // Debit balance
            $stmt = $this->db->prepare("
                UPDATE wallet_accounts 
                SET balance_units = balance_units - ?, updated_at = CURRENT_TIMESTAMP
                WHERE client_id = ?
            ");
            $stmt->execute([$units, $clientId]);

            // Log to ledger with provider reference
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'debit', ?, ?, ?)
            ");
            $stmt->execute([$clientId, $units, $reference, $note ?: 'SMS delivered - units debited']);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Direct debit error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Specialized debit for SMS success
     * Fetches request details and provider reference automatically
     */
    public function debitForSmsSuccess($smsRequestId) {
        try {
            $this->db->beginTransaction();

            // Get SMS request details including provider_message_id and segments
            $stmt = $this->db->prepare("SELECT client_id, estimated_segments, provider_message_id FROM sms_requests WHERE id = ?");
            $stmt->execute([$smsRequestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request || !$request['provider_message_id']) {
                $this->db->rollBack();
                error_log("SMS request not found or no provider_message_id for $smsRequestId");
                return false;
            }

            $clientId = $request['client_id'];
            $segments = $request['estimated_segments'];
            $providerMsgId = $request['provider_message_id'];

            // Check balance before debit (prevent negative balance)
            $stmt = $this->db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ? FOR UPDATE");
            $stmt->execute([$clientId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet || $wallet['balance_units'] < $segments) {
                $this->db->rollBack();
                error_log("Insufficient balance for client $clientId. Required: $segments, Available: " . ($wallet['balance_units'] ?? 0));
                return false;
            }

            // Debit wallet balance
            $stmt = $this->db->prepare("UPDATE wallet_accounts SET balance_units = balance_units - ?, updated_at = CURRENT_TIMESTAMP WHERE client_id = ?");
            $stmt->execute([$segments, $clientId]);

            // Log debit to ledger with provider_message_id reference for audit trail
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'debit', ?, ?, ?)
            ");
            $stmt->execute([
                $clientId,
                $segments,
                $providerMsgId,
                "SMS accepted by provider - Provider ID: $providerMsgId"
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Debit for SMS success error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get wallet balance for a client
     */
    public function getBalance($clientId) {
        try {
            $stmt = $this->db->prepare("SELECT balance_units, reserved_units FROM wallet_accounts WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $wallet = $stmt->fetch();

            return $wallet ?: [
                'client_id' => $clientId,
                'balance_units' => 0,
                'reserved_units' => 0,
                'available_units' => 0
            ];
        } catch (Exception $e) {
            error_log("Wallet balance error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reserve units (for pending SMS)
     * Used before attempting send
     */
    public function reserveUnits($clientId, $units) {
        try {
            $this->db->beginTransaction();

            // Check balance first
            $stmt = $this->db->prepare("SELECT balance_units FROM wallet_accounts WHERE client_id = ? FOR UPDATE");
            $stmt->execute([$clientId]);
            $wallet = $stmt->fetch();

            if (!$wallet || $wallet['balance_units'] < $units) {
                $this->db->rollBack();
                return false;
            }

            // Debit and reserve
            $stmt = $this->db->prepare("
                UPDATE wallet_accounts 
                SET balance_units = balance_units - ?, 
                    reserved_units = reserved_units + ?
                WHERE client_id = ?
            ");
            $stmt->execute([$units, $units, $clientId]);

            // Log to ledger
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, note)
                VALUES (?, 'debit', ?, 'SMS units reserved')
            ");
            $stmt->execute([$clientId, $units]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Reserve units error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm units (after successful send)
     */
    public function confirmDebit($clientId, $units, $reference = '', $note = '') {
        try {
            $stmt = $this->db->prepare("
                UPDATE wallet_accounts 
                SET reserved_units = reserved_units - ?
                WHERE client_id = ?
            ");
            $stmt->execute([$units, $clientId]);

            // Log to ledger
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'debit', ?, ?, ?)
            ");
            $stmt->execute([$clientId, $units, $reference, $note ?: 'SMS sent']);

            return true;
        } catch (Exception $e) {
            error_log("Confirm debit error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund units (on failure)
     */
    public function refundUnits($clientId, $units, $reference = '', $reason = '') {
        try {
            $this->db->beginTransaction();

            // Restore balance and release reserved
            $stmt = $this->db->prepare("
                UPDATE wallet_accounts 
                SET balance_units = balance_units + ?,
                    reserved_units = GREATEST(0, reserved_units - ?)
                WHERE client_id = ?
            ");
            $stmt->execute([$units, $units, $clientId]);

            // Log to ledger
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'refund', ?, ?, ?)
            ");
            $stmt->execute([$clientId, $units, $reference, $reason ?: 'SMS failed - units refunded']);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Refund units error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Credit units (from payment)
     */
    public function creditUnits($clientId, $units, $reference = '', $note = '') {
        try {
            // Ensure wallet account exists
            $stmt = $this->db->prepare("INSERT IGNORE INTO wallet_accounts (client_id) VALUES (?)");
            $stmt->execute([$clientId]);

            // Credit balance
            $stmt = $this->db->prepare("
                UPDATE wallet_accounts 
                SET balance_units = balance_units + ?, updated_at = CURRENT_TIMESTAMP
                WHERE client_id = ?
            ");
            $stmt->execute([$units, $clientId]);

            // Log to ledger
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'credit', ?, ?, ?)
            ");
            $stmt->execute([$clientId, $units, $reference, $note ?: 'Account credited']);

            return true;
        } catch (Exception $e) {
            error_log("Credit units error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get wallet ledger entries
     */
    public function getLedger($clientId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM wallet_ledger 
                WHERE client_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$clientId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get ledger error: " . $e->getMessage());
            return [];
        }
    }
}
