<?php
/**
 * ResponseService - Servicio de Respuestas API
 * 
 * Proporciona respuestas estandarizadas para todas las APIs.
 * 
 * @package    MACO\Services
 * @author     MACO Team
 * @version    1.0.0
 */

namespace MACO\Services;

class ResponseService
{
    /** @var int Código HTTP por defecto */
    private static $defaultCode = 200;

    /**
     * Respuesta exitosa.
     * 
     * @param mixed $data Datos a retornar
     * @param string $message Mensaje opcional
     * @param int $code Código HTTP
     * @return void
     */
    public static function success($data = null, string $message = 'OK', int $code = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Respuesta de error.
     * 
     * @param string $message Mensaje de error
     * @param int $code Código HTTP
     * @param array $errors Errores adicionales
     * @return void
     */
    public static function error(string $message, int $code = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'error' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $code);
    }

    /**
     * Error de validación.
     * 
     * @param array $errors Errores de validación
     * @param string $message Mensaje
     * @return void
     */
    public static function validationError(array $errors, string $message = 'Error de validación'): void
    {
        self::error($message, 422, $errors);
    }

    /**
     * Error no autorizado.
     * 
     * @param string $message Mensaje
     * @return void
     */
    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 401);
    }

    /**
     * Error prohibido.
     * 
     * @param string $message Mensaje
     * @return void
     */
    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 403);
    }

    /**
     * Error no encontrado.
     * 
     * @param string $message Mensaje
     * @return void
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    /**
     * Error interno del servidor.
     * 
     * @param string $message Mensaje
     * @param \Exception|null $e Excepción opcional para logging
     * @return void
     */
    public static function serverError(string $message = 'Error interno del servidor', ?\Exception $e = null): void
    {
        if ($e) {
            error_log("Server Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        self::error($message, 500);
    }

    /**
     * Respuesta paginada.
     * 
     * @param array $data Datos de la página
     * @param int $currentPage Página actual
     * @param int $totalItems Total de items
     * @param int $perPage Items por página
     * @return void
     */
    public static function paginated(array $data, int $currentPage, int $totalItems, int $perPage): void
    {
        $totalPages = ceil($totalItems / $perPage);
        
        self::json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'currentPage' => $currentPage,
                'perPage' => $perPage,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasMore' => $currentPage < $totalPages
            ]
        ]);
    }

    /**
     * Enviar respuesta JSON.
     * 
     * @param array $data Datos a enviar
     * @param int $code Código HTTP
     * @return void
     */
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Enviar archivo para descarga.
     * 
     * @param string $content Contenido del archivo
     * @param string $filename Nombre del archivo
     * @param string $mimeType Tipo MIME
     * @return void
     */
    public static function download(string $content, string $filename, string $mimeType = 'application/octet-stream'): void
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $content;
        exit;
    }

    /**
     * Redirección.
     * 
     * @param string $url URL de destino
     * @param bool $permanent Redirección permanente (301)
     * @return void
     */
    public static function redirect(string $url, bool $permanent = false): void
    {
        http_response_code($permanent ? 301 : 302);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Respuesta vacía (No Content).
     * 
     * @return void
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
