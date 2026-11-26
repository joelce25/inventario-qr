'use strict';
const video = document.querySelector('#video');
const lienzo = document.querySelector('#lienzo');
const contexto = lienzo.getContext('2d', {willReadFrequently: true});
const estado = document.querySelector('#estado');
const activar = document.querySelector('#camara');
const parar = document.querySelector('#parar');
let sesion = 0;
let flujo;
let temporizador;

function detener() {
  sesion++;
  clearTimeout(temporizador);
  flujo?.getTracks().forEach(pista => pista.stop());
  flujo = null;
  video.srcObject = null;
  video.hidden = true;
  activar.hidden = false;
  parar.hidden = true;
}

function leer(imagen, ancho, alto) {
  const escala = Math.min(1, 1100 / Math.max(ancho, alto));
  lienzo.width = Math.max(1, Math.round(ancho * escala));
  lienzo.height = Math.max(1, Math.round(alto * escala));
  contexto.drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
  const pixels = contexto.getImageData(0, 0, lienzo.width, lienzo.height);
  const qr = jsQR(pixels.data, pixels.width, pixels.height);
  if (!qr) return false;
  const sku = qr.data.trim().toUpperCase();
  if (!/^[A-Z0-9][A-Z0-9-]{0,31}$/.test(sku)) {
    estado.textContent = 'Ese QR no contiene un código de producto válido.';
    return false;
  }
  detener();
  window.location.search = new URLSearchParams({vista: 'producto', sku}).toString();
  return true;
}

function fotograma() {
  if (!flujo) return;
  if (video.readyState >= 2 && video.videoWidth && leer(video, video.videoWidth, video.videoHeight)) return;
  temporizador = setTimeout(fotograma, 180);
}

activar.onclick = async () => {
  detener();
  const actual = sesion;
  if (!navigator.mediaDevices?.getUserMedia) {
    estado.textContent = 'La cámara necesita HTTPS o localhost. Puedes cargar una imagen o ingresar el código.';
    return;
  }
  activar.hidden = true;
  parar.hidden = false;
  estado.textContent = 'Esperando permiso de cámara…';
  try {
    const stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
    if (actual !== sesion) { stream.getTracks().forEach(p => p.stop()); return; }
    flujo = stream;
    video.srcObject = flujo;
    video.hidden = false;
    await video.play();
    if (actual !== sesion) return;
    estado.textContent = 'Centra la etiqueta dentro de la imagen.';
    fotograma();
  } catch {
    if (actual !== sesion) return;
    detener();
    estado.textContent = 'No se pudo abrir la cámara. Revisa el permiso o carga una imagen.';
  }
};
parar.onclick = () => { detener(); estado.textContent = 'Cámara detenida.'; };
window.addEventListener('pagehide', detener);
document.querySelector('#imagen').onchange = async event => {
  const archivo = event.target.files[0];
  if (!archivo) return;
  detener();
  const actual = sesion;
  estado.textContent = 'Leyendo imagen…';
  const url = URL.createObjectURL(archivo);
  try {
    const foto = new Image();
    foto.src = url;
    await foto.decode();
    if (actual !== sesion) return;
    if (!leer(foto, foto.naturalWidth, foto.naturalHeight) && estado.textContent === 'Leyendo imagen…') {
      estado.textContent = 'No se encontró un QR. Prueba con una imagen más nítida.';
    }
  } catch { estado.textContent = 'No se pudo leer la imagen.'; }
  finally { URL.revokeObjectURL(url); event.target.value = ''; }
};
