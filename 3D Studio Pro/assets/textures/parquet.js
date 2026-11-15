// textures/parquet.js — Модуль ресурса 3D Studio Pro
export function createParquetTexture() {
            const c = document.createElement('canvas'); c.width = 256; c.height = 256;
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#8b5a2b'; ctx.fillRect(0, 0, 256, 256);
            for (let r = 0; r < 8; r++) {
                for (let col = 0; col < 4; col++) {
                    const s = 115 + ((r * 7 + col * 13) % 30);
                    ctx.fillStyle = `rgb(${s + 25}, ${Math.floor(s * 0.72)}, ${Math.floor(s * 0.42)})`;
                    ctx.fillRect((col * 64 + (r % 2) * 32) % 256, r * 32, 62, 30);
                }
            }
            const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(6, 6);
            return t;
        }

export default { id: 'parquet', create: createParquetTexture };
