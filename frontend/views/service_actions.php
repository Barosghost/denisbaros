<?php
define('PAGE_ACCESS', 'service_actions');
require_once '../../backend/includes/auth_required.php';

$action = $_REQUEST['action'] ?? '';

// 1. UPDATE STATUS & ASSIGNMENT
if ($action == 'update_status' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_request = $_POST['id_request'];
    $status = $_POST['status'];
    $id_technician = $_POST['id_technician'] > 0 ? $_POST['id_technician'] : null;
    $comment = trim($_POST['log_comment']);
    $time_spent = (int) ($_POST['time_spent'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Update Request
        if ($status == 'terminé' || $status == 'livré') {
            $stmt = $pdo->prepare("UPDATE service_requests SET status = ?, id_technician = ?, time_spent_minutes = time_spent_minutes + ?, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id_request = ?");
            $stmt->execute([$status, $id_technician, $time_spent, $id_request]);
        } else {
            $stmt = $pdo->prepare("UPDATE service_requests SET status = ?, id_technician = ?, time_spent_minutes = time_spent_minutes + ?, updated_at = CURRENT_TIMESTAMP WHERE id_request = ?");
            $stmt->execute([$status, $id_technician, $time_spent, $id_request]);
        }

        // --- INTERNAL STOCK LOGIC START ---
        $stmt_check = $pdo->prepare("SELECT is_internal, id_item FROM service_requests WHERE id_request = ?");
        $stmt_check->execute([$id_request]);
        $sr_info = $stmt_check->fetch();

        if ($sr_info && $sr_info['is_internal'] && $sr_info['id_item']) {
            $item_id = $sr_info['id_item'];

            // Get product ID for aggregate stock
            $stmt_prod = $pdo->prepare("SELECT id_product, serial_number FROM product_items WHERE id_item = ?");
            $stmt_prod->execute([$item_id]);
            $item_data = $stmt_prod->fetch();
            $prod_id = $item_data['id_product'];
            $serial = $item_data['serial_number'];

            if ($status == 'terminé') {
                // Return to sellable stock
                $pdo->exec("UPDATE product_items SET status = 'en_stock', current_condition = 'repare', is_sellable = 1 WHERE id_item = $item_id");
                $pdo->exec("UPDATE stock SET quantity = quantity + 1 WHERE id_product = $prod_id");

                require_once '../../backend/config/functions.php';
                logStockMovement($pdo, $prod_id, $_SESSION['user_id'], 'IN', 1, "Retour de réparation (S/N: $serial)");
            } elseif ($status == 'non_reparable') {
                // Mark as loss
                $pdo->exec("UPDATE product_items SET status = 'perte', is_sellable = 0 WHERE id_item = $item_id");

                require_once '../../backend/config/functions.php';
                logStockMovement($pdo, $prod_id, $_SESSION['user_id'], 'OUT', 0, "Perte définitive (Non réparable, S/N: $serial)");
            }
        }
        // --- INTERNAL STOCK LOGIC END ---

        // Add Log
        $stmt = $pdo->prepare("INSERT INTO service_logs (id_request, id_user, id_technician, action, details, time_spent_added) VALUES (?, ?, ?, ?, ?, ?)");
        $action_label = str_replace('_', ' ', ucfirst($status));
        $stmt->execute([$id_request, $_SESSION['user_id'], $id_technician, $action_label, $comment, $time_spent]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ticket mis à jour avec succès']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// 2. ADD PAYMENT
if ($action == 'register_payment' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_request = $_POST['id_request'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'] ?: 'Espèces';

    try {
        $pdo->beginTransaction();

        // 1. Insert Payment
        $stmt = $pdo->prepare("INSERT INTO service_payments (id_request, id_user, amount, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_request, $_SESSION['user_id'], $amount, $method]);

        // 2. Add Log
        $stmt = $pdo->prepare("INSERT INTO service_logs (id_request, id_user, action, details) VALUES (?, ?, 'Paiement', 'Paiement de ' . ? . ' FCFA enregistré via ' . ?)");
        $stmt->execute([$id_request, $_SESSION['user_id'], number_format($amount, 0, ',', ' '), $method]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Paiement enregistré']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// 3. ADD PART (STOCKS)
if ($action == 'add_part' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_request = $_POST['id_request'];
    $id_product = $_POST['id_product'];
    $quantity = $_POST['quantity'];

    try {
        $pdo->beginTransaction();

        // 1. Check stock availability
        $stmt = $pdo->prepare("SELECT quantity FROM stock WHERE id_product = ?");
        $stmt->execute([$id_product]);
        $current_stock = $stmt->fetchColumn();

        if ($current_stock < $quantity) {
            throw new Exception("Stock insuffisant ($current_stock disponible)");
        }

        // 2. Insert into technical_stock
        $stmt = $pdo->prepare("INSERT INTO technical_stock (id_request, id_product, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$id_request, $id_product, $quantity]);

        // 3. Update main stock
        $stmt = $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE id_product = ?");
        $stmt->execute([$quantity, $id_product]);

        // 4. Log Stock Movement
        require_once '../../backend/config/functions.php';
        logStockMovement($pdo, $id_product, $_SESSION['user_id'], 'OUT', $quantity, "Utilisé pour Réparation #$id_request");

        // 5. Log Service Activity
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id_product = ?");
        $stmt->execute([$id_product]);
        $p_name = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO service_logs (id_request, id_user, action, details) VALUES (?, ?, 'Pièce ajoutée', ?)");
        $stmt->execute([$id_request, $_SESSION['user_id'], "Ajout de $quantity x $p_name"]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pièce ajoutée et stock mis à jour']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// 4. GET DETAILS (HTML fragment for modal)
if ($action == 'get_details') {
    $id = $_GET['id'];

    try {
        // Fetch Request info
        $stmt = $pdo->prepare("SELECT sr.*, 
                                     COALESCE(d.brand, p.name) as brand, 
                                     d.model, 
                                     COALESCE(d.serial_number, pi.serial_number) as serial_number, 
                                     d.configuration, 
                                     c.fullname as client_name, 
                                     c.phone as client_phone 
                             FROM service_requests sr 
                             LEFT JOIN devices d ON sr.id_device = d.id_device 
                             LEFT JOIN product_items pi ON sr.id_item = pi.id_item
                             LEFT JOIN products p ON pi.id_product = p.id_product
                             LEFT JOIN clients c ON (d.id_client = c.id_client OR (sr.is_internal=1 AND c.fullname='FBI STORE (INTERNE)'))
                             WHERE sr.id_request = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();

        if (!$r) {
            echo "Ticket introuvable.";
            exit();
        }

        // Fetch Logs
        $stmt = $pdo->prepare("SELECT sl.*, u.username, t.fullname as tech_name 
                             FROM service_logs sl 
                             JOIN users u ON sl.id_user = u.id_user 
                             LEFT JOIN technicians t ON sl.id_technician = t.id_technician 
                             WHERE sl.id_request = ? 
                             ORDER BY sl.created_at DESC");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll();

        ?>
        <div class="row">
            <div class="col-md-6 border-end border-white border-opacity-10 pe-4">
                <h6 class="text-primary fw-bold text-uppercase small mb-3">Informations Appareil</h6>
                <div class="mb-3">
                    <div class="extra-small text-muted mb-1">CLIENT</div>
                    <div class="text-white fw-bold">
                        <?= htmlspecialchars($r['client_name']) ?> <span class="text-muted fw-normal small">(
                            <?= htmlspecialchars($r['client_phone']) ?>)
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="extra-small text-muted mb-1">APPAREIL</div>
                    <div class="text-white">
                        <?= htmlspecialchars($r['brand'] . ' ' . $r['model']) ?>
                    </div>
                    <div class="extra-small font-monospace text-info">
                        <?= htmlspecialchars($r['serial_number'] ?: 'S/N: N/A') ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="extra-small text-muted mb-1">ÉTAT / CONFIG</div>
                    <div class="small text-muted-2 italic">
                        <?= nl2br(htmlspecialchars($r['configuration'] ?: 'Non spécifié')) ?>
                    </div>
                </div>
                <div class="mb-3 p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-20">
                    <div class="extra-small text-primary fw-bold text-uppercase mb-1">Problème déclaré</div>
                    <div class="small text-white"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
                </div>

                <?php
                // Fetch Total Paid
                $stmt = $pdo->prepare("SELECT SUM(amount) FROM service_payments WHERE id_request = ?");
                $stmt->execute([$id]);
                $total_paid = $stmt->fetchColumn() ?: 0;
                ?>
                <div
                    class="mt-4 p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-20 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="extra-small text-success fw-bold text-uppercase mb-0">Total Payé</div>
                        <div class="h5 mb-0 text-white fw-bold"><?= number_format($total_paid, 0, ',', ' ') ?> FCFA</div>
                    </div>
                    <?php if ($total_paid < $r['estimated_cost']): ?>
                        <div class="text-end">
                            <div class="extra-small text-warning fw-bold text-uppercase mb-0">Reste</div>
                            <div class="small mb-0 text-warning">
                                <?= number_format($r['estimated_cost'] - $total_paid, 0, ',', ' ') ?> FCFA
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="badge bg-success"><i class="fa-solid fa-check me-1"></i> SOLDÉ</div>
                    <?php endif; ?>
                </div>

                <!-- Technical Stock / Parts -->
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="extra-small text-muted fw-bold text-uppercase">Pièces & Fournitures</div>
                        <button class="btn btn-sm btn-outline-primary extra-small py-0" onclick="showAddPart(<?= $id ?>)">
                            <i class="fa-solid fa-plus"></i> AJOUTER
                        </button>
                    </div>
                    <?php
                    $stmt = $pdo->prepare("SELECT ts.*, p.name FROM technical_stock ts JOIN products p ON ts.id_product = p.id_product WHERE ts.id_request = ?");
                    $stmt->execute([$id]);
                    $parts = $stmt->fetchAll();
                    ?>
                    <div class="bg-dark bg-opacity-50 rounded-3 border border-white border-opacity-5 p-2">
                        <?php if (empty($parts)): ?>
                            <div class="extra-small text-muted text-center py-2 italic">Aucune pièce enregistrée</div>
                        <?php else: ?>
                            <?php foreach ($parts as $p): ?>
                                <div
                                    class="d-flex justify-content-between align-items-center extra-small py-1 border-bottom border-white border-opacity-5 last-border-0">
                                    <span class="text-white-50"><?= htmlspecialchars($p['name']) ?></span>
                                    <span class="badge bg-secondary opacity-50">x<?= $p['quantity'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 ps-4">
                <h6 class="text-success fw-bold text-uppercase small mb-3">Historique des travaux</h6>
                <div class="timeline ps-3" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($logs)): ?>
                        <div class="text-muted italic small">Aucun historique enregistré.</div>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <div class="mb-4 position-relative border-start border-secondary ps-3 ms-1 py-1">
                                <div class="position-absolute bg-primary rounded-circle"
                                    style="width: 8px; height: 8px; left: -5px; top: 10px;"></div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="extra-small fw-bold text-white-50">
                                        <?= date('d M, H:i', strtotime($l['created_at'])) ?>
                                    </div>
                                    <span class="badge bg-secondary bg-opacity-20 text-muted extra-small" style="font-size: 0.5rem;">
                                        <?= htmlspecialchars($l['username']) ?>
                                    </span>
                                </div>
                                <div class="text-info small fw-bold mt-1">
                                    <?= htmlspecialchars($l['action']) ?>
                                </div>
                                <div class="extra-small text-muted-2 mt-1">
                                    <?= nl2br(htmlspecialchars($l['details'])) ?>
                                </div>
                                <?php if ($l['tech_name']): ?>
                                    <div class="extra-small text-primary mt-1 italic"><i class="fa-solid fa-screwdriver-wrench me-1"></i>
                                        <?= htmlspecialchars($l['tech_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    } catch (Exception $e) {
        echo "Erreur lors de la récupération : " . $e->getMessage();
    }
    exit();
}
// 5. GET DEVICE HISTORY
if ($action == 'get_device_history') {
    $id_device = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT sr.*, u.username as creator_name, t.fullname as tech_name 
                             FROM service_requests sr 
                             JOIN users u ON sr.id_user_creator = u.id_user 
                             LEFT JOIN technicians t ON sr.id_technician = t.id_technician 
                             WHERE sr.id_device = ? 
                             ORDER BY sr.created_at DESC");
        $stmt->execute([$id_device]);
        $history = $stmt->fetchAll();

        if (empty($history)) {
            echo "<div class='text-center py-4 text-muted'>Aucun historique de réparation pour cet appareil.</div>";
        } else {
            foreach ($history as $req) {
                $status_class = match ($req['status']) {
                    'livré' => 'bg-success',
                    'terminé' => 'bg-info',
                    'réparation_en_cours' => 'bg-primary',
                    'en_attente' => 'bg-warning',
                    default => 'bg-secondary'
                };
                ?>
                <div class="mb-4 p-3 bg-white bg-opacity-5 rounded-3 border border-white border-opacity-10">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span
                                class="badge <?= $status_class ?> extra-small me-2"><?= strtoupper(str_replace('_', ' ', $req['status'])) ?></span>
                            <span class="text-white-50 small">#<?= $req['id_request'] ?></span>
                        </div>
                        <div class="extra-small text-muted"><?= date('d/m/Y', strtotime($req['created_at'])) ?></div>
                    </div>
                    <div class="text-white mb-2"><?= nl2br(htmlspecialchars($req['description'])) ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-white border-opacity-5">
                        <div class="extra-small text-muted">Technicien: <span
                                class="text-info"><?= $req['tech_name'] ?: 'Non assigné' ?></span></div>
                        <div class="extra-small text-success fw-bold"><?= number_format($req['estimated_cost'], 0, ',', ' ') ?> FCFA
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    } catch (Exception $e) {
        echo "Erreur : " . $e->getMessage();
    }
    exit();
}

// 5. GET DEVICE HISTORY
if ($action == 'get_device_history') {
    $id_device = $_GET['id'] ?? 0;

    try {
        $stmt = $pdo->prepare("SELECT sr.*, t.fullname as tech_name 
                               FROM service_requests sr 
                               LEFT JOIN technicians t ON sr.id_technician = t.id_technician 
                               WHERE sr.id_device = ? 
                               ORDER BY sr.created_at DESC");
        $stmt->execute([$id_device]);
        $history = $stmt->fetchAll();

        if (empty($history)) {
            echo "<p class='text-muted text-center py-4'>Aucun historique de réparation pour cet appareil.</p>";
        } else {
            echo "<div class='table-responsive'>";
            echo "<table class='table table-dark table-sm mb-0'>";
            echo "<thead><tr class='text-muted extra-small'>";
            echo "<th>DATE</th><th>STATUT</th><th>TECHNICIEN</th><th>COÛT</th>";
            echo "</tr></thead><tbody>";

            foreach ($history as $h) {
                $status_class = match ($h['status']) {
                    'terminé', 'livré' => 'success',
                    'réparation_en_cours' => 'primary',
                    'diagnostic_effectué' => 'info',
                    default => 'secondary'
                };

                echo "<tr>";
                echo "<td class='small'>" . date('d/m/Y', strtotime($h['created_at'])) . "</td>";
                echo "<td><span class='badge bg-{$status_class} bg-opacity-20 text-{$status_class}'>" . htmlspecialchars($h['status']) . "</span></td>";
                echo "<td class='small'>" . htmlspecialchars($h['tech_name'] ?: 'Non assigné') . "</td>";
                echo "<td class='small text-end'>" . number_format($h['estimated_cost'], 0, ',', ' ') . " FCFA</td>";
                echo "</tr>";
            }

            echo "</tbody></table></div>";
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    exit();
}
