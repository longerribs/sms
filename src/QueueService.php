<?php
/**
 * clayon/src/QueueService.php
 * 
 * Database-driven Queue Service for Clayon.
 * Handles non-blocking SMS dispatching with MySQL-only fallback logic.
 */

require_once __DIR__ . '/WalletService.php';
require_once __DIR__ . '/Logger.php';

class QueueService {
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: getClayonDb();
    }

    /**
     * Enqueue an SMS request
     */
    public function enqueue($smsRequestId, $clientId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sms_queue (sms_request_id, client_id, status, next_attempt_at) 
                VALUES (?, ?, 'pending', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$smsRequestId, $clientId]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            Logger::error("Queue Enqueue Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * HYBRID FAST PATH: Try immediate SMS send, fallback to Queue
     */
    public function sendCriticalSMS($smsRequestId, $clientId) {
        try {
            $stmt = $this->db->prepare("UPDATE sms_requests SET status = 'processing' WHERE id = ?");
            $stmt->execute([$smsRequestId]);

            $smsService = new SMSService($this->db);
            $success = $smsService->executeRealSend($smsRequestId, 5);
            
            if ($success) {
                // DEBIT HERE for Fast Path
                $walletService = new WalletService($this->db);
                $walletService->debitForSmsSuccess($smsRequestId);
                return ['success' => true, 'queued' => false];
            }

            $queueId = $this->enqueue($smsRequestId, $clientId);
            if ($queueId) {
                $stmt = $this->db->prepare("UPDATE sms_requests SET status = 'pending' WHERE id = ?");
                $stmt->execute([$smsRequestId]);
                return ['success' => true, 'queued' => true, 'queue_id' => $queueId];
            }

            return ['success' => false, 'error' => 'Failed to queue SMS'];
        } catch (Exception $e) {
            Logger::error("Send critical SMS error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Claim jobs from the queue for processing
     */
    public function claimJobs($limit = 10) {
        try {
            $workerId = gethostname() . '_' . getmypid();
            
            // Limit must be an integer for some MySQL versions/drivers in UPDATE
            $limit = (int)$limit;

            // Atomic claim using update with locking
            // We use a subquery to avoid issues with LIMIT in UPDATE on some systems
            $stmt = $this->db->prepare("
                UPDATE sms_queue 
                SET status = 'locked', locked_at = CURRENT_TIMESTAMP, locked_by = :worker_id 
                WHERE status = 'pending' 
                AND (next_attempt_at IS NULL OR next_attempt_at <= CURRENT_TIMESTAMP)
                LIMIT $limit
            ");
            
            $stmt->execute(['worker_id' => $workerId]);
            $updated = $stmt->rowCount();

            if ($updated > 0) {
                $stmt = $this->db->prepare("
                    SELECT * FROM sms_queue 
                    WHERE status = 'locked' AND locked_by = ?
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$workerId]);
                return $stmt->fetchAll();
            }
            
            return [];
        } catch (Exception $e) {
            Logger::error("Claim jobs database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update queue item after attempt
     */
    public function releaseJob($queueId, $status, $delayMinutes = 5) {
        try {
            if ($status === 'completed') {
                $stmt = $this->db->prepare("DELETE FROM sms_queue WHERE id = ?");
                $stmt->execute([$queueId]);
            } else {
                $nextAttempt = date('Y-m-d H:i:s', strtotime("+$delayMinutes minutes"));
                $stmt = $this->db->prepare("
                    UPDATE sms_queue 
                    SET status = 'pending', attempts = attempts + 1, next_attempt_at = ?, locked_at = NULL, locked_by = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$nextAttempt, $queueId]);
            }
            return true;
        } catch (Exception $e) {
            Logger::error("Release job error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move to dead letter queue
     */
    public function deadLetterJob($queueId, $smsRequestId, $reason = '') {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM sms_queue WHERE id = ?");
            $stmt->execute([$queueId]);

            $stmt = $this->db->prepare("UPDATE sms_requests SET status = 'failed' WHERE id = ?");
            $stmt->execute([$smsRequestId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error("Dead letter job error: " . $e->getMessage());
            return false;
        }
    }
}
