# Reporte de Google Analytics 4

1. Ejecuta las migraciones `database/2026-10-27_create_google_analytics.sql` y
   `database/2026-10-27_add_ga4_reporting.sql` en ese orden.
2. En Google Cloud habilita **Google Analytics Data API** y crea una cuenta de
   servicio. Concede a su correo acceso de lectura a la propiedad GA4.
3. Coloca el JSON de la cuenta de servicio fuera del directorio web, restringe
   su lectura al usuario de Apache y define en el entorno de Apache la variable
   `GOOGLE_APPLICATION_CREDENTIALS` con la ruta absoluta de ese archivo.
   Reinicia Apache para que el proceso la reciba. No subas el JSON al proyecto.
4. En **Configuración → Google Analytics**, indica el ID numérico de propiedad
   y activa el reporte. El ID de propiedad es distinto al ID de medición `G-…`.
5. Abre **Reportes Generales → Reporte Google Analytics** con un rol que tenga
   permiso de consulta tanto en el reporte como en su menú padre.

El reporte consulta los últimos 7, 30 o 90 días y mantiene un resultado por
sesión durante cinco minutos. Presenta datos de la propiedad completa; si la
propiedad contiene varios flujos, no se limita al ID de medición configurado.
La medición y el reporte se activan independientemente. La recepción de datos
depende de que GA4 reciba y procese eventos, y de que la cuenta de servicio y
la API estén correctamente configuradas.
