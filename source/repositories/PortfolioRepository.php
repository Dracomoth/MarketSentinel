<?php
/**
 * Portfolio Repository
 * 
 * Handles database operations for the 'portfolios' and 'portfolio_items' tables.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class PortfolioRepository {
    /**
     * Create a new portfolio record
     * 
     * @param array $data
     * @return int Inserted portfolio ID
     */
    public static function create(array $data): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `portfolios` (
                    `name`, 
                    `user_prompt`, 
                    `strategy_summary`, 
                    `initial_capital`, 
                    `current_value`, 
                    `alert_status`, 
                    `alert_message`, 
                    `time_horizon`, 
                    `created`, 
                    `updated`
                ) VALUES (
                    :name, 
                    :user_prompt, 
                    :strategy_summary, 
                    :initial_capital, 
                    :current_value, 
                    :alert_status, 
                    :alert_message, 
                    :time_horizon, 
                    :created, 
                    :updated
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name'             => trim($data['name'] ?? 'Untitled Portfolio'),
            'user_prompt'      => trim($data['user_prompt'] ?? ''),
            'strategy_summary' => trim($data['strategy_summary'] ?? ''),
            'initial_capital'  => (float) ($data['initial_capital'] ?? 0.00),
            'current_value'    => (float) ($data['current_value'] ?? $data['initial_capital'] ?? 0.00),
            'alert_status'     => in_array($data['alert_status'] ?? '', ['normal', 'action_needed', 'urgent'], true) ? $data['alert_status'] : 'normal',
            'alert_message'    => !empty($data['alert_message']) ? trim($data['alert_message']) : null,
            'time_horizon'     => in_array($data['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $data['time_horizon'] : 'mid_term',
            'created'          => $data['created'] ?? $now,
            'updated'          => $data['updated'] ?? $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Add an asset item to a portfolio
     * 
     * @param int $portfolioId
     * @param array $item
     * @return int Inserted portfolio_item ID
     */
    public static function addItem(int $portfolioId, array $item): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `portfolio_items` (
                    `portfolio_id`,
                    `symbol`,
                    `asset_name`,
                    `asset_type`,
                    `exchange`,
                    `exchange_timezone`,
                    `current_price`,
                    `suggested_shares`,
                    `suggested_price`,
                    `target_price`,
                    `stop_loss_price`,
                    `why_picked`,
                    `bull_thesis`,
                    `bear_thesis`,
                    `invalidation_trigger`,
                    `time_horizon`,
                    `check_interval_hours`,
                    `last_checked_at`,
                    `is_purchased`,
                    `purchase_price`,
                    `purchase_shares`,
                    `purchased_at`,
                    `item_status`,
                    `asset_strategy`,
                    `broker_fee_type`,
                    `broker_fee_value`,
                    `total_fees_paid`,
                    `created`,
                    `updated`
                ) VALUES (
                    :portfolio_id,
                    :symbol,
                    :asset_name,
                    :asset_type,
                    :exchange,
                    :exchange_timezone,
                    :current_price,
                    :suggested_shares,
                    :suggested_price,
                    :target_price,
                    :stop_loss_price,
                    :why_picked,
                    :bull_thesis,
                    :bear_thesis,
                    :invalidation_trigger,
                    :time_horizon,
                    :check_interval_hours,
                    :last_checked_at,
                    :is_purchased,
                    :purchase_price,
                    :purchase_shares,
                    :purchased_at,
                    :item_status,
                    :asset_strategy,
                    :broker_fee_type,
                    :broker_fee_value,
                    :total_fees_paid,
                    :created,
                    :updated
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'portfolio_id'         => $portfolioId,
            'symbol'               => strtoupper(trim($item['symbol'] ?? 'UNKNOWN')),
            'asset_name'           => trim($item['asset_name'] ?? 'Asset'),
            'asset_type'           => trim($item['asset_type'] ?? 'stock'),
            'exchange'             => trim($item['exchange'] ?? 'US'),
            'exchange_timezone'    => trim($item['exchange_timezone'] ?? 'America/New_York'),
            'current_price'        => (float) ($item['current_price'] ?? 0.0000),
            'suggested_shares'     => (int) ($item['suggested_shares'] ?? 1),
            'suggested_price'      => (float) ($item['suggested_price'] ?? $item['current_price'] ?? 0.0000),
            'target_price'         => isset($item['target_price']) && $item['target_price'] !== '' ? (float) $item['target_price'] : null,
            'stop_loss_price'      => isset($item['stop_loss_price']) && $item['stop_loss_price'] !== '' ? (float) $item['stop_loss_price'] : null,
            'why_picked'           => trim($item['why_picked'] ?? ''),
            'bull_thesis'          => !empty($item['bull_thesis']) ? trim($item['bull_thesis']) : null,
            'bear_thesis'          => !empty($item['bear_thesis']) ? trim($item['bear_thesis']) : null,
            'invalidation_trigger' => !empty($item['invalidation_trigger']) ? trim($item['invalidation_trigger']) : null,
            'time_horizon'         => in_array($item['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $item['time_horizon'] : 'mid_term',
            'check_interval_hours' => (int) ($item['check_interval_hours'] ?? 24),
            'last_checked_at'      => $item['last_checked_at'] ?? null,
            'is_purchased'         => !empty($item['is_purchased']) ? 1 : 0,
            'purchase_price'       => isset($item['purchase_price']) ? (float) $item['purchase_price'] : null,
            'purchase_shares'      => isset($item['purchase_shares']) ? (int) $item['purchase_shares'] : null,
            'purchased_at'         => $item['purchased_at'] ?? null,
            'item_status'          => in_array($item['item_status'] ?? '', ['suggested', 'holding', 'sell_recommended', 'sold'], true) ? $item['item_status'] : 'suggested',
            'asset_strategy'       => in_array($item['asset_strategy'] ?? '', ['buy', 'hold', 'sell', 'rebalance', 'watch'], true) ? $item['asset_strategy'] : 'hold',
            'broker_fee_type'      => in_array($item['broker_fee_type'] ?? '', ['fixed', 'percent'], true) ? $item['broker_fee_type'] : 'fixed',
            'broker_fee_value'     => (float) ($item['broker_fee_value'] ?? 0.0000),
            'total_fees_paid'      => (float) ($item['total_fees_paid'] ?? 0.0000),
            'created'              => $item['created'] ?? $now,
            'updated'              => $item['updated'] ?? $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Find a single portfolio by ID
     * 
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `portfolios` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find portfolio by id {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve all items belonging to a portfolio
     * 
     * @param int $portfolioId
     * @return array
     */
    public static function getItems(int $portfolioId): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `portfolio_items` WHERE `portfolio_id` = :pid ORDER BY `id` ASC");
            $stmt->execute(['pid' => $portfolioId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch items for portfolio {$portfolioId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all portfolios ordered by creation date descending
     * 
     * @return array
     */
    public static function getAll(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "SELECT 
                        p.*,
                        COUNT(pi.`id`) AS `item_count`,
                        SUM(CASE WHEN pi.`is_purchased` = 1 THEN 1 ELSE 0 END) AS `purchased_count`
                    FROM `portfolios` p
                    LEFT JOIN `portfolio_items` pi ON p.`id` = pi.`portfolio_id`
                    GROUP BY p.`id`
                    ORDER BY p.`created` DESC, p.`id` DESC";

            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch all portfolios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update the current value of a portfolio
     * 
     * @param int $id
     * @param float $currentValue
     * @return bool
     */
    public static function updateValues(int $id, float $currentValue): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolios` SET `current_value` = :val, `updated` = NOW() WHERE `id` = :id");
            return $stmt->execute([
                'val' => $currentValue,
                'id'  => $id
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update values for portfolio {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an item's current market price and last_checked_at timestamp
     * 
     * @param int $itemId
     * @param float $currentPrice
     * @return bool
     */
    public static function updateItemPrice(int $itemId, float $currentPrice): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolio_items` 
                                   SET `current_price` = :price, 
                                       `last_checked_at` = NOW(), 
                                       `updated` = NOW() 
                                   WHERE `id` = :id");
            return $stmt->execute([
                'price' => $currentPrice,
                'id'    => $itemId
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update price for portfolio_item {$itemId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update portfolio strategy summary
     * 
     * @param int $id
     * @param string $summary
     * @return bool
     */
    public static function updateStrategySummary(int $id, string $summary): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolios` SET `strategy_summary` = :summary, `updated` = NOW() WHERE `id` = :id");
            return $stmt->execute([
                'summary' => trim($summary),
                'id'      => $id
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update strategy summary for portfolio {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark a portfolio item as purchased
     * 
    /**
     * Retrieve a single portfolio item by primary ID
     * 
     * @param int $itemId
     * @return array|null
     */
    public static function getItemById(int $itemId): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `portfolio_items` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find portfolio item by ID {$itemId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark / Execute a purchase of shares with broker fees and cash deduction
     * 
     * @param int $itemId
     * @param float $price
     * @param int $shares
     * @param string $feeType 'fixed' or 'percent'
     * @param float $feeValue
     * @return array Execution summary
     */
    public static function buyItem(int $itemId, float $price, int $shares, string $feeType = 'fixed', float $feeValue = 0.0): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $item = self::getItemById($itemId);
        if (!$item) {
            throw new InvalidArgumentException("Portfolio item #{$itemId} not found.");
        }

        $portfolioId = (int) $item['portfolio_id'];
        $portfolio = self::findById($portfolioId);
        if (!$portfolio) {
            throw new InvalidArgumentException("Parent portfolio not found.");
        }

        if ($price <= 0 || $shares <= 0) {
            throw new InvalidArgumentException("Purchase price and shares must be greater than zero.");
        }

        $feeType = in_array(strtolower($feeType), ['percent', 'fixed'], true) ? strtolower($feeType) : 'fixed';
        $feeValue = max(0.0, (float) $feeValue);
        $computedFee = ($feeType === 'percent') ? round($price * $shares * ($feeValue / 100), 4) : round($feeValue, 4);
        $totalCost = round(($price * $shares) + $computedFee, 2);

        // Calculate uninvested cash buffer
        $items = self::getItems($portfolioId);
        $totalInvested = 0.0;
        foreach ($items as $it) {
            if (!empty($it['is_purchased']) && (int) ($it['purchase_shares'] ?? 0) > 0) {
                $totalInvested += (float) $it['purchase_price'] * (int) $it['purchase_shares'];
            }
        }
        $currentCash = $portfolio['cash_balance'] !== null 
            ? (float) $portfolio['cash_balance'] 
            : max(0.0, round((float) $portfolio['initial_capital'] - $totalInvested, 2));

        $newCash = max(0.0, round($currentCash - $totalCost, 2));

        // Deduct from portfolio cash_balance
        $stmtCash = $pdo->prepare("UPDATE `portfolios` SET `cash_balance` = :cash, `updated` = NOW() WHERE `id` = :pid");
        $stmtCash->execute(['cash' => $newCash, 'pid' => $portfolioId]);

        // Position sizing & weighted average calculation
        $oldShares = (int) ($item['purchase_shares'] ?? 0);
        $oldPrice = (float) ($item['purchase_price'] ?? 0.0);
        $oldFees = (float) ($item['total_fees_paid'] ?? 0.0);

        if ($oldShares > 0 && $oldPrice > 0) {
            $newShares = $oldShares + $shares;
            $newAvgPrice = round((($oldPrice * $oldShares) + ($price * $shares)) / $newShares, 4);
        } else {
            $newShares = $shares;
            $newAvgPrice = round($price, 4);
        }
        $newTotalFees = round($oldFees + $computedFee, 4);

        $sql = "UPDATE `portfolio_items` 
                SET `is_purchased` = 1,
                    `purchase_price` = :price,
                    `purchase_shares` = :shares,
                    `purchased_at` = NOW(),
                    `item_status` = 'holding',
                    `broker_fee_type` = :fee_type,
                    `broker_fee_value` = :fee_value,
                    `total_fees_paid` = :total_fees,
                    `asset_strategy` = 'hold',
                    `updated` = NOW()
                WHERE `id` = :id";

        $stmtItem = $pdo->prepare($sql);
        $stmtItem->execute([
            'price'      => $newAvgPrice,
            'shares'     => $newShares,
            'fee_type'   => $feeType,
            'fee_value'  => $feeValue,
            'total_fees' => $newTotalFees,
            'id'         => $itemId,
        ]);

        self::recalculatePortfolioValue($portfolioId);

        return [
            'success'      => true,
            'item_id'      => $itemId,
            'portfolio_id' => $portfolioId,
            'bought_shares'=> $shares,
            'price'        => $price,
            'fee'          => $computedFee,
            'total_cost'   => $totalCost,
            'new_shares'   => $newShares,
            'avg_price'    => $newAvgPrice,
            'new_cash'     => $newCash,
        ];
    }

    /**
     * Backward-compatible alias for markItemPurchased
     */
    public static function markItemPurchased(int $itemId, float $price, int $shares): bool {
        $res = self::buyItem($itemId, $price, $shares, 'fixed', 0.0);
        return !empty($res['success']);
    }

    /**
     * Sell shares with broker fees and cash buffer return
     * 
     * @param int $itemId
     * @param float $sellPrice
     * @param int $sharesSold
     * @param string $feeType 'fixed' or 'percent'
     * @param float $feeValue
     * @return array Execution summary
     */
    public static function sellItem(int $itemId, float $sellPrice, int $sharesSold, string $feeType = 'fixed', float $feeValue = 0.0): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $item = self::getItemById($itemId);
        if (!$item) {
            throw new InvalidArgumentException("Portfolio item #{$itemId} not found.");
        }

        $portfolioId = (int) $item['portfolio_id'];
        $portfolio = self::findById($portfolioId);
        if (!$portfolio) {
            throw new InvalidArgumentException("Parent portfolio not found.");
        }

        $currentShares = (int) ($item['purchase_shares'] ?? 0);
        if ($currentShares <= 0 || empty($item['is_purchased'])) {
            throw new InvalidArgumentException("Cannot sell asset with zero active shares.");
        }

        if ($sharesSold <= 0 || $sharesSold > $currentShares) {
            throw new InvalidArgumentException("Shares to sell ({$sharesSold}) cannot exceed held shares ({$currentShares}).");
        }

        if ($sellPrice <= 0) {
            throw new InvalidArgumentException("Sell price must be greater than zero.");
        }

        $feeType = in_array(strtolower($feeType), ['percent', 'fixed'], true) ? strtolower($feeType) : 'fixed';
        $feeValue = max(0.0, (float) $feeValue);
        $computedFee = ($feeType === 'percent') ? round($sellPrice * $sharesSold * ($feeValue / 100), 4) : round($feeValue, 4);
        $grossProceeds = round($sellPrice * $sharesSold, 2);
        $netProceeds = max(0.0, round($grossProceeds - $computedFee, 2));

        // Add net proceeds back to parent portfolio cash_balance
        $items = self::getItems($portfolioId);
        $totalInvested = 0.0;
        foreach ($items as $it) {
            if (!empty($it['is_purchased']) && (int) ($it['purchase_shares'] ?? 0) > 0) {
                $totalInvested += (float) $it['purchase_price'] * (int) $it['purchase_shares'];
            }
        }
        $currentCash = $portfolio['cash_balance'] !== null 
            ? (float) $portfolio['cash_balance'] 
            : max(0.0, round((float) $portfolio['initial_capital'] - $totalInvested, 2));

        $newCash = round($currentCash + $netProceeds, 2);
        $stmtCash = $pdo->prepare("UPDATE `portfolios` SET `cash_balance` = :cash, `updated` = NOW() WHERE `id` = :pid");
        $stmtCash->execute(['cash' => $newCash, 'pid' => $portfolioId]);

        $remainingShares = $currentShares - $sharesSold;
        $oldFees = (float) ($item['total_fees_paid'] ?? 0.0);
        $newTotalFees = round($oldFees + $computedFee, 4);

        if ($remainingShares === 0) {
            // All shares liquidated -> flip back to suggested/watch status
            $sql = "UPDATE `portfolio_items` 
                    SET `is_purchased` = 0,
                        `purchase_shares` = 0,
                        `purchase_price` = NULL,
                        `item_status` = 'suggested',
                        `asset_strategy` = 'watch',
                        `broker_fee_type` = :fee_type,
                        `broker_fee_value` = :fee_value,
                        `total_fees_paid` = :total_fees,
                        `updated` = NOW()
                    WHERE `id` = :id";
            $stmtItem = $pdo->prepare($sql);
            $stmtItem->execute([
                'fee_type'   => $feeType,
                'fee_value'  => $feeValue,
                'total_fees' => $newTotalFees,
                'id'         => $itemId,
            ]);
        } else {
            // Partial sell -> remain holding with lower shares count
            $sql = "UPDATE `portfolio_items` 
                    SET `purchase_shares` = :shares,
                        `broker_fee_type` = :fee_type,
                        `broker_fee_value` = :fee_value,
                        `total_fees_paid` = :total_fees,
                        `updated` = NOW()
                    WHERE `id` = :id";
            $stmtItem = $pdo->prepare($sql);
            $stmtItem->execute([
                'shares'     => $remainingShares,
                'fee_type'   => $feeType,
                'fee_value'  => $feeValue,
                'total_fees' => $newTotalFees,
                'id'         => $itemId,
            ]);
        }

        self::recalculatePortfolioValue($portfolioId);

        return [
            'success'          => true,
            'item_id'          => $itemId,
            'portfolio_id'     => $portfolioId,
            'shares_sold'      => $sharesSold,
            'sell_price'       => $sellPrice,
            'fee'              => $computedFee,
            'gross_proceeds'   => $grossProceeds,
            'net_proceeds'     => $netProceeds,
            'remaining_shares' => $remainingShares,
            'new_cash'         => $newCash,
        ];
    }

    /**
     * Update asset risk metrics, strategy signals, and execution parameters
     * 
     * @param int $itemId
     * @param array $data
     * @return bool
     */
    public static function updateAssetDetails(int $itemId, array $data): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $item = self::getItemById($itemId);
        if (!$item) {
            return false;
        }

        $targetPrice = (isset($data['target_price']) && $data['target_price'] !== '' && (float) $data['target_price'] > 0) ? (float) $data['target_price'] : null;
        $stopLossPrice = (isset($data['stop_loss_price']) && $data['stop_loss_price'] !== '' && (float) $data['stop_loss_price'] > 0) ? (float) $data['stop_loss_price'] : null;
        $strategy = in_array($data['asset_strategy'] ?? '', ['buy', 'hold', 'sell', 'rebalance', 'watch'], true) ? $data['asset_strategy'] : ($item['asset_strategy'] ?? 'hold');
        $suggestedShares = isset($data['suggested_shares']) ? max(1, (int) $data['suggested_shares']) : (int) ($item['suggested_shares'] ?? 1);
        $timeHorizon = in_array($data['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $data['time_horizon'] : ($item['time_horizon'] ?? 'mid_term');
        $checkInterval = isset($data['check_interval_hours']) ? max(1, (int) $data['check_interval_hours']) : (int) ($item['check_interval_hours'] ?? 24);
        $whyPicked = isset($data['why_picked']) ? trim($data['why_picked']) : $item['why_picked'];
        $invalidationTrigger = isset($data['invalidation_trigger']) ? trim($data['invalidation_trigger']) : $item['invalidation_trigger'];

        try {
            $stmt = $pdo->prepare("UPDATE `portfolio_items` 
                                   SET `target_price` = :target,
                                       `stop_loss_price` = :stop_loss,
                                       `asset_strategy` = :strategy,
                                       `suggested_shares` = :shares,
                                       `time_horizon` = :horizon,
                                       `check_interval_hours` = :interval_hours,
                                       `why_picked` = :why,
                                       `invalidation_trigger` = :trigger_txt,
                                       `updated` = NOW()
                                   WHERE `id` = :id");
            return $stmt->execute([
                'target'         => $targetPrice,
                'stop_loss'      => $stopLossPrice,
                'strategy'       => $strategy,
                'shares'         => $suggestedShares,
                'horizon'        => $timeHorizon,
                'interval_hours' => $checkInterval,
                'why'            => $whyPicked,
                'trigger_txt'    => $invalidationTrigger,
                'id'             => $itemId,
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update asset details for item {$itemId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a new asset to an existing portfolio with live quote initialization
     * 
     * @param int $portfolioId
     * @param array $data
     * @return int Inserted asset ID
     */
    public static function addAssetToPortfolio(int $portfolioId, array $data): int {
        require_once dirname(__DIR__) . '/services/MarketDataService.php';

        $symbol = strtoupper(trim($data['symbol'] ?? ''));
        if ($symbol === '') {
            throw new InvalidArgumentException("Symbol is required.");
        }

        $quote = MarketDataService::getQuote($symbol);
        $currentPrice = (float) ($quote['price'] ?? $data['current_price'] ?? 0.0);
        $assetName = trim($data['asset_name'] ?? ($quote['name'] ?? $symbol));
        $exchange = trim($data['exchange'] ?? ($quote['exchange'] ?? 'US'));
        $exchangeTz = trim($data['exchange_timezone'] ?? 'America/New_York');

        $targetPrice = (isset($data['target_price']) && $data['target_price'] !== '' && (float) $data['target_price'] > 0) ? (float) $data['target_price'] : null;
        $stopLossPrice = (isset($data['stop_loss_price']) && $data['stop_loss_price'] !== '' && (float) $data['stop_loss_price'] > 0) ? (float) $data['stop_loss_price'] : null;
        $strategy = in_array($data['asset_strategy'] ?? '', ['buy', 'hold', 'sell', 'rebalance', 'watch'], true) ? $data['asset_strategy'] : 'watch';
        $suggestedShares = isset($data['suggested_shares']) ? max(1, (int) $data['suggested_shares']) : 1;

        $itemId = self::addItem($portfolioId, [
            'symbol'               => $symbol,
            'asset_name'           => $assetName,
            'asset_type'           => trim($data['asset_type'] ?? 'stock'),
            'exchange'             => $exchange,
            'exchange_timezone'    => $exchangeTz,
            'current_price'        => $currentPrice,
            'suggested_price'      => $currentPrice,
            'suggested_shares'     => $suggestedShares,
            'why_picked'           => trim($data['why_picked'] ?? "Added to portfolio radar for active surveillance."),
            'bull_thesis'          => !empty($data['bull_thesis']) ? trim($data['bull_thesis']) : null,
            'bear_thesis'          => !empty($data['bear_thesis']) ? trim($data['bear_thesis']) : null,
            'invalidation_trigger' => !empty($data['invalidation_trigger']) ? trim($data['invalidation_trigger']) : null,
            'time_horizon'         => in_array($data['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $data['time_horizon'] : 'mid_term',
            'check_interval_hours' => isset($data['check_interval_hours']) ? max(1, (int) $data['check_interval_hours']) : 24,
            'target_price'         => $targetPrice,
            'stop_loss_price'      => $stopLossPrice,
            'asset_strategy'       => $strategy,
            'is_purchased'         => 0,
            'item_status'          => 'suggested',
        ]);

        self::recalculatePortfolioValue($portfolioId);
        return $itemId;
    }

    /**
     * Delete an un-purchased asset from the portfolio
     * 
     * @param int $itemId
     * @return bool
     */
    public static function deleteItem(int $itemId): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $item = self::getItemById($itemId);
        if (!$item) {
            return false;
        }

        if (!empty($item['is_purchased']) && (int) ($item['purchase_shares'] ?? 0) > 0) {
            throw new RuntimeException("Cannot delete asset with active holding shares. Sell the position first.");
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM `portfolio_items` WHERE `id` = :id");
            $ok = $stmt->execute(['id' => $itemId]);
            if ($ok) {
                self::recalculatePortfolioValue((int) $item['portfolio_id']);
            }
            return $ok;
        } catch (Throwable $e) {
            error_log("Failed to delete portfolio item {$itemId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adjust portfolio initial capital and synchronize cash balance
     * 
     * @param int $portfolioId
     * @param float $newCapital
     * @return bool
     */
    public static function updatePortfolioCapital(int $portfolioId, float $newCapital): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        if ($newCapital <= 0) {
            throw new InvalidArgumentException("Portfolio capital must be greater than zero.");
        }

        $portfolio = self::findById($portfolioId);
        if (!$portfolio) {
            return false;
        }

        $oldCapital = (float) $portfolio['initial_capital'];
        $diff = $newCapital - $oldCapital;

        if ($portfolio['cash_balance'] !== null) {
            $newCash = max(0.0, round((float) $portfolio['cash_balance'] + $diff, 2));
            $stmt = $pdo->prepare("UPDATE `portfolios` SET `initial_capital` = :cap, `cash_balance` = :cash, `updated` = NOW() WHERE `id` = :id");
            $stmt->execute(['cap' => $newCapital, 'cash' => $newCash, 'id' => $portfolioId]);
        } else {
            $stmt = $pdo->prepare("UPDATE `portfolios` SET `initial_capital` = :cap, `updated` = NOW() WHERE `id` = :id");
            $stmt->execute(['cap' => $newCapital, 'id' => $portfolioId]);
        }

        self::recalculatePortfolioValue($portfolioId);
        return true;
    }

    /**
     * Check if a portfolio has any active purchased positions
     * 
     * @param int $portfolioId
     * @return bool
     */
    public static function hasActivePositions(int $portfolioId): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `portfolio_items` WHERE `portfolio_id` = :pid AND `is_purchased` = 1 AND `purchase_shares` > 0");
            $stmt->execute(['pid' => $portfolioId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log("Error checking active positions for portfolio {$portfolioId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save an AI Council or User chat message with optional structured action
     * 
     * @param int $portfolioId
     * @param string $role 'user', 'assistant', or 'system'
     * @param string $message
     * @param array|null $action
     * @return int Inserted message ID
     */
    public static function saveChatMessage(int $portfolioId, string $role, string $message, ?array $action = null): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return 0;
        }

        if (!in_array($role, ['user', 'assistant', 'system'], true)) {
            $role = 'user';
        }

        try {
            $sql = "INSERT INTO `portfolio_chat_messages` (`portfolio_id`, `role`, `message`, `structured_action`, `created`) 
                    VALUES (:pid, :role, :msg, :action, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'pid'    => $portfolioId,
                'role'   => $role,
                'msg'    => trim($message),
                'action' => $action !== null ? json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log("Failed to save portfolio chat message: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieve chronological chat message history for a portfolio
     * 
     * @param int $portfolioId
     * @param int $limit
     * @return array
     */
    public static function getChatHistory(int $portfolioId, int $limit = 50): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `portfolio_chat_messages` WHERE `portfolio_id` = :pid ORDER BY `id` ASC LIMIT :lim");
            $stmt->bindValue(':pid', $portfolioId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$r) {
                if (!empty($r['structured_action'])) {
                    $r['structured_action'] = json_decode($r['structured_action'], true);
                } else {
                    $r['structured_action'] = null;
                }
            }
            return $rows;
        } catch (Throwable $e) {
            error_log("Failed to fetch chat history for portfolio {$portfolioId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific chat message by ID
     * 
     * @param int $messageId
     * @return array|null
     */
    public static function getChatMessageById(int $messageId): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `portfolio_chat_messages` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $messageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['structured_action'] = !empty($row['structured_action']) ? json_decode($row['structured_action'], true) : null;
                return $row;
            }
            return null;
        } catch (Throwable $e) {
            error_log("Failed to get chat message {$messageId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update structured action JSON on a chat message (e.g. mark executed)
     * 
     * @param int $messageId
     * @param array $action
     * @return bool
     */
    public static function updateChatMessageAction(int $messageId, array $action): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolio_chat_messages` SET `structured_action` = :act WHERE `id` = :id");
            return $stmt->execute([
                'act' => json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'id'  => $messageId,
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update chat message action {$messageId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all chat messages for a specific portfolio
     * 
     * @param int $portfolioId
     * @return bool
     */
    public static function clearChatHistory(int $portfolioId): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM `portfolio_chat_messages` WHERE `portfolio_id` = :pid");
            return $stmt->execute(['pid' => $portfolioId]);
        } catch (Throwable $e) {
            error_log("Failed to clear chat history for portfolio {$portfolioId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set alert status and message for a portfolio
     * 
     * @param int $id
     * @param string $status
     * @param string|null $message
     * @return bool
     */
    public static function setAlertStatus(int $id, string $status, ?string $message = null): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        if (!in_array($status, ['normal', 'action_needed', 'urgent'], true)) {
            $status = 'normal';
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolios` 
                                   SET `alert_status` = :status, 
                                       `alert_message` = :message, 
                                       `updated` = NOW() 
                                   WHERE `id` = :id");
            return $stmt->execute([
                'status'  => $status,
                'message' => $message,
                'id'      => $id
            ]);
        } catch (Throwable $e) {
            error_log("Failed to set alert status for portfolio {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the item status of a portfolio item
     * 
     * @param int $itemId
     * @param string $status
     * @return bool
     */
    public static function updateItemStatus(int $itemId, string $status): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        if (!in_array($status, ['suggested', 'holding', 'sell_recommended', 'sold'], true)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `portfolio_items` 
                                   SET `item_status` = :status, 
                                       `updated` = NOW() 
                                   WHERE `id` = :id");
            return $stmt->execute([
                'status' => $status,
                'id'     => $itemId
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update status for portfolio_item {$itemId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalculate and update the aggregated current_value of a portfolio
     * 
     * @param int $portfolioId
     * @return float New current_value
     */
    public static function recalculatePortfolioValue(int $portfolioId): float {
        $portfolio = self::findById($portfolioId);
        if (!$portfolio) {
            return 0.0;
        }

        $items = self::getItems($portfolioId);
        $totalInvested = 0.0;
        $currentHoldingsValue = 0.0;
        $purchasedCount = 0;

        foreach ($items as $it) {
            if (!empty($it['is_purchased']) && (int) ($it['purchase_shares'] ?? 0) > 0) {
                $purchasedCount++;
                $buyPrice = (float) $it['purchase_price'];
                $shares = (int) $it['purchase_shares'];
                $costBasis = $buyPrice * $shares;
                $marketVal = (float) $it['current_price'] * $shares;
                $totalInvested += $costBasis;
                $currentHoldingsValue += $marketVal;
            }
        }

        $initialCapital = (float) $portfolio['initial_capital'];
        if ($portfolio['cash_balance'] !== null) {
            $cashBuffer = (float) $portfolio['cash_balance'];
        } else {
            $cashBuffer = max(0.0, round($initialCapital - $totalInvested, 2));
        }

        if ($purchasedCount > 0) {
            $newVal = round($currentHoldingsValue + $cashBuffer, 2);
        } else {
            $newVal = $portfolio['cash_balance'] !== null ? round($cashBuffer, 2) : $initialCapital;
        }

        self::updateValues($portfolioId, $newVal);
        return $newVal;
    }

    /**
     * Delete a portfolio (strict safeguard: cannot delete if active positions exist)
     * 
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        if (self::hasActivePositions($id)) {
            throw new RuntimeException("Cannot delete portfolio with active holdings. Sell all shares first.");
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM `portfolios` WHERE `id` = :id");
            return $stmt->execute(['id' => $id]);
        } catch (Throwable $e) {
            error_log("Failed to delete portfolio {$id}: " . $e->getMessage());
            return false;
        }
    }
}
