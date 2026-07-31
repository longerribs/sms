<?php
/**
 * clayon/src/SMSService.php
 * 
 * SMS Service for Clayon Reseller Platform.
 * Interacts with TalkSasa API and manages reseller-specific logic.
 */

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db.php';

class SMSService
{
    private $db;
    private $talksasa_url;
    private $talksasa_key;

    public function __construct($db = null)
    {
        $this->db = $db ?: getClayonDb();
        $this->talksasa_url = clayon_env('TALKSASA_URL', 'https://bulksms.talksasa.com/api/v3/sms/send');
        $this->talksasa_key = clayon_env('TALKSASA_API_KEY');
    }

    /**
     * Clean and format phone number to International Standard (254...)
     */
    public function formatPhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Handle local 07... or 01... (Kenyan mobile) formats
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }

        // Handle 7... or 1... (9 digits)
        if (strlen($phone) === 9) {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    /**
     * Calculate segments based on custom reseller pricing strategy:
     * - 60 characters per segment.
     * - 1st and 2nd segments cost 1 unit each.
     * - 3rd segment and beyond cost 2 units each.
     */
    public function calculateSegments($message)
    {
        $len = mb_strlen($message);
        if ($len <= 0)
            return 0;

        $totalSegments = ceil($len / 60);

        if ($totalSegments <= 2) {
            return $totalSegments;
        } else {
            return 2 + (($totalSegments - 2) * 2);
        }
    }

    /**
     * Create SMS request (NO DEBIT - debit happens after provider confirms)
     * Returns request data or null on failure
     */
    public function createRequest($clientId, $recipient, $message, $senderId)
    {
        try {
            $recipient = $this->formatPhone($recipient);
            $segments = $this->calculateSegments($message);
            $reference = 'req_' . uniqid(true);

            $this->db->beginTransaction();

            // Insert request with status='pending' (NOT DEBITED YET)
            $stmt = $this->db->prepare("
                INSERT INTO sms_requests 
                (client_id, request_reference, recipient, message, sender_id, estimated_segments, estimated_cost, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$clientId, $reference, $recipient, $message, $senderId, $segments, $segments]);
            $requestId = $this->db->lastInsertId();

            // Log reserved units (for transparency, no actual debit)
            $stmt = $this->db->prepare("
                INSERT INTO wallet_ledger (client_id, entry_type, units, reference, note)
                VALUES (?, 'reserved', ?, ?, ?)
            ");
            $stmt->execute([$clientId, $segments, $reference, "SMS reserved (pending provider confirmation): $recipient"]);

            $this->db->commit();

            return [
                'id' => $requestId,
                'reference' => $reference,
                'recipient' => $recipient,
                'message' => $message,
                'sender_id' => $senderId,
                'segments' => $segments,
                'estimated_cost' => $segments,
                'status' => 'pending'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SMS Create Request Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Execute actual API call to TalkSasa
     */
    public function executeRealSend($smsRequestId, $timeout = 30)
    {
        $stmt = $this->db->prepare("SELECT * FROM sms_requests WHERE id = ?");
        $stmt->execute([$smsRequestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request)
            return false;

        $attemptNo = $this->getNewAttemptNo($smsRequestId);

        $callbackUrl = clayon_env('APP_URL', 'http://localhost/sms') . '/dlr.php';
        $payload = [
            'recipient' => $request['recipient'],
            'sender_id' => $request['sender_id'],
            'message' => $request['message'],
            'type' => 'plain',
            // Include callback/dlr URLs for TalkSasa API compatibility
            'callback_url' => $callbackUrl,
            'dlr_url'      => $callbackUrl
        ];

        $jsonPayload = json_encode($payload);

        $ch = curl_init($this->talksasa_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->talksasa_key,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->logAttempt($smsRequestId, $attemptNo, $jsonPayload, $response, $httpCode, $error);

        if ($error)
            return false;

        $resData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $isSuccess = (
                (isset($resData['status']) && ($resData['status'] === 'success' || $resData['status'] === true)) ||
                (isset($resData['success']) && $resData['success'] === true)
            );

            if ($isSuccess) {
                $providerMsgId = $resData['data']['queue_uid'] ?? ($resData['data']['message_id'] ?? ($resData['queue_uid'] ?? ($resData['message_id'] ?? ($resData['id'] ?? null))));
                $this->logProviderMessage($smsRequestId, $providerMsgId, $request, $resData);

                $this->db->prepare("UPDATE sms_requests SET status = 'accepted', provider_message_id = ?, final_cost = ? WHERE id = ?")
                    ->execute([$providerMsgId, $request['estimated_cost'], $smsRequestId]);

                return true;
            }
        }

        return false;
    }

    private function getNewAttemptNo($requestId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sms_attempts WHERE sms_request_id = ?");
        $stmt->execute([$requestId]);
        return $stmt->fetchColumn() + 1;
    }

    private function logAttempt($requestId, $no, $payload, $response, $code, $error)
    {
        $stmt = $this->db->prepare("
            INSERT INTO sms_attempts (sms_request_id, attempt_no, provider_request_payload, provider_response_payload, http_code, error_message, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$requestId, $no, $payload, $response, $code, $error]);
    }

    private function logProviderMessage($requestId, $providerMsgId, $request, $raw)
    {
        $stmt = $this->db->prepare("
            INSERT INTO provider_sms_logs (provider_message_id, sms_request_id, sender_name, recipient, sms_count, raw_payload) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $providerMsgId,
            $requestId,
            $request['sender_id'],
            $request['recipient'],
            $request['estimated_segments'],
            json_encode($raw)
        ]);
    }
}
