# Sistema de Código de Verificación por Correo - Instalación

## 📋 Resumen

Se ha implementado un nuevo sistema para reasignar tickets usando códigos de verificación enviados por correo electrónico en lugar de contraseñas de Microsoft.

## ✅ Archivos Creados/Modificados

### Archivos Backend Creados:
1. `database/setup_codigos_verificacion.php` - Script de instalación de BD
2. `conexionBD/email_helper.php` - Helper para enviar correos con Microsoft Graph
3. `Logica/solicitar_codigo_verificacion.php` - Endpoint para solicitar código
4. `INSTRUCCIONES_CODIGO_VERIFICACION.md` - Este archivo

### Archivos Backend Modificados:
1. `Logica/asignar_ticket.php` - Ahora valida código en lugar de contraseña

### Archivos Frontend a Modificar:
1. `View/modulos/Despacho_factura.php` - Necesita actualización del HTML y JavaScript

## 🚀 Pasos de Instalación

### Paso 1: Ejecutar Script de Base de Datos

**Importante:** Este paso DEBE ejecutarse primero.

Abre tu navegador y ve a:
```
http://localhost/MACO.AppLogistica.Web-1/database/setup_codigos_verificacion.php
```

Este script creará:
- ✅ Tabla `codigos_verificacion`
- ✅ Procedimiento `LimpiarCodigosExpirados`

### Paso 2: Verificar Configuración de Azure

El sistema de correos usa Microsoft Graph API.  Asegúrate de que tu archivo `.env` tiene:
```env
AZURE_CLIENT_ID=tu_client_id
AZURE_CLIENT_SECRET=tu_client_secret
AZURE_TENANT_ID=tu_tenant_id
```

###Paso 3: Configurar Permisos en Azure Portal

Para enviar correos, la aplicación necesita permisos:

1. Ve a **Azure Portal** → **Azure Active Directory**
2. **App registrations** → Selecciona tu aplicación
3. **API permissions** → **Add a permission**
4. **Microsoft Graph** → **Application permissions**
5. Agrega: **Mail.Send**
6. **Grant admin consent** para el tenant

### Paso 4: Configurar Email del Remitente

Edita el archivo `conexionBD/email_helper.php` línea 58:

```php
if (empty($fromEmail)) {
    $fromEmail = 'noreply@corripio.com.do'; // ← Cambiar según tu organización
}
```

Asegúrate de que este email existe en tu organización y tiene permisos de envío.

## 🔄 Cómo Funciona el Nuevo Sistema

### Flujo del Usuario:

1. **Usuario intenta reasignar un ticket**
   - El sistema detecta que el ticket ya tiene dueño
   - Muestra el modal de reasignación

2. **Solicitar código**
   - El usuario hace clic en "Solicitar código por correo"
   - El sistema genera un código de 6 dígitos
   - Se envía por correo al usuario actual del ticket
   - **Válido por 5 minutos**

3. **Ingresar código**
   - El usuario actual revisa su correo
   - Ingresa el código de 6 dígitos
   - Hace clic en "Confirmar reasignación"

4. **Validación**
   - El sistema verifica:
     - ✅ Código correcto
     - ✅ No expirado (5 minutos)
     - ✅ No usado previamente
   - Si todo es válido → Reasigna el ticket

### Ventajas sobre el sistema anterior:

- ✅ **Funciona con MFA** (autenticación multifactor)
- ✅ **Más seguro** - No se transmiten contraseñas
- ✅ **Auditado** - Todos los intentos se registran en logs
- ✅ **Rate limiting** - Máximo 3 códigos por minuto
- ✅ **Expira automáticamente** - Códigos válidos solo 5 minutos

## 📝 Actualizar el Frontend

**PENDIENTE:** Necesitas actualizar `Despacho_factura.php`

Reemplaza el HTML del modal (líneas 179-183):

```html
<!-- Campo de código de verificación (solo visible en reasignación) -->
<div class="mb-3" id="codigoContainer" style="display: none;">
    <div class="alert alert-info" id="codigoInfo" style="display: none;">
        <i class="fa-solid fa-envelope me-2"></i>
        <small>Se enviará un código de verificación al correo de <strong id="usuarioActualNombre"></strong></small>
    </div>

    <div class="input-group">
        <input type="text" id="codigoVerificacion" class="form-control" placeholder="Código de 6 dígitos"
               maxlength="6" pattern="[0-9]{6}" autocomplete="off">
        <button type="button" class="btn btn-outline-primary" id="btnSolicitarCodigo">
            <i class="fa-solid fa-paper-plane me-1"></i>
            Solicitar código
        </button>
    </div>
    <small class="text-muted">El código expira en 5 minutos</small>
</div>
```

Y actualizar el JavaScript (líneas 358-419):

