<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Assign redeem keys for an order (idempotent). Matches current schema: order_details, redeem_keys, order_redeems.
function assignRedeemKeys(PDO $conn, int $orderId): array {
    $result = [
        'success' => true,
        'assigned' => [],
        'shortages' => [],
    ];

    // Prevent duplicate assignment
    $chk = $conn->prepare("SELECT COUNT(*) FROM order_redeems WHERE order_id = ?");
    $chk->execute([$orderId]);
    if ((int)$chk->fetchColumn() > 0) {
        return $result;
    }

    // Get order owner
    $o = $conn->prepare("SELECT user_id FROM orders WHERE order_id = ? LIMIT 1");
    $o->execute([$orderId]);
    $owner = $o->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        return ['success' => false, 'assigned' => [], 'shortages' => ['order' => 'not_found']];
    }
    $userId = (int)$owner['user_id'];

    // Load order details
    $d = $conn->prepare(
        "SELECT d.product_id, d.package_id, d.quantity, p.name, pp.title
         FROM order_details d
         JOIN products p ON d.product_id = p.product_id
         LEFT JOIN product_prices pp ON d.package_id = pp.id
         WHERE d.order_id = ?"
    );
    $d->execute([$orderId]);
    $items = $d->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        return ['success' => false, 'assigned' => [], 'shortages' => ['details' => 'empty']];
    }

    try {
        $conn->beginTransaction();

        $insRedeem = $conn->prepare("INSERT INTO order_redeems (order_id, product_id, codes, created_at) VALUES (?, ?, ?, NOW())");

        foreach ($items as $row) {
            $pid = (int)$row['product_id'];
            $need = max(0, (int)$row['quantity']);
            if ($need === 0) { continue; }

            // Build SELECT with literal LIMIT for compatibility
            $limit = (int)$need;
            $sql = "SELECT key_id, key_code FROM redeem_keys
                    WHERE product_id = ? AND status = 'unused'
                    ORDER BY key_id ASC
                    LIMIT $limit FOR UPDATE";
            $sel = $conn->prepare($sql);
            $sel->bindValue(1, $pid, PDO::PARAM_INT);
            $sel->execute();
            $keys = $sel->fetchAll(PDO::FETCH_ASSOC);

            if (count($keys) < $need) {
                $result['success'] = false;
                $result['shortages'][$pid] = $need - count($keys);
            }
            if (empty($keys)) { continue; }

            $ids = array_column($keys, 'key_id');
            $codes = array_column($keys, 'key_code');

            // Mark keys as used
            $place = implode(',', array_fill(0, count($ids), '?'));
            $upd = $conn->prepare("UPDATE redeem_keys SET status='used', used_by=?, used_at=NOW() WHERE key_id IN ($place)");
            $i = 1;
            $upd->bindValue($i++, $userId, PDO::PARAM_INT);
            foreach ($ids as $kid) { $upd->bindValue($i++, (int)$kid, PDO::PARAM_INT); }
            $upd->execute();

            // Save snapshot per product
            $insRedeem->execute([$orderId, $pid, implode(',', $codes)]);

            $label = ($row['name'] ?? 'Product') . (isset($row['title']) && $row['title'] !== null ? (' (' . $row['title'] . ')') : '');
            $result['assigned'][$label] = $codes;
        }

        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        return ['success' => false, 'assigned' => [], 'shortages' => ['exception' => $e->getMessage()]];
    }

    return $result;
}
