// assets/environments/supermarket.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'supermarket',
    name: 'Супермаркет',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x0f172a : 0x1e293b);

        const tileFloor = new THREE.Mesh(new THREE.PlaneGeometry(14, 14), new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.3 }));
        tileFloor.rotation.x = -Math.PI / 2; envGroup.add(tileFloor);

        const prodColors = [0xef4444, 0x3b82f6, 0x22c55e, 0xeab308, 0xa855f7];
        [-3.2, 0, 3.2].forEach((sx, rIdx) => {
            const rack = new THREE.Group();
            const frame = new THREE.Mesh(new THREE.BoxGeometry(1.1, 2.1, 4.5), new THREE.MeshStandardMaterial({ color: 0x334155 }));
            frame.position.y = 1.05; rack.add(frame);
            for (let y = 0.5; y <= 1.8; y += 0.45) {
                for (let z = -1.8; z <= 1.8; z += 0.6) {
                    const item = new THREE.Mesh(new THREE.BoxGeometry(1.16, 0.28, 0.42), new THREE.MeshStandardMaterial({ color: prodColors[(rIdx + Math.round(z + 2)) % 5] }));
                    item.position.set(0, y, z); rack.add(item);
                }
            }
            rack.position.set(sx, 0, -2.2); envGroup.add(rack);
            addStaticCollider(sx, -2.2, 1.2, 4.5);
        });

        [-2.5, 2.5].forEach(cx => {
            const counter = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.9, 2.2), new THREE.MeshStandardMaterial({ color: 0x0284c7 }));
            counter.position.set(cx, 0.45, 3.4); envGroup.add(counter);
            addStaticCollider(cx, 3.4, 1.2, 2.2);
        });
    }
};
