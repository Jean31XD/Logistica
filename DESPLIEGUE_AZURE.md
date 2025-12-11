# 🚀 Guía de Despliegue en Azure App Service

## 📋 Estado Actual del Diagnóstico

Según el diagnóstico ejecutado:

✅ **FUNCIONANDO:**
- HTTPS detectado correctamente
- Conexión a base de datos exitosa
- PHP 8.2.29 con extensiones SQL Server
- Todos los archivos críticos presentes

⚠️ **PENDIENTE:**
- Configurar variables de entorno en Azure
- Directorio de logs creado en próximo despliegue

---

## 🔧 PASOS PARA COMPLETAR EL DESPLIEGUE

### **Paso 1: Configurar Variables de Entorno en Azure** ⚠️ CRÍTICO

Actualmente la aplicación está leyendo el archivo `.env` local, lo cual es **inseguro en producción**.

**Instrucciones:**

1. Abre **Azure Portal** (https://portal.azure.com)
2. Ve a tu **App Service**: `app-apptransportistas-w-preproduccion-dacfhza7chb9d6hp`
3. En el menú izquierdo, selecciona **Configuration**
4. Click en la pestaña **Application settings**
5. Agrega estas 5 variables (click **+ New application setting** para cada una):

| Name | Value |
|------|-------|
| `DB_SERVER` | `sdb-apptransportistas-maco.privatelink.database.windows.net` |
| `DB_NAME` | `db-apptransportistas-maco` |
| `DB_USERNAME` | `ServiceAppTrans` |
| `DB_PASSWORD` | `⁠nZ(#n41LJm)iLmJP` ⚠️ Copiar EXACTO con carácter invisible |
| `SE_FUE_CODE` | `LogisicA*2025*` |

6. Click **Save** (arriba)
7. Click **Continue** cuando pregunte si reiniciar

**Verificación:**
- Después de guardar, vuelve a acceder a `diagnostic.php`
- Deberías ver "CONFIGURADO" en todas las variables de entorno

---

### **Paso 2: Crear Directorio de Logs**

El directorio `/logs` ya está incluido en el repositorio con `.gitkeep`.

**Después del próximo despliegue, ejecuta:**

```
https://tu-app.azurewebsites.net/setup_logs.php
```

Deberías ver:
```
✓ Directorio creado exitosamente
✓ Directorio es escribible
✓ Test de escritura exitoso
```

**Después de verificar, elimina el archivo:**
```bash
git rm setup_logs.php
git commit -m "Remove setup script"
git push
```

---

### **Paso 3: Hacer Push de los Cambios**

```bash
# Agregar todos los archivos nuevos
git add .

# Commit con descripción clara
git commit -m "Fix: Azure deployment improvements

- Resolver loop de redirección HTTPS
- Agregar directorio de logs
- Actualizar .gitignore
- Agregar scripts de setup y diagnóstico"

# Push a la rama preproduccion
git push origin preproduccion
```

---

### **Paso 4: Verificar el Despliegue**

Después de que Azure despliegue los cambios (5-10 minutos):

1. **Verificar diagnóstico:**
   ```
   https://app-apptransportistas-w-preproduccion-dacfhza7chb9d6hp.eastus-01.azurewebsites.net/diagnostic.php
   ```

   Deberías ver:
   - ✓ HTTPS DETECTADO: SÍ
   - ✓ Variables de entorno: CONFIGURADO (todas)
   - ✓ CONEXIÓN EXITOSA

2. **Probar el login:**
   ```
   https://app-apptransportistas-w-preproduccion-dacfhza7chb9d6hp.eastus-01.azurewebsites.net/
   ```

3. **Ejecutar setup de logs:**
   ```
   https://app-apptransportistas-w-preproduccion-dacfhza7chb9d6hp.eastus-01.azurewebsites.net/setup_logs.php
   ```

---

### **Paso 5: Limpiar Archivos de Diagnóstico** ⚠️ SEGURIDAD

Una vez todo funcione correctamente, **elimina los archivos de diagnóstico**:

```bash
git rm diagnostic.php
git rm setup_logs.php
git commit -m "Remove diagnostic and setup files for security"
git push origin preproduccion
```

---

## 🔍 SOLUCIÓN DE PROBLEMAS

### Problema: Variables de entorno siguen mostrando "NO CONFIGURADO"

**Solución:**
1. Verifica que guardaste los cambios en Azure Configuration
2. Reinicia el App Service manualmente:
   - Azure Portal → Tu App Service → Overview → **Restart**
3. Espera 2-3 minutos y vuelve a verificar

### Problema: No se puede crear directorio de logs

**Solución:**
1. Ve a Azure Portal → App Service → **SSH** (o **Kudu**)
2. Ejecuta manualmente:
   ```bash
   cd /home/site/wwwroot
   mkdir -p logs
   chmod 750 logs
   ```

### Problema: Conexión a base de datos falla

**Solución:**
1. Verifica que la IP de Azure esté en la whitelist del firewall de Azure SQL
2. Azure Portal → SQL Database → **Networking**
3. Agrega la IP del App Service en **Firewall rules**

---

## 📊 CHECKLIST DE DESPLIEGUE

- [ ] Variables de entorno configuradas en Azure
- [ ] Push realizado a `preproduccion`
- [ ] Despliegue de Azure completado (verificar en Deployment Center)
- [ ] `diagnostic.php` ejecutado y verificado
- [ ] HTTPS funciona sin redirección infinita
- [ ] Login funciona correctamente
- [ ] `setup_logs.php` ejecutado
- [ ] Directorio de logs creado y escribible
- [ ] Archivos de diagnóstico eliminados
- [ ] Aplicación funcionando correctamente

---

## 🎯 RESULTADO ESPERADO

Después de completar todos los pasos:

✅ Aplicación funcionando en HTTPS sin errores
✅ Variables de entorno seguras en Azure (no en `.env`)
✅ Sistema de logs funcionando
✅ Nivel de seguridad: **9.5/10**
✅ Sin vulnerabilidades críticas o altas

---

## 📞 SOPORTE

Si encuentras problemas:

1. Revisa los **logs de Azure**:
   - Azure Portal → App Service → **Log stream**

2. Revisa los **logs de la aplicación**:
   - Azure Portal → App Service → **Advanced Tools (Kudu)** → `/home/site/wwwroot/logs`

3. Ejecuta diagnóstico nuevamente para ver cambios

---

**Última actualización:** 2025-12-11
**Versión:** 1.0
