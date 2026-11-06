# ONUBA GOURMET MARKET 

## Dependencias con Docker

PROBLEMA DETECTADO: tardanza para cargar las páginas.
Actualmente PHP se ejecuta en Docker y cada vez que necesita una librería tiene que acceder a mi carpeta de Windows.

CAMBIO: consiste en guardar las librerías `vendor` de Laravel dentro de Docker, donde PHP puede leerlas mucho mas rápido. 
Ahora esas librerias están en un volumen de Docker, un espacio de almacenamiento que se conserva aunque reinicie el contenedor. 

MODIFICACIONES EN EL CÓDIGO: en docker-compose.yml añado al servicio app la instrucción y declaro un nuevo volumen. 

El código se comparte desde Windows y las librerías `vendor` se guardan en el volumen interno `vendor_data` para acelerar Laravel. La carpeta `vendor` de Windows se conserva, pero el servidor Docker utiliza la del volumen.



SI LLEVO EL PROYECTO A OTRO ORDENADOR (En una instalación nueva): el volumen de dependencias estará vacío. Hay que inicializar las dependencias antes de arrancar:
    - 1º en powershell, para llenar el volumen con las dependecias:
        docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
        docker compose up -d

Tras cambiar `composer.lock` (este registra las versiones exactas de las librerias, y puede cambiar al añadir una librería o recibir cambios del proyecto), hay que sincronizar las dependencias del servidor con:
    - 2º en powershell escribimos el siguiente comando para actualizar el contenido de vendor y coincida con el composer.lock:
        docker compose exec app composer install --no-interaction --prefer-dist


Para volver a leer `vendor` desde Windows, elimina el montaje
`vendor_data:/var/www/html/vendor` del servicio `app` y ejecuta
`docker compose up -d --no-deps app`. No es necesario borrar ningún volumen.
