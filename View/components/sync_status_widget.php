<?php
/**
 * Widget de Estado de Sincronización
 * Muestra el estado de la última sincronización de facturas
 * MACO AppLogística
 */

date_default_timezone_set('America/Santo_Domingo');
require_once __DIR__ . '/../../conexionBD/conexion.php';

// Función para obtener el último estado de sincronización
function getLastSyncStatus($conn) {
    $sql = "SELECT TOP 1
                execution_time,
                status,
                duration_ms,
                error_message
            FROM sync_execution_log
            ORDER BY execution_time DESC";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        return null;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// Función para obtener estadísticas del día
function getTodayStats($conn) {
    $sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'ERROR' THEN 1 ELSE 0 END) as errors,
                AVG(duration_ms) as avg_duration
            FROM sync_execution_log
            WHERE CAST(execution_time AS DATE) = CAST(GETDATE() AS DATE)";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        return null;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

$lastSync = getLastSyncStatus($conn);
$todayStats = getTodayStats($conn);

// Calcular tiempo desde última sincronización
$timeSinceSync = '';
$statusClass = 'info';
$statusIcon = 'fa-sync';

if ($lastSync) {
    $now = new DateTime();
    $lastTime = $lastSync['execution_time'];

    if (is_object($lastTime)) {
        $diff = $now->diff($lastTime);

        if ($diff->h > 0) {
            $timeSinceSync = $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            $timeSinceSync = $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');
        } else {
            $timeSinceSync = 'Hace menos de 1 minuto';
        }

        // Determinar estado
        if ($lastSync['status'] === 'SUCCESS') {
            if ($diff->i < 25) {
                $statusClass = 'success';
                $statusIcon = 'fa-check-circle';
            } else {
                $statusClass = 'warning';
                $statusIcon = 'fa-exclamation-triangle';
            }
        } else {
            $statusClass = 'danger';
            $statusIcon = 'fa-times-circle';
        }
    }
}
?>

<style>
.sync-widget {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.sync-widget-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-100);
}

.sync-widget-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.sync-widget-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.sync-widget-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.sync-widget-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
}

.sync-widget-icon.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
}

.sync-widget-title {
    flex: 1;
}

.sync-widget-title h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
}

.sync-widget-title p {
    margin: 0.25rem 0 0 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.sync-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.sync-stat {
    text-align: center;
    padding: 0.75rem;
    background: var(--gray-50);
    border-radius: var(--radius-md);
}

.sync-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.sync-stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

.sync-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-full);
    font-size: 0.875rem;
    font-weight: 600;
}

.sync-status-badge.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.sync-status-badge.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.sync-status-badge.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
}

.sync-error-message {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(239, 68, 68, 0.05);
    border-left: 4px solid var(--danger);
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    color: var(--text-secondary);
}
</style>

<div class="sync-widget">
    <div class="sync-widget-header">
        <div class="sync-widget-icon <?= $statusClass ?>">
            <i class="fas <?= $statusIcon ?>"></i>
        </div>
        <div class="sync-widget-title">
            <h4>Sincronización de Facturas</h4>
            <p>
                <?php if ($lastSync): ?>
                    <?= $timeSinceSync ?>
                    <?php if ($lastSync['duration_ms']): ?>
                        • <?= round($lastSync['duration_ms']) ?> ms
                    <?php endif; ?>
                <?php else: ?>
                    Sin datos de sincronización
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($lastSync): ?>
        <div style="margin-bottom: 1rem;">
            <span class="sync-status-badge <?= $statusClass ?>">
                <i class="fas <?= $statusIcon ?>"></i>
                <?= $lastSync['status'] === 'SUCCESS' ? 'Sincronización Exitosa' : 'Error en Sincronización' ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($lastSync && $lastSync['status'] === 'ERROR' && $lastSync['error_message']): ?>
        <div class="sync-error-message">
            <strong>Error:</strong> <?= htmlspecialchars($lastSync['error_message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($todayStats && $todayStats['total'] > 0): ?>
        <div class="sync-stats">
            <div class="sync-stat">
                <div class="sync-stat-value"><?= $todayStats['total'] ?></div>
                <div class="sync-stat-label">Total Hoy</div>
            </div>
            <div class="sync-stat">
                <div class="sync-stat-value" style="color: var(--success);">
                    <?= $todayStats['success'] ?>
                </div>
                <div class="sync-stat-label">Exitosas</div>
            </div>
            <div class="sync-stat">
                <div class="sync-stat-value" style="color: var(--danger);">
                    <?= $todayStats['errors'] ?>
                </div>
                <div class="sync-stat-label">Errores</div>
            </div>
            <div class="sync-stat">
                <div class="sync-stat-value">
                    <?= $todayStats['avg_duration'] ? round($todayStats['avg_duration']) : 0 ?> ms
                </div>
                <div class="sync-stat-label">Promedio</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh cada 60 segundos
setTimeout(function() {
    location.reload();
}, 60000);
</script>
