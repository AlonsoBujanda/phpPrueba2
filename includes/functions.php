<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function getDateRange($range = 'today') {
    switch ($range) {
        case 'today':
            return [
                'start' => date('Y-m-d') . ' 00:00:00',
                'end' => date('Y-m-d') . ' 23:59:59'
            ];
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('monday this week')) . ' 00:00:00',
                'end' => date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59'
            ];
        case 'month':
            return [
                'start' => date('Y-m-01') . ' 00:00:00',
                'end' => date('Y-m-t') . ' 23:59:59'
            ];
        default:
            return [
                'start' => date('Y-m-d') . ' 00:00:00',
                'end' => date('Y-m-d') . ' 23:59:59'
            ];
    }
}

function redirect($url, $delay = 0) {
    if ($delay > 0) {
        echo "<meta http-equiv='refresh' content='$delay;url=$url'>";
    } else {
        header("Location: $url");
    }
    exit;
}

function showAlert($type, $message) {
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

function getStatusBadge($status) {
    $badges = [
        'Pendiente' => 'warning',
        'Preparando' => 'info',
        'Listo' => 'primary',
        'Entregado' => 'success',
        'Cancelado' => 'danger',
        'Libre' => 'success',
        'Ocupada' => 'warning',
        'Reservada' => 'info',
        'Activo' => 'success',
        'Inactivo' => 'danger',
        'Disponible' => 'success',
        'No Disponible' => 'danger'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    return "<span class='badge bg-$color'>$status</span>";
}
?>