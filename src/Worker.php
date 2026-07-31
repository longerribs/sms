<?php
/**
 * clayon/src/Worker.php
 * 
 * Background worker script to be triggered by cron.
 * Processes the SMS queue with detailed logging.
 */

// Ensure we are in the right directory for relative requires
chdir(__DIR__);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/QueueService.php';
require_once __DIR__ . '/SMSService.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/WalletService.php';


$db = getClayonDb();
$queueService = new QueueService($db);
$smsService = new SMSService($db);

$now = date('Y-m-d H:i:s');
Logger::info("=== WORKER CYCLE STARTED ===");

// 1. Check health
$stmt = $db->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'pending'");
$totalPending = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= CURRENT_TIMESTAMP)");
$readyToProcess = $stmt->fetchColumn();

Logger::info("Queue Stats: $totalPending total pending, $readyToProcess ready for dispatch.");

// 2. Claim jobs
$jobs = $queueService->claimJobs(20); 
$claimedCount = count($jobs);
Logger::info("Claimed $claimedCount jobs.");

foreach ($jobs as $job) {
    $smsRequestId = $job['sms_request_id'];
    $queueId = $job['id'];
    $clientId = $job['client_id'];
    $attempt = $job['attempts'] + 1;
    
    // Fetch recipient for better logging
    $stmt = $db->prepare("SELECT recipient FROM sms_requests WHERE id = ?");
    $stmt->execute([$smsRequestId]);
    $recipient = $stmt->fetchColumn();

    Logger::info("Processing QueueID #$queueId | ReqID #$smsRequestId | Client #$clientId | To: $recipient | Attempt #$attempt");
    
    // Execute send
    $success = $smsService->executeRealSend($smsRequestId);
    
    if ($success) {
        Logger::info("Outcome: SUCCESS for ReqID #$smsRequestId - Provider confirmed");
        
        // CRITICAL: Debit wallet ONLY AFTER provider confirms
        try {
            $walletService = new WalletService($db);
            $debited = $walletService->debitForSmsSuccess($smsRequestId);
            
            if ($debited) {
                Logger::info("Wallet debited successfully for ReqID #$smsRequestId");
            } else {
                Logger::error("Wallet debit FAILED for ReqID #$smsRequestId - Check logs");
            }
        } catch (Exception $e) {
            Logger::error("Wallet debit exception: " . $e->getMessage());
        }
        
        $queueService->releaseJob($queueId, 'completed');
    } else {
        Logger::error("Outcome: FAILED for ReqID #$smsRequestId - Provider did not confirm");
        
        if ($job['attempts'] >= 5) {
            Logger::error("MAX RETRIES REACHED. Moving ReqID #$smsRequestId to dead_letter.");
            Logger::info("Note: No units debited (message failed)");
            $queueService->releaseJob($queueId, 'dead_letter');
            $db->prepare("UPDATE sms_requests SET status = 'failed' WHERE id = ?")->execute([$smsRequestId]);
        } else {
            Logger::info("Scheduling retry in 5 minutes for ReqID #$smsRequestId. Units still reserved.");
            $queueService->releaseJob($queueId, 'failed', 5);
        }
    }
}

$finished = date('Y-m-d H:i:s');
Logger::info("=== WORKER CYCLE FINISHED at $finished ===");
Logger::info("-------------------------------------------");

// Also echo to console for CLI visibility
echo "Worker finished. Check clayon/logs/worker.log for details." . PHP_EOL;
