<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\PricingService;
use PDO;

final class RateCardController
{
    public function bulk(): void
    {
        Auth::requirePermission('sales.manage');
        $pdo = Database::connection();
        $cards = $pdo->query("SELECT id,rate_card_number,name,status,valid_from,valid_to,default_margin_percent,base_whole_bird_price FROM rate_cards WHERE status<>'expired' ORDER BY FIELD(status,'active','draft','inactive'),valid_from DESC,id DESC")->fetchAll();
        $requestedId = (int) ($_GET['rate_card_id'] ?? 0);
        $card = $this->selectCard($cards, $requestedId);
        $products = [];
        if ($card) {
            $statement = $pdo->prepare("SELECT p.id,p.sku,p.name,p.hsn_code,p.unit,p.live_bird_cost_factor product_factor,p.default_margin_percent product_margin,p.selling_price,
                rci.live_bird_cost_factor,rci.margin_percent,rci.fixed_margin,rci.calculated_price,rci.unit_price,rci.minimum_quantity
                FROM products p
                LEFT JOIN rate_card_items rci ON rci.product_id=p.id AND rci.rate_card_id=?
                WHERE p.status='active' AND p.category IN ('finished_good','by_product')
                ORDER BY p.brand_name,p.name,p.sku");
            $statement->execute([$card['id']]);
            $products = $statement->fetchAll();
        }
        $suggestedBasePrice = (new PricingService($pdo))->latestLiveBirdCost();
        View::render('rate-card/bulk', [
            'title' => 'Bulk Rate Entry', 'cards' => $cards, 'card' => $card,
            'products' => $products, 'suggestedBasePrice' => $suggestedBasePrice,
            'canManage' => Auth::can('sales.manage'),
        ]);
    }

    public function saveBulk(): void
    {
        Auth::requirePermission('sales.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $rateCardId = (int) ($_POST['rate_card_id'] ?? 0);
        $basePrice = round((float) ($_POST['base_whole_bird_price'] ?? 0), 2);
        $defaultMargin = round((float) ($_POST['default_margin_percent'] ?? 0), 3);
        if ($rateCardId <= 0 || $basePrice <= 0 || $defaultMargin < 0 || $defaultMargin > 1000) {
            flash('danger', 'Select a rate card and enter a valid whole-bird base price and default margin.');
            redirect('rate-card.bulk', ['rate_card_id' => $rateCardId]);
        }
        $submitted = $_POST['products'] ?? [];
        if (!is_array($submitted)) {
            throw new \RuntimeException('Invalid bulk rate data.', 422);
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $cardStatement = $pdo->prepare("SELECT * FROM rate_cards WHERE id=? AND status<>'expired' LIMIT 1 FOR UPDATE");
            $cardStatement->execute([$rateCardId]);
            $card = $cardStatement->fetch();
            if (!$card) throw new \RuntimeException('Rate card not found or expired.', 404);

            $pdo->prepare('UPDATE rate_cards SET base_whole_bird_price=?,default_margin_percent=? WHERE id=?')
                ->execute([$basePrice, $defaultMargin, $rateCardId]);
            $allowed = array_fill_keys($pdo->query("SELECT id FROM products WHERE status='active' AND category IN ('finished_good','by_product')")->fetchAll(PDO::FETCH_COLUMN), true);
            $upsert = $pdo->prepare('INSERT INTO rate_card_items (rate_card_id,product_id,hsn_code,live_bird_cost_factor,margin_percent,fixed_margin,calculated_price,unit_price,minimum_quantity)
                VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE hsn_code=VALUES(hsn_code),live_bird_cost_factor=VALUES(live_bird_cost_factor),margin_percent=VALUES(margin_percent),fixed_margin=VALUES(fixed_margin),calculated_price=VALUES(calculated_price),unit_price=VALUES(unit_price),minimum_quantity=VALUES(minimum_quantity)');
            $productDetails = $pdo->prepare('SELECT hsn_code,live_bird_cost_factor,default_margin_percent FROM products WHERE id=?');
            $saved = 0;
            foreach ($submitted as $productId => $row) {
                $productId = (int) $productId;
                if ($productId <= 0 || !isset($allowed[$productId]) || !is_array($row)) continue;
                $productDetails->execute([$productId]);
                $product = $productDetails->fetch();
                if (!$product) continue;
                $factor = $this->boundedNumber($row['factor'] ?? $product['live_bird_cost_factor'], 0, 100, 4);
                $margin = ($row['margin'] ?? '') === ''
                    ? $defaultMargin
                    : $this->boundedNumber($row['margin'], 0, 1000, 3);
                $fixed = $this->boundedNumber($row['fixed_margin'] ?? 0, 0, 1000000, 3);
                $minimum = $this->boundedNumber($row['minimum_quantity'] ?? 0, 0, 100000000, 3);
                $calculated = round(($basePrice * $factor) * (1 + $margin / 100) + $fixed, 2);
                $override = trim((string) ($row['unit_price'] ?? ''));
                $unitPrice = $override === '' ? $calculated : $this->boundedNumber($override, 0.01, 1000000, 2);
                $upsert->execute([$rateCardId, $productId, $product['hsn_code'] ?: null, $factor, $margin, $fixed, $calculated, $unitPrice, $minimum]);
                $saved++;
            }
            AuditService::log('bulk_updated', 'rate_cards', $rateCardId, "Bulk rate card updated for {$saved} products.", null, [
                'base_whole_bird_price' => $basePrice, 'default_margin_percent' => $defaultMargin, 'product_count' => $saved,
            ]);
            $pdo->commit();
            flash('success', "Bulk rates saved for {$saved} products.");
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        redirect('rate-card.bulk', ['rate_card_id' => $rateCardId]);
    }

    public function export(): void
    {
        Auth::requirePermission('sales.manage');
        $rateCardId = (int) ($_GET['rate_card_id'] ?? 0);
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM rate_cards WHERE id=? LIMIT 1');
        $statement->execute([$rateCardId]);
        $card = $statement->fetch();
        if (!$card) throw new \RuntimeException('Rate card not found.', 404);
        $statement = $pdo->prepare("SELECT p.sku,p.name product,p.hsn_code,p.unit,rci.live_bird_cost_factor cost_factor,rci.margin_percent,rci.fixed_margin,rci.calculated_price,rci.unit_price,rci.minimum_quantity
            FROM rate_card_items rci JOIN products p ON p.id=rci.product_id
            WHERE rci.rate_card_id=? ORDER BY p.brand_name,p.name,p.sku");
        $statement->execute([$rateCardId]);
        $rows = $statement->fetchAll();
        AuditService::log('exported', 'rate_cards', $rateCardId, 'Rate card exported as CSV.');
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', strtolower((string) $card['rate_card_number'])) . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Rate card', $card['rate_card_number'], 'Whole-bird base price', $card['base_whole_bird_price'], 'Default margin %', $card['default_margin_percent']]);
        if ($rows) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function selectCard(array $cards, int $requestedId): ?array
    {
        foreach ($cards as $card) {
            if ($requestedId > 0 && (int) $card['id'] === $requestedId) return $card;
        }
        return $cards[0] ?? null;
    }

    private function boundedNumber(mixed $value, float $minimum, float $maximum, int $precision): float
    {
        if (!is_numeric($value)) throw new \RuntimeException('Every rate value must be numeric.', 422);
        $number = (float) $value;
        if ($number < $minimum || $number > $maximum) throw new \RuntimeException('A rate value is outside the allowed range.', 422);
        return round($number, $precision);
    }
}
