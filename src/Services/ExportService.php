<?php
/**
 * ExportService - Servicio de Exportación
 * 
 * Proporciona exportación de datos a Excel y PDF.
 * 
 * @package    MACO\Services
 * @author     MACO Team
 * @version    1.0.0
 */

namespace MACO\Services;

class ExportService
{
    /** @var array Configuración de exportación */
    private $config = [
        'max_rows' => 10000,
        'chunk_size' => 1000,
    ];

    /**
     * Constructor.
     * 
     * @param array $config Configuración opcional
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Exporta datos a formato CSV (compatible con Excel).
     * 
     * @param array $data Datos a exportar
     * @param array $columns Definición de columnas ['key' => 'Header']
     * @param string $filename Nombre del archivo
     * @return void
     */
    public function toExcel(array $data, array $columns, string $filename = 'export'): void
    {
        if (count($data) > $this->config['max_rows']) {
            $data = array_slice($data, 0, $this->config['max_rows']);
        }

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filename .= '_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // BOM para UTF-8 en Excel
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, array_values($columns), ';');

        // Data
        foreach ($data as $row) {
            $exportRow = [];
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                
                // Formatear fechas
                if ($value instanceof \DateTime) {
                    $value = $value->format('d/m/Y H:i:s');
                }
                
                // Sanitizar valores
                if (is_string($value)) {
                    $value = trim($value);
                }
                
                $exportRow[] = $value;
            }
            fputcsv($output, $exportRow, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Exporta datos a formato HTML (para imprimir como PDF).
     * 
     * @param array $data Datos a exportar
     * @param array $columns Definición de columnas
     * @param string $title Título del reporte
     * @param array $options Opciones adicionales
     * @return string HTML del reporte
     */
    public function toHtmlReport(array $data, array $columns, string $title = 'Reporte', array $options = []): string
    {
        $subtitle = $options['subtitle'] ?? '';
        $logo = $options['logo'] ?? '';
        $orientation = $options['orientation'] ?? 'landscape';
        $showTotals = $options['showTotals'] ?? false;
        $dateRange = $options['dateRange'] ?? '';

        $html = $this->getReportStyles($orientation);
        
        $html .= '<div class="report-container">';
        
        // Header
        $html .= '<div class="report-header">';
        if ($logo) {
            $html .= '<img src="' . htmlspecialchars($logo) . '" alt="Logo" class="report-logo">';
        }
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        if ($subtitle) {
            $html .= '<p class="subtitle">' . htmlspecialchars($subtitle) . '</p>';
        }
        if ($dateRange) {
            $html .= '<p class="date-range">' . htmlspecialchars($dateRange) . '</p>';
        }
        $html .= '<p class="generated-at">Generado: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // Table
        $html .= '<table class="report-table">';
        $html .= '<thead><tr>';
        foreach ($columns as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                
                if ($value instanceof \DateTime) {
                    $value = $value->format('d/m/Y H:i');
                }
                
                // Detectar si es numérico para alinear a la derecha
                $class = is_numeric($value) ? 'text-right' : '';
                $html .= '<td class="' . $class . '">' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        if ($showTotals) {
            $html .= '<tfoot><tr>';
            $html .= '<td colspan="' . (count($columns) - 1) . '"><strong>Total registros:</strong></td>';
            $html .= '<td class="text-right"><strong>' . count($data) . '</strong></td>';
            $html .= '</tr></tfoot>';
        }

        $html .= '</table>';

        // Footer
        $html .= '<div class="report-footer">';
        $html .= '<p>MACO Logística - Sistema de Gestión</p>';
        $html .= '</div>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Descarga reporte como PDF (usando impresión del navegador).
     * 
     * @param array $data Datos
     * @param array $columns Columnas
     * @param string $title Título
     * @param array $options Opciones
     * @return void
     */
    public function toPdf(array $data, array $columns, string $title = 'Reporte', array $options = []): void
    {
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '</head><body>';
        $html .= $this->toHtmlReport($data, $columns, $title, $options);
        $html .= '<script>window.onload = function() { window.print(); }</script>';
        $html .= '</body></html>';

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    /**
     * Obtiene los estilos CSS para reportes.
     */
    private function getReportStyles(string $orientation = 'landscape'): string
    {
        return '
        <style>
            @page {
                size: letter ' . $orientation . ';
                margin: 1cm;
            }
            
            * {
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                color: #333;
                margin: 0;
                padding: 20px;
            }
            
            .report-container {
                max-width: 100%;
            }
            
            .report-header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #E63946;
            }
            
            .report-logo {
                max-height: 60px;
                margin-bottom: 10px;
            }
            
            .report-header h1 {
                margin: 0 0 5px 0;
                font-size: 20px;
                color: #1D3557;
            }
            
            .report-header .subtitle {
                color: #666;
                margin: 5px 0;
            }
            
            .report-header .date-range {
                font-weight: bold;
                color: #E63946;
                margin: 5px 0;
            }
            
            .report-header .generated-at {
                font-size: 9px;
                color: #999;
                margin: 10px 0 0 0;
            }
            
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .report-table th {
                background: #1D3557;
                color: white;
                padding: 8px 6px;
                text-align: left;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border: 1px solid #1D3557;
            }
            
            .report-table td {
                padding: 6px;
                border: 1px solid #ddd;
                font-size: 10px;
            }
            
            .report-table tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .report-table tbody tr:hover {
                background-color: #f0f0f0;
            }
            
            .report-table tfoot td {
                background: #f0f0f0;
                font-weight: bold;
                border-top: 2px solid #1D3557;
            }
            
            .text-right {
                text-align: right;
            }
            
            .report-footer {
                text-align: center;
                font-size: 9px;
                color: #999;
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }
            
            @media print {
                body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                .report-table th { background: #1D3557 !important; color: white !important; }
            }
        </style>';
    }

    /**
     * Formatea un valor para exportación.
     * 
     * @param mixed $value Valor a formatear
     * @param string $type Tipo (date, datetime, money, number)
     * @return string Valor formateado
     */
    public static function format($value, string $type = 'text'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case 'date':
                if ($value instanceof \DateTime) {
                    return $value->format('d/m/Y');
                }
                $ts = strtotime($value);
                return $ts ? date('d/m/Y', $ts) : '';

            case 'datetime':
                if ($value instanceof \DateTime) {
                    return $value->format('d/m/Y H:i:s');
                }
                $ts = strtotime($value);
                return $ts ? date('d/m/Y H:i:s', $ts) : '';

            case 'money':
                return 'RD$ ' . number_format((float)$value, 2, '.', ',');

            case 'number':
                return number_format((float)$value, 0, '.', ',');

            default:
                return (string)$value;
        }
    }
}
