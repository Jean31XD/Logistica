# 🔄 Sincronización Automática de Facturas
## MACO AppLogística - SyncCustinvoicejour cada 20 minutos

---

## 📋 Opciones Disponibles

Hay **3 opciones** para ejecutar automáticamente `SyncCustinvoicejour` cada 20 minutos:

1. **SQL Server Agent Job** (Recomendado para SQL Server On-Premise)
2. **Windows Task Scheduler + PHP** (Recomendado para XAMPP/desarrollo)
3. **Azure Automation** (Recomendado para Azure SQL Database)

---

## ⚙️ OPCIÓN 1: SQL Server Agent Job

### Para: SQL Server On-Premise o Azure SQL Managed Instance

### Instalación:

```sql
-- Ejecutar el script SQL
:r C:\xampp\htdocs\MACO.AppLogistica.Web-1\database\04_sync_scheduler.sql
```

O copiar y pegar el contenido completo en SQL Server Management Studio.

### Verificación:

```sql
-- Ver estado del job
SELECT
    name AS Job_Name,
    enabled AS Activo,
    date_created AS Fecha_Creacion,
    date_modified AS Ultima_Modificacion
FROM msdb.dbo.sysjobs
WHERE name = 'MACO - Sync Facturas (20 min)';

-- Ver historial de ejecuciones
SELECT TOP 20
    CONVERT(VARCHAR(10), CAST(CAST(h.run_date AS VARCHAR(8)) AS DATE), 120) AS Fecha,
    STUFF(STUFF(RIGHT('000000' + CAST(h.run_time AS VARCHAR(6)), 6), 5, 0, ':'), 3, 0, ':') AS Hora,
    CASE h.run_status
        WHEN 0 THEN '❌ Error'
        WHEN 1 THEN '✅ Éxito'
        WHEN 2 THEN '🔄 Reintento'
        WHEN 3 THEN '⏹️ Cancelado'
        WHEN 4 THEN '⏳ En progreso'
    END AS Estado,
    h.run_duration AS Duracion_Seg,
    h.message AS Mensaje
FROM msdb.dbo.sysjobhistory h
INNER JOIN msdb.dbo.sysjobs j ON h.job_id = j.job_id
WHERE j.name = 'MACO - Sync Facturas (20 min)'
ORDER BY h.run_date DESC, h.run_time DESC;
```

### Comandos útiles:

```sql
-- Ejecutar manualmente
EXEC sp_SyncFacturas @ModoDebug = 1;

-- Deshabilitar job
EXEC msdb.dbo.sp_update_job
    @job_name = 'MACO - Sync Facturas (20 min)',
    @enabled = 0;

-- Habilitar job
EXEC msdb.dbo.sp_update_job
    @job_name = 'MACO - Sync Facturas (20 min)',
    @enabled = 1;

-- Ejecutar job inmediatamente (para probar)
EXEC msdb.dbo.sp_start_job @job_name = 'MACO - Sync Facturas (20 min)';

-- Eliminar job
EXEC msdb.dbo.sp_delete_job
    @job_name = 'MACO - Sync Facturas (20 min)';
```

---

## ⚙️ OPCIÓN 2: Windows Task Scheduler + PHP

### Para: Entornos XAMPP, desarrollo local, o cuando no tienes SQL Server Agent

### Paso 1: Probar el script manualmente

```batch
# Abrir CMD como Administrador
cd C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools
php sync_facturas_cron.php
```

Deberías ver:
```
[2025-12-10 14:30:00] [INFO] ========================================
[2025-12-10 14:30:00] [INFO] Iniciando sincronización de facturas
[2025-12-10 14:30:00] [INFO] Conexión a BD establecida
[2025-12-10 14:30:01] [SUCCESS] ✓ SyncCustinvoicejour ejecutado exitosamente
[2025-12-10 14:30:01] [INFO] Duración: 1234.56 ms
[2025-12-10 14:30:01] [INFO] ========================================
```

### Paso 2: Configurar Task Scheduler

#### Método A: Usar el script BAT (Recomendado)

1. **Abrir Programador de Tareas**
   - Win + R → `taskschd.msc` → Enter

2. **Crear Tarea Básica**
   - Clic derecho en "Biblioteca del Programador de tareas"
   - "Crear tarea..." (NO "Crear tarea básica")

3. **Pestaña General**
   - Nombre: `MACO - Sync Facturas (20 min)`
   - Descripción: `Sincroniza facturas ejecutando SyncCustinvoicejour cada 20 minutos`
   - ☑ Ejecutar tanto si el usuario inició sesión como si no
   - ☑ Ejecutar con los privilegios más altos
   - Configurar para: Windows 10

