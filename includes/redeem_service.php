<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function assignRedeemKeys(PDO $conn, int $orderId): array {
    $result = [
        'success' => true,
        'assigned' => [],
        'shortages' => [],
    ];

    $chk = $conn->prepare("SELECT COUNT(*) FROM order_redeems WHERE order_id = ?");
    $chk->execute([$orderId]);
    if ((int)$chk->fetchColumn() > 0) {
        return $result;
    }

    $orderStmt = $conn->prepare("SELECT user_id FROM orders WHERE order_id = ? LIMIT 1");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['success' => false, 'assigned' => [], 'shortages' => ['order' => 'not_found']];
    }
    $userId = (int)$order['user_id'];

    $detailsStmt = $conn->prepare(
        "SELECT d.product_id, d.package_id, d.quantity, p.name, pp.title
         FROM order_details d
         JOIN products p ON d.product_id = p.product_id
         LEFT JOIN product_prices pp ON d.package_id = pp.id
         WHERE d.order_id = ?"
    );
    $detailsStmt->execute([$orderId]);
    $details = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$details) {
        return ['success' => false, 'assigned' => [], 'shortages' => ['details' => 'empty']];
    }

    try {
        $conn->beginTransaction();

        $sel = $conn->prepare(
            "SELECT key_id, key_code FROM redeem_keys
             WHERE product_id = ? AND status = 'unused'
             ORDER BY key_id ASC
             LIMIT ? FOR UPDATE"
        );

        $insRedeem = $conn->prepare(
            "INSERT INTO order_redeems (order_id, product_id, codes, created_at) VALUES (?, ?, ?, NOW())"
        );

        foreach ($details as $d) {
            $pid = (int)$d['product_id'];
            $qty = (int)$d['quantity'];
            if ($qty <= 0) { continue; }

            $sel->bindValue(1, $pid, PDO::PARAM_INT);
            $sel->bindValue(2, $qty, PDO::PARAM_INT);
            $sel->execute();
            $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) { $result['success'] = false; $result['shortages'][$pid] = $qty; continue; }

            $codes = array_column($rows, 'key_code');
            $ids = array_column($rows, 'key_id');

            if (count($ids) < $qty) {
                $result['success'] = false;
                $result['shortages'][$pid] = $qty - count($ids);
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtSql = sprintf("UPDATE redeem_keys SET status = 'used', used_by = ?, used_at = NOW() WHERE key_id IN (%s)", $placeholders);
            $upd = $conn->prepare($stmtSql);
            $i = 1;
            $upd->bindValue($i++, $userId, PDO::PARAM_INT);
            foreach ($ids as $kid) { $upd->bindValue($i++, (int)$kid, PDO::PARAM_INT); }
            $upd->execute();

            $labelCodes = implode(',', $codes);
            $insRedeem->execute([$orderId, $pid, $labelCodes]);

            $keyLabel = ($d['name'] ?? 'Product') . (isset($d['title']) && $d['title'] !== null ? (' (' . $d['title'] . ')') : '');
            $result['assigned'][$keyLabel] = $codes;
        }

        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        return ['success' => false, 'assigned' => [], 'shortages' => ['exception' => $e->getMessage()]];
    }

    return $result;
}