```javascript
// Al abrir el modal de asignación
$(document).on('click', '.btn-asignar', function() {
    const tiket = $(this).data('tiket');
    const asignadoA = $(this).data('asignado-a');

    $('#asignarTiket').text(tiket);
    $('#asignarTiketInput').val(tiket);
    $('#currentAssigneeInput').val(asignadoA || '');
    $('#codigoVerificacion').val('');

    if (asignadoA) {
        // Reasignación - mostrar campo código
        $('#isReassignment').val('true');
        $('#usuarioActualNombre').text(asignadoA);
        $('#modalAsignarTexto').html(`Para re-asignar el ticket de <strong>${asignadoA}</strong>, necesitas un código de verificación.`);
        $('#codigoInfo').show();
        $('#codigoContainer').show();
        $('#asignarModal').off('shown.bs.modal').on('shown.bs.modal', () => $('#codigoVerificacion').focus());
    } else {
        // Asignación nueva
        $('#isReassignment').val('false');
        $('#modalAsignarTexto').text('Confirma que deseas asignar este ticket a tu usuario.');
        $('#codigoContainer').hide();
    }

    $('#asignarModal').modal('show');
});

// Botón solicitar código
$('#btnSolicitarCodigo').on('click', function() {
    const currentAssignee = $('#currentAssigneeInput').val();
    const ticket = $('#asignarTiketInput').val();
    const btn = $(this);

    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>Enviando...');

    $.ajax({
        url: '../../Logica/solicitar_codigo_verificacion.php',
        method: 'POST',
        data: {
            usuario: currentAssignee,
            ticket: ticket,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(`✅ Código enviado a ${response.email}`);
                $('#codigoVerificacion').focus();
            } else {
                alert('❌ ' + response.message);
            }
        },
        error: function() {
            alert('Error al solicitar el código. Intenta de nuevo.');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i>Solicitar código');
        }
    });
});

// Submit del formulario de asignación
$('#formAsignar').on('submit', function(e) {
    e.preventDefault();
    const tiket = $('#asignarTiketInput').val();
    const codigoVerificacion = $('#codigoVerificacion').val();
    const currentAssignee = $('#currentAssigneeInput').val();
    const isReassignment = $('#isReassignment').val() === 'true';

    // Solo validar código si es reasignación
    if (isReassignment && !codigoVerificacion) {
        alert('Por favor, ingresa el código de verificación.');
        $('#codigoVerificacion').focus();
        return;
    }

    // Validar formato del código (6 dígitos)
    if (isReassignment && !/^\d{6}$/.test(codigoVerificacion)) {
        alert('El código debe tener 6 dígitos.');
        $('#codigoVerificacion').focus();
        return;
    }

    $.ajax({
        url: '../../Logica/asignar_ticket.php',
        method: 'POST',
        data: {
            tiket: tiket,
            codigo_verificacion: codigoVerificacion,
            current_assignee: currentAssignee,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('asignarModal')).hide();
                actualizarTablaInteligentemente();
            } else {
                alert('❌ ' + response.message);
                if (isReassignment) {
                    $('#codigoVerificacion').val('').focus();
                }
            }
        },
        error: () => alert('Ocurrió un error de comunicación. Inténtalo de nuevo.')
    });
});
```

## 🧪 Pruebas

### 1. Probar envío de correos (opcional):
```
http://localhost/MACO.AppLogistica.Web-1/Logica/test_microsoft_auth.php
```

### 2. Probar reasignación:
1. Asigna un ticket a un usuario
2. Con otro usuario, intenta reasignarlo
3. Haz clic en "Solicitar código"
4. Revisa el correo del usuario original
5. Ingresa el código de 6 dígitos
6. Confirma la reasignación

## 📊 Tabla de Códigos de Verificación

La tabla `codigos_verificacion` almacena:

| Campo | Descripción |
|-------|-------------|
| codigo | Código de 6 dígitos |
| usuario | Usuario para quien es el código |
| ticket | Ticket que se quiere reasignar |
| creado | Fecha de creación |
| expira | Fecha de expiración (creado + 5 min) |
| usado | Flag si ya fue usado |
| ip_solicitud | IP desde donde se solicitó |

## 🔒 Seguridad

- ✅ Rate limiting: 3 solicitudes/minuto
- ✅ Códigos de un solo uso
- ✅ Expiración automática (5 minutos)
- ✅ CSRF protection
- ✅ Logs de auditoría
- ✅ Validación de sesión

## 🐛 Solución de Problemas

### El correo no llega:

1. Verifica que el email del remitente existe
2. Revisa que los permisos `Mail.Send` estén otorgados en Azure
3. Revisa los logs en PHP error log
4. Verifica la configuración de .env

### Error "Error al enviar el correo":

- Verifica permisos de Mail.Send en Azure Portal
- Asegúrate de haber dado "Admin consent"
- Revisa que el email del remitente sea válido

### Error "Código inválido o ya usado":

- El código solo puede usarse una vez
- Verifica que no hayan pasado más de 5 minutos
- Solicita un nuevo código

---

**Creado:** 2026-01-13
**Versión:** 1.0.0
**Autor:** Claude Code
