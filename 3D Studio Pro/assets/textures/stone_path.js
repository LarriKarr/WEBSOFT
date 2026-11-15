// textures/stone_path.js — Модуль ресурса 3D Studio Pro
export function createStonePathTexture() {
            const c = document.createElement('canvas'); c.width = 256; c.height = 256;
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#64748b'; ctx.fillRect(0, 0, 256, 256);
            for (let y = 0; y < 256; y += 32) {
                for (let x = 0; x < 256; x += 48) {
                    const v = 130 + ((x + y) % 35);
                    ctx.fillStyle = `rgb(${v},${v + 4},${v + 10})`;
                    ctx.beginPath(); ctx.roundRect(x + 3 + ((y / 32) % 2) * 12, y + 3, 42, 26, 5); ctx.fill();
                }
            }
            const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(2, 10);
            return t;
        }

export default { id: 'stone_path', create: createStonePathTexture };
