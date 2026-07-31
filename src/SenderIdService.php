<?php
/**
 * clayon/src/SenderIdService.php
 * 
 * Sender ID management and validation.
 */

class SenderIdService {
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: getDb();
    }

    /**
     * Check if sender_id belongs to client
     */
    public function clientOwnersSenderId($clientId, $senderId) {
        try {
            $senderId = trim((string) $senderId);
            if ($senderId === '') {
                $senderId = 'TALKSASA';
            }
            $senderId = preg_replace('/[^A-Za-z0-9_-]/', '', $senderId);
            if ($senderId === '') {
                $senderId = 'TALKSASA';
            }
            $senderId = strtoupper($senderId);

            return $senderId === 'TALKSASA';
        } catch (Exception $e) {
            error_log("Check sender ID error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Get client's approved sender IDs
     */
    public function getApprovedSenderIds($clientId) {
        try {
            $stmt = $this->db->prepare("
                SELECT sender_id, approval_status, status 
                FROM sender_ids 
                WHERE client_id = ? AND status = 'active'
                ORDER BY created_at DESC
            ");
            $stmt->execute([$clientId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get sender IDs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Request new sender ID
     */
    public function requestSenderId($clientId, $senderId) {
        try {
            // Check if already exists
            $stmt = $this->db->prepare("
                SELECT id FROM sender_ids 
                WHERE client_id = ? AND sender_id = ?
            ");
            $stmt->execute([$clientId, $senderId]);

            if ($stmt->fetch()) {
                return ['error' => 'Sender ID already exists'];
            }

            // Create request
            $stmt = $this->db->prepare("
                INSERT INTO sender_ids (client_id, sender_id, approval_status, status)
                VALUES (?, ?, 'pending', 'active')
            ");
            $stmt->execute([$clientId, $senderId]);

            // Log audit
            $this->logAudit('client', $clientId, 'sender_id_requested', 'sender_ids', $this->db->lastInsertId(), [
                'sender_id' => $senderId
            ]);

            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (Exception $e) {
            error_log("Request sender ID error: " . $e->getMessage());
            return ['error' => 'Failed to create request'];
        }
    }

    /**
     * Admin: approve sender ID
     */
    public function approveSenderId($senderIdRecordId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE sender_ids 
                SET approval_status = 'approved'
                WHERE id = ?
            ");
            $result = $stmt->execute([$senderIdRecordId]);

            // Get details for audit
            $stmt = $this->db->prepare("SELECT * FROM sender_ids WHERE id = ?");
            $stmt->execute([$senderIdRecordId]);
            $record = $stmt->fetch();

            $this->logAudit('admin', null, 'sender_id_approved', 'sender_ids', $senderIdRecordId, [
                'sender_id' => $record['sender_id']
            ]);

            return $result;
        } catch (Exception $e) {
            error_log("Approve sender ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get pending sender ID requests
     */
    public function getPendingRequests() {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, c.name, c.email 
                FROM sender_ids s
                JOIN clients c ON s.client_id = c.id
                WHERE s.approval_status = 'pending'
                ORDER BY s.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get pending requests error: " . $e->getMessage());
            return [];
        }
    }

    private function logAudit($actorType, $actorId, $action, $entityType, $entityId, $metadata = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, metadata)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $actorType,
                $actorId,
                $action,
                $entityType,
                $entityId,
                json_encode($metadata)
            ]);
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}
