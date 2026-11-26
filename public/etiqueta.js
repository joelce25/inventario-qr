'use strict';
const contenedor = document.querySelector('#qr');
const qr = qrcode(0, 'M');
qr.addData(contenedor.dataset.sku);
qr.make();
contenedor.innerHTML = qr.createSvgTag({cellSize: 5, margin: 20, scalable: true});
document.querySelector('#imprimir').addEventListener('click', () => window.print());
