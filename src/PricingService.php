<?php
/**
 * clayon/src/PricingService.php
 * 
 * Billing and pricing calculations.
 */

class PricingService {
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: getDb();
    }

    /**
     * Get pricing plan for a client
     */
    public function getPlanForClient($clientId) {
        try {
            $stmt = $this->db->prepare("
                SELECT pp.* FROM pricing_plans pp
                JOIN clients c ON c.plan_id = pp.id
                WHERE c.id = ? AND pp.status = 'active'
            ");
            $stmt->execute([$clientId]);
            $plan = $stmt->fetch();

            // Fallback to default if no plan assigned
            if (!$plan) {
                return $this->getDefaultPlan();
            }

            return $plan;
        } catch (Exception $e) {
            error_log("Get plan error: " . $e->getMessage());
            return $this->getDefaultPlan();
        }
    }

    /**
     * Get default pricing plan
     */
    public function getDefaultPlan() {
        return [
            'id' => 0,
            'plan_name' => 'Default',
            'provider_markup_type' => Config::DEFAULT_MARKUP_TYPE,
            'markup_value' => Config::DEFAULT_MARKUP_VALUE,
            'min_topup' => Config::MIN_TOPUP_AMOUNT,
            'status' => 'active'
        ];
    }

    /**
     * Calculate cost for SMS segments
     * Returns: [
     *   'segments' => 1,
     *   'provider_cost' => 0.5,
     *   'markup' => 0.125,
     *   'client_cost' => 0.625,
     *   'profit' => 0.125
     * ]
     */
    public function calculateSMSCost($segments, $clientId) {
        try {
            $plan = $this->getPlanForClient($clientId);

            // Assume provider cost is X per segment (use setting or default)
            $providerCostPerSegment = floatval(
                $this->getSetting('provider_cost_per_segment', 0.5)
            );

            $providerCost = $segments * $providerCostPerSegment;

            // Calculate markup
            if ($plan['provider_markup_type'] === 'percentage') {
                $markup = ($providerCost * $plan['markup_value']) / 100;
            } else {
                $markup = $plan['markup_value'] * $segments;
            }

            $clientCost = $providerCost + $markup;
            $profit = $markup;

            return [
                'segments' => $segments,
                'provider_cost' => round($providerCost, 4),
                'markup' => round($markup, 4),
                'client_cost' => round($clientCost, 4),
                'profit' => round($profit, 4)
            ];
        } catch (Exception $e) {
            error_log("Calculate cost error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get system setting
     */
    public function getSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Create or update pricing plan
     */
    public function savePlan($data) {
        try {
            if (!empty($data['id'])) {
                $stmt = $this->db->prepare("
                    UPDATE pricing_plans 
                    SET plan_name = ?, provider_markup_type = ?, markup_value = ?, min_topup = ?, status = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $data['plan_name'],
                    $data['provider_markup_type'],
                    $data['markup_value'],
                    $data['min_topup'],
                    $data['status'] ?? 'active',
                    $data['id']
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO pricing_plans (plan_name, provider_markup_type, markup_value, min_topup, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                return $stmt->execute([
                    $data['plan_name'],
                    $data['provider_markup_type'],
                    $data['markup_value'],
                    $data['min_topup'] ?? 0
                ]);
            }
        } catch (Exception $e) {
            error_log("Save plan error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List all pricing plans
     */
    public function listPlans() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM pricing_plans ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("List plans error: " . $e->getMessage());
            return [];
        }
    }
}