4. **Pestaña Desencadenadores**
   - Clic en "Nuevo..."
   - Iniciar la tarea: **Según una programación**
   - Configuración:
     - ☑ Diariamente
     - Repetir cada: `1` días
     - Hora de inicio: `00:00:00` (medianoche)
   - **Configuración avanzada:**
     - ☑ Repetir la tarea cada: `20 minutos`
     - Durante: `1 día`
     - ☑ Habilitada
   - Clic en "Aceptar"

5. **Pestaña Acciones**
   - Clic en "Nuevo..."
   - Acción: **Iniciar un programa**
   - Programa o script:
     ```
     C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\sync_facturas.bat
     ```
   - Iniciar en (opcional):
     ```
     C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools
     ```
   - Clic en "Aceptar"

6. **Pestaña Condiciones**
   - ☐ Iniciar la tarea solo si el equipo está conectado a la corriente alterna
   - ☑ Activar si el equipo está en modo de espera

7. **Pestaña Configuración**
   - ☑ Permitir que la tarea se ejecute a petición
   - ☑ Ejecutar la tarea lo antes posible después de perder una ejecución programada
   - Si la tarea en ejecución no finaliza cuando se solicita: **No realizar ninguna acción**
   - Si la tarea ya se está ejecutando: **No iniciar una nueva instancia**

8. **Guardar**
   - Clic en "Aceptar"
   - Ingresa contraseña de Windows si se solicita

#### Método B: Importar XML (Más rápido)

Crear archivo `sync_task.xml`:

```xml
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Date>2025-12-10T00:00:00</Date>
    <Author>MACO</Author>
    <Description>Sincroniza facturas ejecutando SyncCustinvoicejour cada 20 minutos</Description>
  </RegistrationInfo>
  <Triggers>
    <CalendarTrigger>
      <Repetition>
        <Interval>PT20M</Interval>
        <Duration>P1D</Duration>
        <StopAtDurationEnd>false</StopAtDurationEnd>
      </Repetition>
      <StartBoundary>2025-12-10T00:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay>
        <DaysInterval>1</DaysInterval>
      </ScheduleByDay>
    </CalendarTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <LogonType>Password</LogonType>
      <RunLevel>HighestAvailable</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <AllowHardTerminate>true</AllowHardTerminate>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <IdleSettings>
      <StopOnIdleEnd>false</StopOnIdleEnd>
      <RestartOnIdle>false</RestartOnIdle>
    </IdleSettings>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <Hidden>false</Hidden>
    <RunOnlyIfIdle>false</RunOnlyIfIdle>
    <WakeToRun>false</WakeToRun>
    <ExecutionTimeLimit>PT5M</ExecutionTimeLimit>
    <Priority>7</Priority>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\sync_facturas.bat</Command>
      <WorkingDirectory>C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools</WorkingDirectory>
    </Exec>
  </Actions>
</Task>
```

Luego importar:
```batch
schtasks /Create /XML "sync_task.xml" /TN "MACO - Sync Facturas (20 min)"
```

### Paso 3: Probar la tarea

```batch
# Ejecutar tarea manualmente
schtasks /Run /TN "MACO - Sync Facturas (20 min)"

# Ver estado
schtasks /Query /TN "MACO - Sync Facturas (20 min)" /FO LIST /V
```

### Paso 4: Verificar logs

Los logs se guardan en:
```
C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\logs\sync_YYYY-MM-DD.log
```

Ver último log:
```batch
type C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\logs\sync_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%.log
```

### Comandos útiles de Task Scheduler:

```batch
# Ver todas las tareas programadas
schtasks /Query

# Ver estado de la tarea específica
schtasks /Query /TN "MACO - Sync Facturas (20 min)" /FO LIST /V

# Deshabilitar tarea
schtasks /Change /TN "MACO - Sync Facturas (20 min)" /DISABLE

# Habilitar tarea
schtasks /Change /TN "MACO - Sync Facturas (20 min)" /ENABLE

# Eliminar tarea
schtasks /Delete /TN "MACO - Sync Facturas (20 min)" /F

# Ver historial de ejecuciones (desde Event Viewer)
eventvwr.msc
# → Registros de Windows → Microsoft → Windows → TaskScheduler → Operational
```

---

## ⚙️ OPCIÓN 3: Azure Automation (Azure SQL Database)

### Para: Azure SQL Database (sin SQL Server Agent)

Azure SQL Database no tiene SQL Server Agent, por lo que necesitas usar **Azure Automation** o **Azure Logic Apps**.

### Opción 3A: Azure Automation

1. **Crear Automation Account**
   - Portal Azure → Automation Accounts → Create
   - Nombre: `maco-automation`
   - Región: Misma que tu SQL Database

