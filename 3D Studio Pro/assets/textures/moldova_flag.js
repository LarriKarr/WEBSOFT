// textures/moldova_flag.js — Модуль ресурса 3D Studio Pro
export function createMoldovaFlagTexture() {
            const c = document.createElement('canvas'); c.width = 300; c.height = 150;
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#0046ae'; ctx.fillRect(0, 0, 100, 150);
            ctx.fillStyle = '#ffd200'; ctx.fillRect(100, 0, 100, 150);
            ctx.fillStyle = '#cc092f'; ctx.fillRect(200, 0, 100, 150);
            ctx.fillStyle = '#78350f';
            ctx.beginPath();
            ctx.moveTo(150, 42);
            ctx.lineTo(170, 62);
            ctx.lineTo(164, 98);
            ctx.lineTo(150, 110);
            ctx.lineTo(136, 98);
            ctx.lineTo(130, 62);
            ctx.closePath();
            ctx.fill();
            ctx.fillStyle = '#dc2626'; ctx.fillRect(142, 62, 16, 14);
            ctx.fillStyle = '#1d4ed8'; ctx.fillRect(142, 76, 16, 14);
            ctx.fillStyle = '#facc15';
            ctx.fillRect(148, 34, 4, 12);
            ctx.fillRect(145, 37, 10, 3);
            return new THREE.CanvasTexture(c);
        }

export default { id: 'moldova_flag', create: createMoldovaFlagTexture };
