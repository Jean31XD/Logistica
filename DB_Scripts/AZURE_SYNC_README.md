# Guía de Sincronización para Azure SQL Database

## 🔴 Problema
Azure SQL Database **NO soporta SQL Server Agent Jobs**. No puedes usar `sp_add_job`.

---

## ✅ Soluciones Disponibles

### **Opción 1: Azure Logic App** ⭐ (Recomendado)

**Ventajas:**
- ✅ Muy fácil de configurar (visual, sin código)
- ✅ Económico (~$0.001 por ejecución)
- ✅ Logs automáticos en Azure Portal

**Pasos:**
1. **Azure Portal** → Logic Apps → **Create**
2. **Add trigger** → Buscar "Recurrence"
3. Configurar:
   - Frequency: **Day**
   - Interval: **1**
   - Time zone: **(UTC-04:00) Santiago**
   - At these hours: **2**
4. **Add action** → Buscar "SQL Server - Execute stored procedure"
5. Configurar conexión:
   - Server: `tu-servidor.database.windows.net`
   - Database: `MACODB`
   - Authentication: **SQL Server Authentication** o **Managed Identity**
6. Seleccionar procedure: **SyncCustinvoicejour**
7. **Save** y **Enable**

**Costo estimado:** $0.03/mes (1 ejecución diaria)

---

### **Opción 2: Azure Automation Runbook**

**Ventajas:**
- ✅ Más control con PowerShell
- ✅ Puede ejecutar lógica compleja
- ✅ Integrado con Azure

**Pasos:**
1. **Azure Portal** → Automation Accounts → **Create**
2. Crear Runbook → **PowerShell**
3. Pegar código:

```powershell
param()

$ServerName = "tu-servidor.database.windows.net"
$DatabaseName = "MACODB"
$Query = "EXEC SyncCustinvoicejour"

# Obtener token de autenticación
$token = (Get-AzAccessToken -ResourceUrl "https://database.windows.net").Token

# Ejecutar SP
Invoke-Sqlcmd -ServerInstance $ServerName `
              -Database $DatabaseName `
              -Query $Query `
              -AccessToken $token

Write-Output "Sincronización completada: $(Get-Date)"
```

4. **Publish** el runbook
5. **Link to Schedule** → Daily 2:00 AM

**Costo estimado:** Gratis (primeras 500 horas/mes)

---

### **Opción 3: Ejecución Manual** 💼

Si la sincronización no es crítica o puedes ejecutarla manualmente:

```sql
-- Ejecutar en SQL Server Management Studio cuando sea necesario
EXEC SyncCustinvoicejour;
```

**Pros:** Gratis, simple  
**Contras:** Requiere acción manual

---

### **Opción 4: Azure Function** (Avanzado)

Para desarrolladores que prefieren código:

**C# Timer Function:**
```csharp
[FunctionName("SyncFacturas")]
public static async Task Run(
    [TimerTrigger("0 0 2 * * *")] TimerInfo myTimer, 
    ILogger log)
{
    var connString = Environment.GetEnvironmentVariable("SqlConnectionString");
    using var connection = new SqlConnection(connString);
    await connection.OpenAsync();
    
    using var cmd = new SqlCommand("SyncCustinvoicejour", connection);
    cmd.CommandType = CommandType.StoredProcedure;
    await cmd.ExecuteNonQueryAsync();
    
    log.LogInformation($"Sync completed at: {DateTime.Now}");
}
```

**Cron:** `0 0 2 * * *` = Todos los días a las 2:00 AM

---

## 📊 Comparación de Opciones

| Opción | Dificultad | Costo/mes | Logs | Recomendado |
|--------|-----------|-----------|------|-------------|
| **Logic App** | ⭐ Fácil | $0.03 | ✅ Sí | ✅ **SÍ** |
| **Automation** | ⭐⭐ Media | Gratis | ✅ Sí | ✅ Sí |
| **Manual** | ⭐ Muy fácil | Gratis | ❌ No | Solo temporal |
| **Function** | ⭐⭐⭐ Difícil | $0.20 | ✅ Sí | Para devs |

---

## 🚀 Recomendación Final

**Para MACO:** Usar **Azure Logic App**

**Razones:**
1. ✅ Configuración en 5 minutos
2. ✅ Costo insignificante ($0.03/mes)
3. ✅ Monitoreo visual en Azure Portal
4. ✅ No requiere código ni mantenimiento

---

## 📝 Próximos Pasos

1. ✅ Ejecutar `05_sincronizacion_azure.sql` para sincronizar manualmente AHORA
2. ⏳ Configurar Azure Logic App para automatización (5 minutos)
3. ✅ Verificar que la Logic App ejecute correctamente
4. ✅ Deshabilitar popup/notificaciones innecesarias

---

## 🔧 Script Creado

He creado **`05_sincronizacion_azure.sql`** que:
- ✅ Ejecuta la sincronización manualmente
- 📖 Incluye instrucciones completas para cada opción
- 🔍 Maneja errores automáticamente
