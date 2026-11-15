// textures/wallpaper.js — Модуль ресурса 3D Studio Pro
export function createWallpaperTexture() {
            const c = document.createElement('canvas'); c.width = 128; c.height = 128;
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#e5e0d8'; ctx.fillRect(0, 0, 128, 128);
            ctx.fillStyle = 'rgba(148, 163, 184, 0.28)';
            for (let x = 8; x < 128; x += 24) ctx.fillRect(x, 0, 6, 128);
            const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(8, 2);
            return t;
        }

export default { id: 'wallpaper', create: createWallpaperTexture };
