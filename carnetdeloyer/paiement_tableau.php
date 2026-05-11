<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$houseId = (int) ($_GET['house_id'] ?? $_POST['house_id'] ?? 0);
$payments = cl_fetch_payment_rows($pdo, (int) $user['id'], $houseId);

if (cl_is_htmx_request()) {
    cl_render_payment_table_component($payments, $houseId);
    exit;
}

cl_render_shell_start('Tableau des paiements', $user);
?>
<div id="payments-dynamic-zone">
    <?php cl_render_payment_table_component($payments, $houseId); ?>
</div>
<?php cl_render_shell_end();
