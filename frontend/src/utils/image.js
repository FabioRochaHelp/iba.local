/**
 * Reduz a foto no navegador antes do envio: recorte quadrado central em
 * `size` px, JPEG. Fotos de celular (vários MB) viram ~50–100 KB, cabendo no
 * limite de 1 MB da API. O servidor revalida e regrava a imagem de qualquer forma.
 */
export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp']
export const PHOTO_MAX_MB = 15

function readAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = () => reject(new Error('Não foi possível ler o arquivo.'))
    reader.readAsDataURL(file)
  })
}

async function loadImage(file) {
  // createImageBitmap já aplica a rotação do EXIF (fotos de celular "deitadas").
  if (window.createImageBitmap) {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' })
    } catch {
      /* cai no <img> abaixo */
    }
  }
  const url = await readAsDataUrl(file)
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('Arquivo de imagem inválido.'))
    img.src = url
  })
}

export async function squarePhoto(file, size = 512) {
  if (!PHOTO_TYPES.includes(file.type)) {
    throw new Error('Escolha uma imagem JPG, PNG ou WebP.')
  }
  if (file.size > PHOTO_MAX_MB * 1024 * 1024) {
    throw new Error(`A imagem deve ter no máximo ${PHOTO_MAX_MB} MB.`)
  }

  const img = await loadImage(file)
  const w = img.width
  const h = img.height
  if (Math.min(w, h) < 64) throw new Error('Imagem pequena demais.')

  const side = Math.min(w, h)
  const out = Math.min(size, side)
  const canvas = document.createElement('canvas')
  canvas.width = out
  canvas.height = out
  const ctx = canvas.getContext('2d')
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, out, out)
  ctx.drawImage(img, (w - side) / 2, (h - side) / 2, side, side, 0, 0, out, out)
  img.close?.()

  return canvas.toDataURL('image/jpeg', 0.85)
}
