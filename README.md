# Almacén QR

Inventario en PHP y MySQL. Cada producto tiene una etiqueta QR que abre su ficha desde el escáner.

## Abrir en Windows

1. En XAMPP, inicia **Apache** y **MySQL**.
2. Entra a [phpMyAdmin](http://localhost/phpmyadmin) e importa `sql/inventario.sql`.
3. Ejecuta `iniciar.bat` y abre [localhost:8000](http://localhost:8000).

## Probar el QR

En **Escáner → Leer desde imagen**, carga `ejemplos/TEC-001.png`. Se abrirá la ficha de **Teclado compacto**, que empieza con 3 unidades.

Registra una salida de 2 unidades. Si intentas sacar 2 más, la segunda salida se rechaza y queda 1 unidad en stock. Puedes revisar el movimiento en **Historial**.

Para sacar tu propia etiqueta, abre cualquier producto y pulsa **Imprimir etiqueta**.
