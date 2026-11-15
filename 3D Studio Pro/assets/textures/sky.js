// textures/sky.js — Модуль ресурса 3D Studio Pro
export function createSkyTexture(isNight, withMetropolis = false) {
            const c = document.createElement('canvas'); c.width = 512; c.height = 256;
            const ctx = c.getContext('2d');
            const g = ctx.createLinearGradient(0, 0, 0, 256);
            if (!isNight) {
                g.addColorStop(0, '#0284c7'); g.addColorStop(1, '#bae6fd');
                ctx.fillStyle = g; ctx.fillRect(0, 0, 512, 256);
                ctx.fillStyle = '#fde047'; ctx.beginPath(); ctx.arc(410, 55, 26, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = 'rgba(255,255,255,0.85)';
                ctx.beginPath(); ctx.arc(120, 70, 22, 0, Math.PI * 2); ctx.arc(148, 65, 28, 0, Math.PI * 2); ctx.fill();
            } else {
                g.addColorStop(0, '#020617'); g.addColorStop(1, '#0f172a');
                ctx.fillStyle = g; ctx.fillRect(0, 0, 512, 256);
                ctx.fillStyle = '#fef9c3'; ctx.beginPath(); ctx.arc(410, 60, 22, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#050b1c'; ctx.beginPath(); ctx.arc(400, 53, 19, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#ffffff';
                [[50, 35], [110, 28], [180, 60], [260, 30], [320, 75], [80, 95]].forEach(([sx, sy]) => {
                    ctx.beginPath(); ctx.arc(sx, sy, 2, 0, Math.PI * 2); ctx.fill();
                });
            }
            if (withMetropolis) {
                for (let x = 10; x < 500; x += 38) {
                    const h = 70 + ((x * 19) % 110);
                    ctx.fillStyle = isNight ? '#1e293b' : '#475569';
                    ctx.fillRect(x, 256 - h, 32, h);
                    ctx.fillStyle = isNight ? '#fde047' : '#bae6fd';
                    for (let wy = 264 - h; wy < 248; wy += 12) {
                        for (let wx = x + 5; wx < x + 26; wx += 9) {
                            if ((wx + wy) % 3 !== 0) ctx.fillRect(wx, wy, 5, 7);
                        }
                    }
                }
            }
            return new THREE.CanvasTexture(c);
        }

export default { id: 'sky', create: createSkyTexture };