2. **Crear Runbook**
   - Runbooks → Create a runbook
   - Nombre: `Sync-Facturas-MACO`
   - Tipo: PowerShell
   - Código:

```powershell
param()

$serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net"
$databaseName = "db-apptransportistas-maco"
$username = "ServiceAppTrans"
$password = "nZ(#n41LJm)iLmJP"

try {
    # Construir connection string
    $connectionString = "Server=tcp:$serverName,1433;Initial Catalog=$databaseName;Persist Security Info=False;User ID=$username;Password=$password;MultipleActiveResultSets=False;Encrypt=True;TrustServerCertificate=False;Connection Timeout=30;"

    # Crear conexión
    $connection = New-Object System.Data.SqlClient.SqlConnection
    $connection.ConnectionString = $connectionString
    $connection.Open()

    # Crear comando
    $command = $connection.CreateCommand()
    $command.CommandText = "EXEC SyncCustinvoicejour"
    $command.CommandTimeout = 300

    # Ejecutar
    $command.ExecuteNonQuery()

    Write-Output "✓ Sincronización completada exitosamente"

    # Cerrar conexión
    $connection.Close()

} catch {
    Write-Error "Error: $_"
    throw
}
```

3. **Programar Runbook**
   - Schedules → Add a schedule
   - Nombre: `Cada-20-Minutos`
   - Starts: Hoy
   - Recurrence: Recurring
   - Recur every: `20 Minutes`

### Opción 3B: Azure Logic Apps

1. **Crear Logic App**
   - Portal Azure → Logic Apps → Create
   - Nombre: `sync-facturas-maco`

2. **Diseñar Workflow**
   - Trigger: **Recurrence**
     - Interval: `20`
     - Frequency: `Minute`

   - Action: **SQL Server - Execute stored procedure**
     - Connection name: `maco-sql-connection`
     - Server: `sdb-apptransportistas-maco.privatelink.database.windows.net`
     - Database: `db-apptransportistas-maco`
     - Procedure: `SyncCustinvoicejour`

3. **Guardar y Activar**

---

## 📊 Monitoreo

### Ver logs en tiempo real (Opción 2 - Windows):

```batch
# PowerShell con auto-refresh cada 5 segundos
powershell -Command "while($true){Clear-Host; Get-Content C:\xampp\htdocs\MACO.AppLogistica.Web-1\tools\logs\sync_*.log -Tail 50; Start-Sleep 5}"
```

### Consultar última sincronización:

```sql
-- Ver última sincronización exitosa en el log
SELECT TOP 1 *
FROM custinvoicejour
ORDER BY FechaCreacion DESC;
```

---

## 🔧 Solución de Problemas

### Problema: "PHP no encontrado"

**Solución:** Editar `sync_facturas.bat` y ajustar la ruta de PHP:
```batch
set PHP_PATH=C:\xampp\php\php.exe
```

### Problema: "Error de conexión a BD"

**Solución:** Verificar credenciales en `conexionBD/conexion.php`

### Problema: "Task Scheduler no ejecuta la tarea"

**Solución:**
1. Verificar que la tarea está habilitada
2. Ejecutar como administrador
3. Configurar "Ejecutar tanto si el usuario inició sesión como si no"
4. Ver Event Viewer para errores específicos

### Problema: "SyncCustinvoicejour tarda mucho"

**Solución:**
- Aumentar timeout en script PHP (línea de `sqlsrv_query`)
- Revisar índices en tabla `custinvoicejour`
- Considerar optimizar el SP si es posible

---

## 📅 Recomendaciones

1. **Usar Opción 1** (SQL Server Agent) si tienes SQL Server On-Premise
2. **Usar Opción 2** (Task Scheduler) para desarrollo local con XAMPP
3. **Usar Opción 3** (Azure Automation) si estás en Azure SQL Database
4. **Monitorear logs** al menos 1 vez al día los primeros 3 días
5. **Mantener logs** solo de los últimos 7 días (limpiar automáticamente)
6. **Alertas**: Configurar notificaciones si la sync falla más de 3 veces seguidas

---

## 📝 Notas Importantes

- ⏰ **Frecuencia**: Cada 20 minutos, 24/7
- 📊 **Impacto**: Bajo (solo sincroniza cambios)
- 🔒 **Seguridad**: Las credenciales están en archivos locales (proteger acceso)
- 💾 **Logs**: Se rotan diariamente (1 archivo por día)
- ⚡ **Rendimiento**: ~1-3 segundos por ejecución típica

---

**Última actualización:** 2025-12-10
**Versión:** 1.0
**Autor:** Sistema MACO AppLogística
