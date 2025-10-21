<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * Assign redeem keys for an order, once (idempotent).
 * Requires tables: orders, order_items(product_id, quantity), redeem_keys, order_redeems.
 */
function assignRedeemKeys(PDO $conn, int $orderId): array {
    $result = [
        'success' => true,
        'assigned' => [],     // label => [codes]
        'shortages' => [],    // product_id => missing count
    ];

    // Already assigned?
    $chk = $conn->prepare("SELECT COUNT(*) FROM order_redeems WHERE order_id = ?");
    $chk->execute([$orderId]);
    if ((int)$chk->fetchColumn() > 0) {
        return $result; // idempotent: nothing to do
    }

    try {
        $conn->beginTransaction();

        // Lock order items to read required quantities
        $selItems = $conn->prepare("
            SELECT oi.product_id, oi.quantity, p.name, COALESCE(oi.title, NULL) AS title
            FROM order_items oi
            JOIN products p ON p.product_id = oi.product_id
            WHERE oi.order_id = ?
            FOR UPDATE
        ");
        $selItems->execute([$orderId]);
        $items = $selItems->fetchAll(PDO::FETCH_ASSOC);

        if (!$items) {
            throw new Exception("No items for order_id=" . $orderId);
        }

        $insRedeem = $conn->prepare("INSERT INTO order_redeems (order_id, product_id, codes) VALUES (?, ?, ?)");

        foreach ($items as $d) {
            $pid = (int)$d['product_id'];
            $need = (int)$d['quantity'];

            // Select unused keys for this product
            $sel = $conn->prepare("
                SELECT key_id, key_code FROM redeem_keys
                WHERE product_id = ? AND status = 'unused'
                ORDER BY key_id ASC
                LIMIT ?
                FOR UPDATE
            ");
            $sel->bindValue(1, $pid, PDO::PARAM_INT);
            $sel->bindValue(2, $need, PDO::PARAM_INT);
            $sel->execute();
            $keys = $sel->fetchAll(PDO::FETCH_ASSOC);

            if (count($keys) < $need) {
                $result['shortages'][$pid] = $need - count($keys);
            }

            if (empty($keys)) { continue; }

            $ids = array_column($keys, 'key_id');
            $codes = array_column($keys, 'key_code');

            // Mark as used and link to order
            $mark = $conn->prepare("
                UPDATE redeem_keys
                SET status = 'used', used_at = NOW(), order_id = ?, used_by = ?
                WHERE key_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            ");
            $bindIndex = 1;
            $mark->bindValue($bindIndex++, $orderId, PDO::PARAM_INT);
            $userId = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;
            if ($userId !== null) {
                $mark->bindValue($bindIndex++, $userId, PDO::PARAM_INT);
            } else {
                $mark->bindValue($bindIndex++, null, PDO::PARAM_NULL);
            }
            foreach ($ids as $kid) { $mark->bindValue($bindIndex++, (int)$kid, PDO::PARAM_INT); }
            $mark->execute();

            // Save codes snapshot per product
            $labelCodes = implode("\n", $codes);
            $insRedeem->execute([$orderId, $pid, $labelCodes]);

            $keyLabel = ($d['name'] ?? 'Product') . ($d['title'] !== null ? (' (' . $d['title'] . ')') : '');
            $result['assigned'][$keyLabel] = $codes;
        }

        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        return ['success' => false, 'assigned' => [], 'shortages' => ['exception' => $e->getMessage()]];
    }

    return $result;
}

/**
 * Simple helper: bulk insert keys for a product.
 * @param PDO $conn
 * @param int $productId
 * @param array $codes
 * @return array [inserted => n, duplicates => [code,...]]
 */
function bulkAddRedeemKeys(PDO $conn, int $productId, array $codes): array {
    $ins = $conn->prepare("INSERT IGNORE INTO redeem_keys (product_id, key_code) VALUES (?, ?)");
    $dups = [];
    $ok = 0;
    foreach ($codes as $c) {
        $code = trim($c);
        if ($code === '') continue;
        $r = $ins->execute([$productId, $code]);
        if ($r && $ins->rowCount() === 1) {
            $ok++;
        } else {
            $dups[] = $code;
        }
    }
    return ['inserted' => $ok, 'duplicates' => $dups];
}
