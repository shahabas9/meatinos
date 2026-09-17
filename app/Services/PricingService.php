<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;
use PDO;

final class PricingService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function latestLiveBirdCost(): float
    {
        $value = $this->pdo->query("SELECT COALESCE(SUM(total_value)/NULLIF(SUM(received_quantity),0),0) FROM bird_receipts WHERE status IN ('accepted','released') AND received_quantity>0 AND total_value>0 AND received_at>=CURDATE()-INTERVAL 90 DAY")->fetchColumn();
        if ((float) $value > 0) return round((float) $value, 4);
        return round((float) $this->pdo->query("SELECT COALESCE(MAX(unit_purchase_cost),0) FROM bird_receipts WHERE unit_purchase_cost>0")->fetchColumn(), 4);
    }

    /** @return array{live_bird_cost:float,cost_factor:float,margin_percent:float,fixed_margin:float,calculated_price:float,rate_card_price:?float,effective_price:float,hsn_code:?string} */
    public function calculate(int $productId, ?int $rateCardId = null, ?float $factor = null, ?float $margin = null, ?float $fixedMargin = null): array
    {
        $statement = $this->pdo->prepare('SELECT hsn_code,live_bird_cost_factor,default_margin_percent,standard_cost,selling_price FROM products WHERE id=? AND status=\'active\'');
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if (!$product) throw new \RuntimeException('Select an active product.', 422);

        $rateItem = null;
        $rateCard = null;
        if ($rateCardId) {
            $statement = $this->pdo->prepare("SELECT * FROM rate_cards WHERE id=? AND status='active' AND valid_from<=CURDATE() AND (valid_to IS NULL OR valid_to>=CURDATE()) LIMIT 1");
            $statement->execute([$rateCardId]);
            $rateCard = $statement->fetch() ?: null;
            if ($rateCard) {
                $statement = $this->pdo->prepare('SELECT * FROM rate_card_items WHERE rate_card_id=? AND product_id=? LIMIT 1');
                $statement->execute([$rateCardId,$productId]);
                $rateItem = $statement->fetch() ?: null;
            }
        }
        $liveCost = $this->latestLiveBirdCost();
        $baseCost = $rateCard && (float) $rateCard['base_whole_bird_price'] > 0
            ? (float) $rateCard['base_whole_bird_price']
            : ($liveCost > 0 ? $liveCost : (float) $product['standard_cost']);
        $costFactor = $factor ?? ($rateItem ? (float) $rateItem['live_bird_cost_factor'] : (float) $product['live_bird_cost_factor']);
        $marginPercent = $margin ?? ($rateItem ? (float) $rateItem['margin_percent'] : ($rateCard ? (float) $rateCard['default_margin_percent'] : (float) $product['default_margin_percent']));
        $fixed = $fixedMargin ?? ($rateItem ? (float) $rateItem['fixed_margin'] : 0.0);
        $calculated = round(($baseCost * max(0, $costFactor)) * (1 + max(0, $marginPercent) / 100) + max(0, $fixed), 2);
        $ratePrice = $rateItem && (float) $rateItem['unit_price'] > 0 ? (float) $rateItem['unit_price'] : null;
        $effective = $ratePrice ?? ($calculated > 0 ? $calculated : (float) $product['selling_price']);
        return [
            'live_bird_cost'=>$liveCost,'cost_factor'=>$costFactor,'margin_percent'=>$marginPercent,'fixed_margin'=>$fixed,
            'calculated_price'=>$calculated,'rate_card_price'=>$ratePrice,'effective_price'=>round($effective,2),
            'hsn_code'=>$rateItem['hsn_code'] ?? $product['hsn_code'] ?? null,
        ];
    }
}
