#!/bin/bash

# Copia nuestra configuración personalizada de Nginx a la ubicación correcta en el servidor
cp /home/site/wwwroot/config/nginx/default.conf /etc/nginx/sites-enabled/default

# Recarga Nginx para aplicar los cambios
service nginx reload

# Inicia Nginx en primer plano (esto es crucial para los scripts de inicio personalizados en Azure)
nginx -g "daemon off;"
